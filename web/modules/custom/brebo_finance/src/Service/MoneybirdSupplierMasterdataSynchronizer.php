<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\node\NodeInterface;

/** Safely enriches a BREBO organization from sanitized Moneybird contact data. */
final class MoneybirdSupplierMasterdataSynchronizer {

  /**
   * Synchronizes only empty organization fields and reports conflicts.
   *
   * @param array<string, mixed> $contact
   *   Sanitized supplier_contact payload from the BREBO Integration API.
   *
   * @return array{updated: string[], conflicts: array<string, array{brebo: string, moneybird: string}>}
   */
  public function synchronize(NodeInterface $organization, array $contact): array {
    $mapping = [
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

    $updated = [];
    $conflicts = [];
    foreach ($mapping as $source => $field) {
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
        $updated[] = $field;
      }
      elseif ($this->comparable($current) !== $this->comparable($incoming)) {
        $conflicts[$field] = ['brebo' => $current, 'moneybird' => $incoming];
      }
    }

    if ($organization->hasField('field_brebo_sepa_active')) {
      $incomingSepa = ($contact['sepa_active'] ?? FALSE) === TRUE || ($contact['direct_debit'] ?? FALSE) === TRUE;
      $currentSepa = !$organization->get('field_brebo_sepa_active')->isEmpty()
        ? (bool) $organization->get('field_brebo_sepa_active')->value
        : NULL;
      if ($currentSepa === NULL && $incomingSepa) {
        $organization->set('field_brebo_sepa_active', 1);
        $updated[] = 'field_brebo_sepa_active';
      }
      elseif ($currentSepa === FALSE && $incomingSepa) {
        // A deliberate BREBO 'off' is not silently changed into an active mandate.
        $conflicts['field_brebo_sepa_active'] = ['brebo' => '0', 'moneybird' => '1'];
      }
    }

    if ($updated !== []) {
      $organization->save();
    }

    return ['updated' => $updated, 'conflicts' => $conflicts];
  }

  private function normalize(mixed $value): string {
    return is_scalar($value) ? trim((string) $value) : '';
  }

  private function comparable(string $value): string {
    return mb_strtolower(preg_replace('/\s+/', ' ', trim($value)) ?? trim($value));
  }

}
