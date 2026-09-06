<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Contract\IntakeEnricherInterface;
use Drupal\brebo_data_intake\Service\IntakeEnrichmentDispatcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Covers the immutable source-evidence boundary of intake enrichment. */
final class IntakeEnrichmentDispatcherTest extends TestCase {

  public function testEnricherAddsNormalizedInvoicePayloadWithoutChangingEvidence(): void {
    $enricher = new class implements IntakeEnricherInterface {
      public function supports(array $envelope): bool {
        return ($envelope['classification'] ?? '') === 'purchase_invoice';
      }

      public function enrich(array $envelope): array {
        $envelope['payload']['invoice_number'] = 'INV-42';
        $envelope['payload']['lines'] = [['line_number' => 1, 'description' => 'Kozijnanker']];
        $envelope['payload']['extraction_provenance'] = ['provider' => 'test'];
        return $envelope;
      }
    };

    $dispatcher = new IntakeEnrichmentDispatcher([$enricher]);
    $envelope = $this->envelope();
    $result = $dispatcher->enrich($envelope);

    self::assertSame('INV-42', $result['payload']['invoice_number']);
    self::assertSame('Kozijnanker', $result['payload']['lines'][0]['description']);
    self::assertSame($envelope['source_record_id'], $result['source_record_id']);
    self::assertSame($envelope['attachments'], $result['attachments']);
  }

  public function testEnricherCannotReplaceOriginalAttachmentEvidence(): void {
    $enricher = new class implements IntakeEnricherInterface {
      public function supports(array $envelope): bool {
        return TRUE;
      }

      public function enrich(array $envelope): array {
        $envelope['attachments'] = [];
        return $envelope;
      }
    };

    $this->expectException(RuntimeException::class);
    (new IntakeEnrichmentDispatcher([$enricher]))->enrich($this->envelope());
  }

  private function envelope(): array {
    return [
      'source' => 'upload',
      'source_record_id' => str_repeat('a', 64),
      'classification' => 'purchase_invoice',
      'canonical' => [],
      'payload' => [],
      'attachments' => [[
        'file_id' => 12,
        'filename' => 'invoice.pdf',
        'uri' => 'private://brebo-intake/invoice.pdf',
        'content_sha256' => str_repeat('a', 64),
      ]],
      'received_at' => 1,
      'actor_uid' => 1,
    ];
  }

}
