<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Read-only identity matching for classified Moneybird suppliers. */
final class MoneybirdSupplierIdentityDiagnosis {

  private const BUNDLE = 'brebo_organization';
  private const MONEYBIRD_FIELD = 'field_brebo_moneybird_contact_id';

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * @param array<int, array<string, mixed>> $invoices
   *   Normalized purchase invoices from the BREBO Integration API.
   *
   * @return array<string, mixed>
   *   Mutation-free identity diagnosis for contacts classified as suppliers.
   */
  public function diagnose(array $invoices): array {
    $classifier = new MoneybirdSupplierClassifier();
    $classification = $classifier->classify($invoices);
    $supplierIds = [];
    foreach (($classification['supplier'] ?? []) as $item) {
      $supplierIds[(string) ($item['contact_id'] ?? '')] = TRUE;
    }

    $contacts = [];
    foreach ($invoices as $invoice) {
      $contactId = trim((string) ($invoice['contact_id'] ?? ''));
      if ($contactId === '' || !isset($supplierIds[$contactId])) {
        continue;
      }
      if (!isset($contacts[$contactId])) {
        $contacts[$contactId] = [
          'contact_id' => $contactId,
          'name' => trim((string) ($invoice['supplier_name'] ?? '')),
          'supplier_contact' => is_array($invoice['supplier_contact'] ?? NULL) ? $invoice['supplier_contact'] : [],
        ];
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $organizations = $storage->loadByProperties(['type' => self::BUNDLE]);
    $indexes = $this->buildIndexes($organizations);

    $result = [
      'supplier_count' => count($contacts),
      'by_moneybird_id' => [],
      'strong_match' => [],
      'possible_match' => [],
      'new' => [],
      'conflict' => [],
    ];

    foreach ($contacts as $contact) {
      $resolved = $this->matchContact($contact, $indexes);
      $result[$resolved['group']][] = $resolved['item'];
    }

    foreach (['by_moneybird_id', 'strong_match', 'possible_match', 'new', 'conflict'] as $group) {
      usort($result[$group], static fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
      $result[$group . '_count'] = count($result[$group]);
    }

    return $result;
  }

  /** @param array<int|string, mixed> $organizations */
  private function buildIndexes(array $organizations): array {
    $indexes = [
      'moneybird' => [],
      'kvk' => [],
      'vat' => [],
      'name' => [],
    ];

    foreach ($organizations as $organization) {
      if (!$organization instanceof NodeInterface) {
        continue;
      }
      $nid = (int) $organization->id();
      $moneybird = $this->field($organization, self::MONEYBIRD_FIELD);
      $kvk = $this->identity($this->field($organization, 'field_brebo_org_kvk'));
      $vat = $this->identity($this->field($organization, 'field_brebo_org_vat'));
      $name = $this->text((string) $organization->label());

      if ($moneybird !== '') {
        $indexes['moneybird'][$moneybird][] = $nid;
      }
      if ($kvk !== '') {
        $indexes['kvk'][$kvk][] = $nid;
      }
      if ($vat !== '') {
        $indexes['vat'][$vat][] = $nid;
      }
      if ($name !== '') {
        $indexes['name'][$name][] = $nid;
      }
    }

    return $indexes;
  }

  /** @param array<string, mixed> $contact */
  private function matchContact(array $contact, array $indexes): array {
    $contactId = trim((string) ($contact['contact_id'] ?? ''));
    $name = trim((string) ($contact['name'] ?? ''));
    $master = is_array($contact['supplier_contact'] ?? NULL) ? $contact['supplier_contact'] : [];
    $base = ['contact_id' => $contactId, 'name' => $name];

    $moneybirdMatches = array_values(array_unique(array_map('intval', $indexes['moneybird'][$contactId] ?? [])));
    if (count($moneybirdMatches) === 1) {
      return ['group' => 'by_moneybird_id', 'item' => $base + ['organization_nid' => $moneybirdMatches[0], 'reason' => 'moneybird_contact_id']];
    }
    if (count($moneybirdMatches) > 1) {
      return ['group' => 'conflict', 'item' => $base + ['organization_nids' => $moneybirdMatches, 'reason' => 'duplicate_moneybird_contact_id']];
    }

    $kvk = $this->identity((string) ($master['chamber_of_commerce'] ?? ''));
    $vat = $this->identity((string) ($master['tax_number'] ?? ''));
    $registration = [];
    foreach ($kvk !== '' ? ($indexes['kvk'][$kvk] ?? []) : [] as $nid) {
      $registration[(int) $nid] = TRUE;
    }
    foreach ($vat !== '' ? ($indexes['vat'][$vat] ?? []) : [] as $nid) {
      $registration[(int) $nid] = TRUE;
    }
    $registrationIds = array_keys($registration);
    if (count($registrationIds) === 1) {
      return ['group' => 'strong_match', 'item' => $base + ['organization_nid' => (int) $registrationIds[0], 'reason' => $kvk !== '' && $vat !== '' ? 'registration_match' : ($kvk !== '' ? 'kvk_match' : 'vat_match')]];
    }
    if (count($registrationIds) > 1) {
      return ['group' => 'conflict', 'item' => $base + ['organization_nids' => array_map('intval', $registrationIds), 'reason' => 'registration_points_to_multiple_organizations']];
    }

    $nameKey = $this->text($name);
    $nameIds = array_values(array_unique(array_map('intval', $indexes['name'][$nameKey] ?? [])));
    if (count($nameIds) === 1) {
      return ['group' => 'possible_match', 'item' => $base + ['organization_nid' => $nameIds[0], 'reason' => 'exact_name_only']];
    }
    if (count($nameIds) > 1) {
      return ['group' => 'conflict', 'item' => $base + ['organization_nids' => $nameIds, 'reason' => 'duplicate_exact_name']];
    }

    return ['group' => 'new', 'item' => $base + ['reason' => 'no_existing_identity_match']];
  }

  private function field(NodeInterface $organization, string $field): string {
    if (!$organization->hasField($field) || $organization->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) ($organization->get($field)->value ?? ''));
  }

  private function identity(string $value): string {
    return mb_strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($value)));
  }

  private function text(string $value): string {
    $value = mb_strtolower(trim($value));
    return preg_replace('/\s+/', ' ', $value) ?? $value;
  }

}
