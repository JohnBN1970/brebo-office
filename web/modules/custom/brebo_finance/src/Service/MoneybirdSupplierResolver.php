<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves Moneybird supplier contacts to central BREBO organizations.
 */
final class MoneybirdSupplierResolver {

  private const BUNDLE = 'brebo_organization';
  private const TYPE_FIELD = 'field_brebo_org_type';
  private const MONEYBIRD_FIELD = 'field_brebo_moneybird_contact_id';
  private const SUPPLIER_TYPE = 'Leverancier';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Resolves a supplier, creating an organization only when no match exists.
   *
   * Moneybird contact ID is authoritative. Name matching is deliberately only
   * a fallback for organizations that have not yet received a Moneybird ID.
   */
  public function resolve(string $contactId, string $supplierName, bool $create = true): ?NodeInterface {
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
      return $organization instanceof NodeInterface ? $organization : NULL;
    }
    if (count($ids) > 1) {
      // Never guess when an external identity is already duplicated.
      return NULL;
    }

    // Controlled fallback: exact organization title, but only if exactly one
    // candidate exists and its Moneybird identity is still empty.
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
        $organization->set(self::TYPE_FIELD, self::SUPPLIER_TYPE);
      }
      $organization->save();
      return $organization;
    }

    if (!$create || $nameIds !== []) {
      // Multiple/suspicious same-name organizations require manual review.
      return NULL;
    }

    $organization = $storage->create([
      'type' => self::BUNDLE,
      'title' => $supplierName,
      'status' => 1,
      self::TYPE_FIELD => self::SUPPLIER_TYPE,
      self::MONEYBIRD_FIELD => $contactId,
    ]);
    $organization->save();

    return $organization instanceof NodeInterface ? $organization : NULL;
  }

}
