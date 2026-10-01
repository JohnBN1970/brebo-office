<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\brebo_finance\Contract\OrganizationReferenceGatewayInterface;

/**
 * Read-only diagnosis for Moneybird suppliers present on purchase invoices.
 */
final class MoneybirdSupplierDiagnosis {

  public function __construct(
    private readonly Connection $database,
    private readonly OrganizationReferenceGatewayInterface $organizations,
  ) {}

  /**
   * Returns a mutation-free classification of unique supplier contacts.
   */
  public function diagnose(): array {
    $rows = $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->fields('i', ['supplier_ref', 'supplier_name'])
      ->condition('supplier_ref', '', '<>')
      ->orderBy('supplier_name')
      ->execute()
      ->fetchAllAssoc('supplier_ref');

    $result = [
      'invoice_count' => (int) $this->database->select('brebo_finance_purchase_invoice', 'i')->countQuery()->execute()->fetchField(),
      'unique_contacts' => count($rows),
      'by_moneybird_id' => [],
      'by_exact_name' => [],
      'new' => [],
      'ambiguous' => [],
      'invalid' => [],
    ];

    foreach ($rows as $row) {
      $contactId = trim((string) $row->supplier_ref);
      $name = trim((string) $row->supplier_name);
      if ($contactId === '' || $name === '') {
        $result['invalid'][] = ['contact_id' => $contactId, 'name' => $name];
        continue;
      }

      $idMatches = $this->organizations->findIdsByMoneybirdContactId($contactId);
      if (count($idMatches) === 1) {
        $result['by_moneybird_id'][] = ['contact_id' => $contactId, 'name' => $name, 'organization_nid' => (int) reset($idMatches)];
        continue;
      }
      if (count($idMatches) > 1) {
        $result['ambiguous'][] = ['contact_id' => $contactId, 'name' => $name, 'reason' => 'duplicate_moneybird_id', 'organization_nids' => array_map('intval', array_values($idMatches))];
        continue;
      }

      $nameIds = $this->organizations->findIdsByExactName($name);
      if ($nameIds === []) {
        $result['new'][] = ['contact_id' => $contactId, 'name' => $name];
        continue;
      }

      $unlinked = array_values(array_filter($nameIds, fn (int $organizationId): bool => $this->organizations->isMoneybirdUnlinked($organizationId)));
      if (count($nameIds) === 1 && count($unlinked) === 1) {
        $result['by_exact_name'][] = ['contact_id' => $contactId, 'name' => $name, 'organization_nid' => (int) $unlinked[0]];
        continue;
      }

      $result['ambiguous'][] = ['contact_id' => $contactId, 'name' => $name, 'reason' => 'name_collision', 'organization_nids' => array_map('intval', array_values($nameIds))];
    }

    foreach (['by_moneybird_id', 'by_exact_name', 'new', 'ambiguous', 'invalid'] as $key) {
      $result[$key . '_count'] = count($result[$key]);
    }

    return $result;
  }

}
