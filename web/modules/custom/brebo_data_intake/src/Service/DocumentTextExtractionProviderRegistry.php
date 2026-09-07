<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\DocumentTextExtractionProviderInterface;

/** Selects the first configured provider that supports a document MIME type. */
final class DocumentTextExtractionProviderRegistry {

  /**
   * @param iterable<\Drupal\brebo_data_intake\Contract\DocumentTextExtractionProviderInterface> $providers
   */
  public function __construct(
    private readonly iterable $providers,
  ) {}

  public function providerFor(string $mimeType): ?DocumentTextExtractionProviderInterface {
    $mimeType = strtolower(trim($mimeType));
    foreach ($this->providers as $provider) {
      if (!$provider instanceof DocumentTextExtractionProviderInterface) {
        throw new \LogicException('Every document text extraction provider must implement DocumentTextExtractionProviderInterface.');
      }
      if ($provider->supports($mimeType)) {
        return $provider;
      }
    }
    return NULL;
  }

}
