<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

/**
 * Extracts non-canonical text evidence from a canonical intake document.
 *
 * Providers may use a local runtime, a BREBO-managed extraction service, or
 * another explicitly configured implementation. They never own the original
 * document and must not mutate intake identity or attachment provenance.
 */
interface DocumentTextExtractionProviderInterface {

  /**
   * Returns whether this provider can attempt the requested document.
   */
  public function supports(string $mimeType): bool;

  /**
   * Extracts text from document bytes.
   *
   * @return array{
   *   status: string,
   *   text: string,
   *   extractor: string,
   *   confidence: float,
   *   metadata?: array<string, mixed>
   * }
   *   A source-neutral extraction result. Text is evidence only and never
   *   canonical truth. Empty text is valid for an unavailable/failed/no-text
   *   status.
   */
  public function extract(string $document, string $mimeType, string $filename = ''): array;

}
