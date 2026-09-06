<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Service\PurchaseInvoiceTextEnricher;
use PHPUnit\Framework\TestCase;

/** Covers source-neutral invoice normalization from extracted attachment text. */
final class PurchaseInvoiceTextEnricherTest extends TestCase {

  public function testBalancedExtractedTextAddsHeaderAndLines(): void {
    $text = <<<'TXT'
Factuurnummer: INV-42
Factuurdatum: 06-09-2026
Vervaldatum: 20-09-2026
Totaal excl. btw: 100,00
BTW-bedrag: 21,00
Totaal incl. btw: 121,00
Aluminium profiel 70 mm 2 st 25,00 50,00 21% 60,50
Montagemateriaal 1 set 50,00 50,00 21% 60,50
TXT;

    $envelope = [
      'source' => 'upload',
      'source_record_id' => 'sha256-source',
      'classification' => 'purchase_invoice',
      'payload' => [],
      'attachments' => [[
        'file_id' => 10,
        'filename' => 'factuur.pdf',
        'mime_type' => 'application/pdf',
        'extracted_text' => $text,
      ]],
    ];

    $enricher = new PurchaseInvoiceTextEnricher();
    self::assertTrue($enricher->supports($envelope));
    $result = $enricher->enrich($envelope);

    self::assertSame('sha256-source', $result['source_record_id']);
    self::assertSame($envelope['attachments'], $result['attachments']);
    self::assertSame('INV-42', $result['payload']['invoice_number']);
    self::assertSame('2026-09-06', $result['payload']['invoice_date']);
    self::assertSame('2026-09-20', $result['payload']['due_date']);
    self::assertSame(100.0, $result['payload']['amount_ex_vat']);
    self::assertSame(21.0, $result['payload']['vat_amount']);
    self::assertSame(121.0, $result['payload']['amount_inc_vat']);
    self::assertCount(2, $result['payload']['lines']);
    self::assertSame('Aluminium profiel 70 mm', $result['payload']['lines'][0]['description']);
    self::assertSame(2.0, $result['payload']['lines'][0]['quantity']);
    self::assertSame(50.0, $result['payload']['lines'][0]['amount_ex_vat']);
    self::assertSame(10.5, $result['payload']['lines'][0]['vat_amount']);
    self::assertSame('brebo_purchase_invoice_text_v1', $result['payload']['extraction_provenance'][0]['extractor']);
  }

  public function testUnbalancedRowsAreNotPromotedToLines(): void {
    $envelope = [
      'source' => 'email',
      'source_record_id' => 'mail-1',
      'classification' => 'purchase_invoice',
      'payload' => [
        'amount_ex_vat' => 100,
        'vat_amount' => 21,
        'amount_inc_vat' => 121,
      ],
      'attachments' => [[
        'type' => 'mail_attachment_evidence',
        'evidence' => [
          'context_text' => "[Bijlage: factuur.pdf, pagina 1]\nVerkeerde regel 1 st 50,00 50,00 21% 60,50",
        ],
      ]],
    ];

    $result = (new PurchaseInvoiceTextEnricher())->enrich($envelope);
    self::assertArrayNotHasKey('lines', $result['payload']);
    self::assertSame([], $result['payload']['extraction_provenance'][0]['fields_added']);
  }

  public function testExistingStructuredValuesAreNeverOverwritten(): void {
    $envelope = [
      'source' => 'portal',
      'source_record_id' => 'portal-1',
      'classification' => 'supplier_invoice',
      'payload' => [
        'invoice_number' => 'CANONICAL-7',
        'lines' => [['line_number' => 1, 'description' => 'Bestaand']],
      ],
      'attachments' => [[
        'extracted_text' => "Factuurnummer: OCR-999\nArtikel 1 st 10,00 10,00 21% 12,10",
      ]],
    ];

    $result = (new PurchaseInvoiceTextEnricher())->enrich($envelope);
    self::assertSame('CANONICAL-7', $result['payload']['invoice_number']);
    self::assertSame([['line_number' => 1, 'description' => 'Bestaand']], $result['payload']['lines']);
  }

}
