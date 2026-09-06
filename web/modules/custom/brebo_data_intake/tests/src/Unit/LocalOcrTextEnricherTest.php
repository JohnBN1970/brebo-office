<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Service\LocalOcrTextEnricher;
use Drupal\brebo_data_intake\Service\PurchaseInvoiceTextEnricher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use PHPUnit\Framework\TestCase;

/** Covers source and MIME boundaries for local invoice OCR. */
final class LocalOcrTextEnricherTest extends TestCase {

  public function testSupportsCanonicalInvoicePdfAndImages(): void {
    $enricher = $this->enricher();

    self::assertTrue($enricher->supports($this->envelope('purchase_invoice', 'application/pdf')));
    self::assertTrue($enricher->supports($this->envelope('supplier_invoice', 'image/jpeg')));
    self::assertTrue($enricher->supports($this->envelope('purchase_invoice', 'image/png')));
  }

  public function testRejectsNonInvoicesUnsupportedMimeAndMissingFileIdentity(): void {
    $enricher = $this->enricher();

    self::assertFalse($enricher->supports($this->envelope('quotation', 'application/pdf')));
    self::assertFalse($enricher->supports($this->envelope('purchase_invoice', 'text/plain')));

    $envelope = $this->envelope('purchase_invoice', 'image/png');
    $envelope['attachments'][0]['file_id'] = 0;
    self::assertFalse($enricher->supports($envelope));
  }

  private function enricher(): LocalOcrTextEnricher {
    return new LocalOcrTextEnricher(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(FileSystemInterface::class),
      new PurchaseInvoiceTextEnricher(),
    );
  }

  private function envelope(string $classification, string $mime): array {
    return [
      'source' => 'upload',
      'source_record_id' => 'source-1',
      'classification' => $classification,
      'payload' => [],
      'attachments' => [[
        'file_id' => 12,
        'filename' => 'invoice.bin',
        'mime_type' => $mime,
        'content_sha256' => str_repeat('a', 64),
      ]],
    ];
  }

}
