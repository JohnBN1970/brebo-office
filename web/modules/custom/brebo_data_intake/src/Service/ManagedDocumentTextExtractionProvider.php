<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\brebo_data_intake\Contract\DocumentTextExtractionProviderInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/** Calls an explicitly configured BREBO-managed document extraction runtime. */
final class ManagedDocumentTextExtractionProvider implements DocumentTextExtractionProviderInterface {

  private const SUPPORTED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/heic',
    'image/heif',
  ];

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly string $endpoint = '',
    private readonly string $token = '',
  ) {}

  public function supports(string $mimeType): bool {
    return $this->isConfigured() && in_array(strtolower(trim($mimeType)), self::SUPPORTED_MIME_TYPES, TRUE);
  }

  public function extract(string $document, string $mimeType, string $filename = ''): array {
    $mimeType = strtolower(trim($mimeType));
    if (!$this->supports($mimeType)) {
      return $this->result('unavailable');
    }

    $endpoint = $this->configuredEndpoint();
    $token = $this->configuredToken();

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Accept' => 'application/json',
          'Authorization' => 'Bearer ' . $token,
          'X-BREBO-Extraction-Contract' => 'v1',
        ],
        'multipart' => [
          [
            'name' => 'document',
            'contents' => $document,
            'filename' => $filename !== '' ? $filename : 'document',
            'headers' => ['Content-Type' => $mimeType],
          ],
        ],
        'connect_timeout' => 5.0,
        'timeout' => 30.0,
        'http_errors' => FALSE,
      ]);
    }
    catch (GuzzleException) {
      return $this->result('provider_unreachable');
    }

    if ($response->getStatusCode() !== 200) {
      return $this->result('provider_error', ['provider_status' => $response->getStatusCode()]);
    }

    $decoded = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($decoded)) {
      return $this->result('invalid_provider_response');
    }

    $text = trim((string) ($decoded['text'] ?? ''));
    $status = (string) ($decoded['status'] ?? ($text !== '' ? 'extracted' : 'no_text'));
    if ($status === 'extracted' && $text === '') {
      return $this->result('invalid_provider_response');
    }

    $confidence = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
      ? max(0.0, min(1.0, (float) $decoded['confidence']))
      : ($text !== '' ? 0.8 : 0.0);
    $extractor = trim((string) ($decoded['extractor'] ?? 'brebo_managed_extraction_v1'));

    return [
      'status' => $status,
      'text' => $text,
      'extractor' => $extractor !== '' ? $extractor : 'brebo_managed_extraction_v1',
      'confidence' => $confidence,
      'metadata' => [
        'provider' => 'brebo_managed',
        'contract' => 'v1',
      ],
    ];
  }

  private function isConfigured(): bool {
    return str_starts_with($this->configuredEndpoint(), 'https://') && $this->configuredToken() !== '';
  }

  private function configuredEndpoint(): string {
    if (trim($this->endpoint) !== '') {
      return trim($this->endpoint);
    }

    $setting = trim((string) Settings::get('brebo_document_extraction_endpoint', ''));
    if ($setting !== '') {
      return $setting;
    }

    return trim((string) (getenv('BREBO_DOCUMENT_EXTRACTION_ENDPOINT') ?: ''));
  }

  private function configuredToken(): string {
    if (trim($this->token) !== '') {
      return trim($this->token);
    }

    $setting = trim((string) Settings::get('brebo_document_extraction_token', ''));
    if ($setting !== '') {
      return $setting;
    }

    return trim((string) (getenv('DOCUMENT_EXTRACTION_TOKEN') ?: ''));
  }

  /**
   * @param array<string, mixed> $metadata
   */
  private function result(string $status, array $metadata = []): array {
    return [
      'status' => $status,
      'text' => '',
      'extractor' => 'brebo_managed_extraction_v1',
      'confidence' => 0.0,
      'metadata' => ['provider' => 'brebo_managed', 'contract' => 'v1'] + $metadata,
    ];
  }

}
