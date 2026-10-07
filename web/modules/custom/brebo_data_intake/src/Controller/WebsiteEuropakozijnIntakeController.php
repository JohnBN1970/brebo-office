<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Controller;

use Drupal\brebo_data_intake\Service\SourceNeutralIntakeManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Receives authenticated Europakozijn website requests into central intake. */
final class WebsiteEuropakozijnIntakeController extends ControllerBase {

  public function __construct(private readonly SourceNeutralIntakeManager $intakeManager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_data_intake.source_neutral_intake_manager'));
  }

  public function ingest(Request $request): JsonResponse {
    $raw = $request->getContent();
    if (!$this->authenticated($request, $raw)) {
      return $this->error(401, 'invalid_signature');
    }

    try {
      $payload = json_decode($raw, TRUE, 128, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return $this->error(400, 'invalid_json');
    }

    if (!is_array($payload) || !$this->validPayload($payload)) {
      return $this->error(422, 'invalid_payload');
    }

    $requestId = (string) $payload['request_id'];
    $sourceId = (string) $payload['source'];
    $building = is_array($payload['observed']['building'] ?? NULL) ? $payload['observed']['building'] : [];
    $metadata = [
      'project_name' => trim((string) ($payload['selected']['project_name'] ?? $payload['observed']['project_name'] ?? '')),
      'address' => trim((string) ($building['address'] ?? $building['formatted_address'] ?? '')),
      'source_contract' => 'BREBO Intake API v1',
      'source_id' => $sourceId,
      'source_schema_version' => (string) $payload['schema_version'],
    ];

    $intake = $this->intakeManager->intake([
      'source' => 'website',
      'source_record_id' => $requestId,
      'classification' => 'website_project_request',
      'confidence' => 1.0,
      'canonical' => [],
      'payload' => [
        'request_id' => $requestId,
        'source' => $sourceId,
        'metadata' => array_filter($metadata, static fn(mixed $value): bool => $value !== ''),
        'website_request' => [
          'observed' => $payload['observed'],
          'detected' => $payload['detected'],
          'calculated' => $payload['calculated'],
          'selected' => $payload['selected'],
        ],
        'preliminary_scope' => is_array($payload['calculated']['preliminary_scope'] ?? NULL)
          ? $payload['calculated']['preliminary_scope']
          : [],
      ],
      'attachments' => [],
      'received_at' => time(),
      'actor_uid' => 0,
    ]);

    $destination = is_array($intake['destination'] ?? NULL) ? $intake['destination'] : [];
    return $this->response([
      'intake_id' => $requestId,
      'request_id' => $requestId,
      'state' => (string) ($intake['state'] ?? 'review_required'),
      'reference' => isset($destination['opportunity_id']) ? 'opportunity:' . (int) $destination['opportunity_id'] : NULL,
      'intake' => $intake,
    ], 202);
  }

  /** @param array<string,mixed> $payload */
  private function validPayload(array $payload): bool {
    if (($payload['schema_version'] ?? NULL) !== '1.0') {
      return FALSE;
    }
    if (!is_string($payload['request_id'] ?? NULL) || !preg_match('/^[0-9a-f-]{36}$/i', $payload['request_id'])) {
      return FALSE;
    }
    if (!is_string($payload['source'] ?? NULL) || !preg_match('/^brebo-platform\.[a-z0-9._-]+$/', $payload['source'])) {
      return FALSE;
    }
    foreach (['observed', 'detected', 'calculated', 'selected'] as $bucket) {
      if (!array_key_exists($bucket, $payload) || !is_array($payload[$bucket])) {
        return FALSE;
      }
    }
    if (!isset($payload['observed']['building']) || !is_array($payload['observed']['building'])) {
      return FALSE;
    }
    if (!isset($payload['observed']['rooms']) || !is_array($payload['observed']['rooms'])) {
      return FALSE;
    }
    if (!isset($payload['observed']['frames']) || !is_array($payload['observed']['frames']) || $payload['observed']['frames'] === []) {
      return FALSE;
    }
    return TRUE;
  }

  private function authenticated(Request $request, string $raw): bool {
    $secret = trim((string) Settings::get('brebo_shared_secret', getenv('BREBO_SHARED_SECRET') ?: ''));
    $timestamp = $request->headers->get('X-BREBO-Timestamp', '');
    $requestId = $request->headers->get('X-BREBO-Request-Id', '');
    $signatureHeader = $request->headers->get('X-BREBO-Signature', '');

    if ($secret === '' || !preg_match('/^[0-9]+$/', $timestamp) || !preg_match('/^[0-9a-f-]{36}$/i', $requestId) || !preg_match('/^v1=([a-f0-9]{64})$/', $signatureHeader, $match)) {
      return FALSE;
    }
    if (abs(time() - (int) $timestamp) > 300) {
      return FALSE;
    }

    $bodyHash = hash('sha256', $raw);
    $canonical = implode("\n", ['POST', $request->getPathInfo(), $bodyHash, $timestamp, $requestId]);
    return hash_equals(hash_hmac('sha256', $canonical, $secret), $match[1]);
  }

  private function response(array $payload, int $status): JsonResponse {
    $response = new JsonResponse(['status' => 'ok'] + $payload, $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

  private function error(int $status, string $code): JsonResponse {
    $response = new JsonResponse(['status' => 'error', 'error' => ['code' => $code]], $status);
    $response->headers->set('Cache-Control', 'private, no-store');
    return $response;
  }

}
