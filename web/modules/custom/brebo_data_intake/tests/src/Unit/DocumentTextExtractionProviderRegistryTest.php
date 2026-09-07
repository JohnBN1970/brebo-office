<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\brebo_data_intake\Contract\DocumentTextExtractionProviderInterface;
use Drupal\brebo_data_intake\Service\DocumentTextExtractionProviderRegistry;
use PHPUnit\Framework\TestCase;

final class DocumentTextExtractionProviderRegistryTest extends TestCase {

  public function testSelectsFirstSupportingProvider(): void {
    $first = $this->provider(['application/pdf']);
    $second = $this->provider(['application/pdf', 'image/png']);
    $registry = new DocumentTextExtractionProviderRegistry([$first, $second]);

    self::assertSame($first, $registry->providerFor(' Application/PDF '));
    self::assertSame($second, $registry->providerFor('image/png'));
    self::assertNull($registry->providerFor('text/plain'));
  }

  public function testRejectsInvalidTaggedService(): void {
    $registry = new DocumentTextExtractionProviderRegistry([new \stdClass()]);

    $this->expectException(\LogicException::class);
    $registry->providerFor('application/pdf');
  }

  /**
   * @param string[] $mimeTypes
   */
  private function provider(array $mimeTypes): DocumentTextExtractionProviderInterface {
    return new class($mimeTypes) implements DocumentTextExtractionProviderInterface {
      public function __construct(private readonly array $mimeTypes) {}

      public function supports(string $mimeType): bool {
        return in_array($mimeType, $this->mimeTypes, TRUE);
      }

      public function extract(string $document, string $mimeType, string $filename = ''): array {
        return [
          'status' => 'extracted',
          'text' => 'fixture',
          'extractor' => 'fixture_v1',
          'confidence' => 1.0,
        ];
      }
    };
  }

}
