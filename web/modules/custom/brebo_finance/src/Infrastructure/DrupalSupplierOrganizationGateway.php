<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SupplierOrganizationGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal adapter for supplier organization resolution and enrichment.
 */
final class DrupalSupplierOrganizationGateway implements SupplierOrganizationGatewayInterface {

  private const BUNDLE = 'brebo_organization';
  private const TYPE_FIELD = 'field_brebo_org_type';
  private const MONEYBIRD_FIELD = 'field_brebo_moneybird_contact_id';

  private const MASTERDATA_MAPPING = [
    'address1' => 'field_brebo_org_address',
    'zipcode' => 'field_brebo_org_postal_code',
    'city' => 'field_brebo_org_city',
    'country' => 'field_brebo_org_country',
    'phone' => 'field_brebo_org_phone',
    'email' => 'field_brebo_org_email',
    'chamber_of_commerce' => 'field_brebo_org_kvk',
    'tax_number' => 'field_brebo_org_vat',
    'sepa_iban' => 'field_brebo_sepa_iban',
    'sepa_iban_account_name' => 'field_brebo_sepa_account_name',
    'sepa_bic' => 'field_brebo_sepa_bic',
    'sepa_mandate_id' => 'field_brebo_sepa_mandate_id',
    'sepa_mandate_date' => 'field_brebo_sepa_mandate_date',
    'sepa_sequence_type' => 'field_brebo_sepa_sequence',
  ];

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public function resolve(string $contactId, string $supplierName, bool $create = TRUE, array $supplierContact = []): ?array {
    $contactId = trim($contactId);
    $supplierName = trim($supplierName);
    if ($contactId === '' || $supplierName === '') {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition(self::MONEYBIRD_FIELD, $contactId)
      ->range(0, 2)
      ->execute();

    if (count($ids) === 1) {
      $organization = $storage->load(reset($ids));
      if (!$organization instanceof NodeInterface) {
        return NULL;
      }
      $this->enrich($organization, $supplierContact);
      return $this->snapshot($organization);
    }
    if (count($ids) > 1) {
      return NULL;
    }

    $nameIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->condition('title', $supplierName)
      ->range(0, 3)
      ->execute();
    $candidates = $storage->loadMultiple($nameIds);
    $unlinked = array_values(array_filter($candidates, static function ($node): bool {
      return $node instanceof NodeInterface
        && $node->hasField(self::MONEYBIRD_FIELD)
        && $node->get(self::MONEYBIRD_FIELD)->isEmpty();
    }));

    if (count($unlinked) === 1) {
      $organization = $unlinked[0];
      $organization->set(self::MONEYBIRD_FIELD, $contactId);
      if ($organization->hasField(self::TYPE_FIELD) && $organization->get(self::TYPE_FIELD)->isEmpty()) {
        $organization->set(self::TYPE_FIELD, 'leverancier');
      }
      $organization->save();
      $this->enrich($organization, $supplierContact);
      return $this->snapshot($organization);
    }

    if (!$create || $nameIds !== []) {
      return NULL;
    }

    $organization = $storage->create([
      'type' => self::BUNDLE,
      'title' => $supplierName,
      'status' => 1,
      self::TYPE_FIELD => 'leverancier',
      self::MONEYBIRD_FIELD => $contactId,
    ]);
    $organization->save();
    if (!$organization instanceof NodeInterface) {
      return NULL;
    }
    $this->enrich($organization, $supplierContact);
    return $this->snapshot($organization);
  }

  private function enrich(NodeInterface $organization, array $contact): void {
    if ($contact === []) {
      return;
    }

    $changed = FALSE;
    foreach (self::MASTERDATA_MAPPING as $source => $field) {
      if (!$organization->hasField($field)) {
        continue;
      }
      $incoming = $this->normalize($contact[$source] ?? NULL);
      if ($incoming === '') {
        continue;
      }
      $current = $this->normalize($organization->get($field)->value ?? NULL);
      if ($current === '') {
        $organization->set($field, $incoming);
        $changed = TRUE;
      }
      // Existing non-empty BREBO values are deliberately never overwritten.
    }

    if ($organization->hasField('field_brebo_sepa_active')) {
      $incomingSepa = ($contact['sepa_active'] ?? FALSE) === TRUE || ($contact['direct_debit'] ?? FALSE) === TRUE;
      $currentSepa = !$organization->get('field_brebo_sepa_active')->isEmpty()
        ? (bool) $organization->get('field_brebo_sepa_active')->value
        : NULL;
      if ($currentSepa === NULL && $incomingSepa) {
        $organization->set('field_brebo_sepa_active', 1);
        $changed = TRUE;
      }
      // Explicit FALSE remains authoritative and is never silently flipped.
    }

    if ($changed) {
      $organization->save();
    }
  }

  /** @return array{id:int,name:string,email:string} */
  private function snapshot(NodeInterface $organization): array {
    $email = $organization->hasField('field_brebo_org_email')
      ? trim((string) $organization->get('field_brebo_org_email')->value)
      : '';
    return [
      'id' => (int) $organization->id(),
      'name' => (string) $organization->label(),
      'email' => $email,
    ];
  }

  private function normalize(mixed $value): string {
    return is_scalar($value) ? trim((string) $value) : '';
  }

}
