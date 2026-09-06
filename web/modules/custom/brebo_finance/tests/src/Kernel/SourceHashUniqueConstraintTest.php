<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Kernel;

use Drupal\KernelTests\KernelTestBase;

/** Covers the database-level replay uniqueness contract for purchase invoices. */
final class SourceHashUniqueConstraintTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'node',
    'brebo_finance',
  ];

  public function testSourceHashCanBeBackedByUniqueKey(): void {
    $this->installSchema('brebo_finance', ['brebo_finance_purchase_invoice']);
    $schema = $this->container->get('database')->schema();

    self::assertTrue($schema->fieldExists('brebo_finance_purchase_invoice', 'source_hash'));
    if (!$schema->indexExists('brebo_finance_purchase_invoice', 'source_hash_unique')) {
      $schema->addUniqueKey('brebo_finance_purchase_invoice', 'source_hash_unique', ['source_hash']);
    }
    self::assertTrue($schema->indexExists('brebo_finance_purchase_invoice', 'source_hash_unique'));
  }

}
