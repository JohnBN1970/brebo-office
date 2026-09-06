<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_finance\Kernel;

use Drupal\brebo_data_intake\ValueObject\IntakeDestinationResult;
use Drupal\brebo_finance\Service\PurchaseInvoiceIntakeDestination;
use Drupal\KernelTests\KernelTestBase;

/** Covers replay-safe source-neutral purchase-invoice intake. */
final class PurchaseInvoiceIntakeDestinationTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'node',
    'brebo_data_intake',
    'brebo_finance',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('brebo_finance', ['brebo_finance_purchase_invoice']);
  }

  public function testRepeatedSourceRecordReturnsDuplicate(): void {
    $destination = new PurchaseInvoiceIntakeDestination(
      $this->container->get('database'),
      $this->container->get('entity_type.manager'),
    );

    $envelope = [
      'source' => 'upload',
      'source_record_id' => 'invoice-upload-123',
      'classification' => 'purchase_invoice',
      'canonical' => ['supplier_ref' => 'supplier-1'],
      'payload' => [
        'supplier_ref' => 'supplier-1',
        'supplier_name' => 'Supplier 1',
        'invoice_number' => 'INV-123',
        'invoice_date' => '2026-09-06',
        'amount_ex_vat' => 100,
        'vat_amount' => 21,
        'amount_inc_vat' => 121,
      ],
    ];

    $first = $destination->route($envelope);
    self::assertSame(IntakeDestinationResult::CREATED, $first->state);

    $second = $destination->route($envelope);
    self::assertSame(IntakeDestinationResult::DUPLICATE, $second->state);
    self::assertSame('source_replay', $second->context['duplicate_reason'] ?? NULL);
    self::assertSame($first->context['invoice_id'] ?? NULL, $second->context['invoice_id'] ?? NULL);
  }

}
