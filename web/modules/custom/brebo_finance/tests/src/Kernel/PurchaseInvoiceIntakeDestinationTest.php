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
    $this->installSchema('brebo_finance', ['brebo_finance_purchase_invoice', 'brebo_finance_purchase_invoice_line']);
  }

  public function testRepeatedSourceRecordReturnsDuplicate(): void {
    $destination = $this->destination();
    $envelope = $this->envelope();

    $first = $destination->route($envelope);
    self::assertSame(IntakeDestinationResult::CREATED, $first->state);

    $second = $destination->route($envelope);
    self::assertSame(IntakeDestinationResult::DUPLICATE, $second->state);
    self::assertSame('source_replay', $second->context['duplicate_reason'] ?? NULL);
    self::assertSame($first->context['invoice_id'] ?? NULL, $second->context['invoice_id'] ?? NULL);
  }

  public function testNormalizedLinesArePersistedWithInvoice(): void {
    $envelope = $this->envelope();
    $envelope['payload']['lines'] = [
      [
        'line_number' => 1,
        'description' => 'Aluminium profiel 70 mm',
        'quantity' => 2,
        'unit' => 'st',
        'unit_price_ex_vat' => 25,
        'amount_ex_vat' => 50,
        'vat_code' => 'NL_21',
        'vat_rate' => 21,
        'vat_amount' => 10.50,
        'amount_inc_vat' => 60.50,
      ],
      [
        'line_number' => 2,
        'description' => 'Montagemateriaal',
        'quantity' => 1,
        'unit' => 'set',
        'unit_price_ex_vat' => 50,
        'amount_ex_vat' => 50,
        'vat_code' => 'NL_21',
        'vat_rate' => 21,
        'vat_amount' => 10.50,
        'amount_inc_vat' => 60.50,
      ],
    ];

    $result = $this->destination()->route($envelope);
    self::assertSame(IntakeDestinationResult::CREATED, $result->state);
    self::assertSame(2, $result->context['line_count'] ?? NULL);

    $rows = $this->container->get('database')->select('brebo_finance_purchase_invoice_line', 'l')
      ->fields('l')
      ->condition('invoice_id', (int) $result->context['invoice_id'])
      ->orderBy('line_number')
      ->execute()
      ->fetchAllAssoc('line_number');
    self::assertCount(2, $rows);
    self::assertSame('Aluminium profiel 70 mm', $rows[1]->description);
    self::assertSame('st', $rows[1]->unit);
    self::assertSame('50.0000', $rows[1]->amount_ex_vat);
    self::assertSame('unmatched', $rows[1]->match_status);
  }

  public function testLineTotalMismatchRequiresReviewWithoutPersistingInvoice(): void {
    $envelope = $this->envelope();
    $envelope['payload']['lines'] = [[
      'line_number' => 1,
      'description' => 'Onvolledige extractie',
      'quantity' => 1,
      'unit_price_ex_vat' => 50,
      'amount_ex_vat' => 50,
      'vat_rate' => 21,
      'vat_amount' => 10.50,
      'amount_inc_vat' => 60.50,
    ]];

    $result = $this->destination()->route($envelope);
    self::assertSame(IntakeDestinationResult::REVIEW_REQUIRED, $result->state);
    self::assertSame('purchase_invoice_line_totals_not_balanced', $result->reason);
    self::assertSame(0, (int) $this->container->get('database')->select('brebo_finance_purchase_invoice', 'i')->countQuery()->execute()->fetchField());
  }

  private function destination(): PurchaseInvoiceIntakeDestination {
    return new PurchaseInvoiceIntakeDestination(
      $this->container->get('database'),
      $this->container->get('entity_type.manager'),
    );
  }

  private function envelope(): array {
    return [
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
  }

}
