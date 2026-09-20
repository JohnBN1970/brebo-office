<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Service\ManagedDocumentTextExtractionProvider;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ManagedDocumentTextExtractionProviderTest extends TestCase {

  public function testUnconfiguredProviderDoesNotSupportDocuments(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects(self::never())->method('request');
    $provider = new ManagedDocumentTextExtractionProvider($client);

    self::assertFalse($provider->supports('application/pdf'));
    self::assertSame('unavailable', $provider->extract('pdf', 'application/pdf')['status']);
  }

  public function testConfiguredProviderRejectsUnsupportedTiffWithoutCallingRuntime(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects(self::never())->method('request');
    $provider = new ManagedDocumentTextExtractionProvider($client, 'https://extract.example.test/v1/documents', 'secret');

    self::assertFalse($provider->supports('image/tiff'));
    self::assertSame('unavailable', $provider->extract('bytes', 'image/tiff')['status']);
  }

  public function testReturnsManagedEvidenceWithoutMakingItCanonical(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects(self::once())
      ->method('request')
      ->with('POST', 'https://extract.example.test/v1/documents', self::callback(static function (array $options): bool {
        return ($options['headers']['Authorization'] ?? '') === 'Bearer secret'
          && ($options['headers']['X-BREBO-Extraction-Contract'] ?? '') === 'v1'
          && ($options['multipart'][0]['contents'] ?? '') === 'bytes';
      }))
      ->willReturn(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'status' => 'extracted',
        'text' => "Factuur 123\nTotaal 121,00",
        'extractor' => 'brebo_ocr_v1',
        'confidence' => 0.93,
      ], JSON_THROW_ON_ERROR)));

    $provider = new ManagedDocumentTextExtractionProvider($client, 'https://extract.example.test/v1/documents', 'secret');
    $result = $provider->extract('bytes', 'application/pdf', 'invoice.pdf');

    self::assertSame('extracted', $result['status']);
    self::assertSame('brebo_ocr_v1', $result['extractor']);
    self::assertSame(0.93, $result['confidence']);
    self::assertSame('brebo_managed', $result['metadata']['provider']);
  }

  public function testProviderFailureReturnsEvidenceStatusInsteadOfThrowing(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willReturn($this->response(503));
    $provider = new ManagedDocumentTextExtractionProvider($client, 'https://extract.example.test/v1/documents', 'secret');

    $result = $provider->extract('bytes', 'image/png');
    self::assertSame('provider_error', $result['status']);
    self::assertSame(503, $result['metadata']['provider_status']);
  }

  private function response(int $status): ResponseInterface {
    return new Response($status, ['Content-Type' => 'application/json'], '{}');
  }

}
