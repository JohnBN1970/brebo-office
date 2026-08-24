<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Read-only diagnosis for Moneybird suppliers present on purchase invoices.
 */
final class MoneybirdSupplierDiagnosis {

  private const BUNDLE = 'brebo_organization';
  private const MONEYBIRD_FIELD = 'field_brebo_moneybird_contact_id';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
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

    $storage = $this->entityTypeManager->getStorage('node');
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

      $idMatches = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', self::BUNDLE)
        ->condition(self::MONEYBIRD_FIELD, $contactId)
        ->range(0, 3)
        ->execute();
      if (count($idMatches) === 1) {
        $result['by_moneybird_id'][] = ['contact_id' => $contactId, 'name' => $name, 'organization_nid' => (int) reset($idMatches)];
        continue;
      }
      if (count($idMatches) > 1) {
        $result['ambiguous'][] = ['contact_id' => $contactId, 'name' => $name, 'reason' => 'duplicate_moneybird_id', 'organization_nids' => array_map('intval', array_values($idMatches))];
        continue;
      }

      $nameIds = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', self::BUNDLE)
        ->condition('title', $name)
        ->range(0, 5)
        ->execute();
      if ($nameIds === []) {
        $result['new'][] = ['contact_id' => $contactId, 'name' => $name];
        continue;
      }

      $nodes = $storage->loadMultiple($nameIds);
      $unlinked = array_values(array_filter($nodes, static fn ($node): bool => $node instanceof NodeInterface && $node->hasField(self::MONEYBIRD_FIELD) && $node->get(self::MONEYBIRD_FIELD)->isEmpty()));
      if (count($nameIds) === 1 && count($unlinked) === 1) {
        $result['by_exact_name'][] = ['contact_id' => $contactId, 'name' => $name, 'organization_nid' => (int) $unlinked[0]->id()];
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
