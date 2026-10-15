<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use JsonException;
use Throwable;

/** Transport adapter: no Drupal or global request state. */
final class WebsiteIntakeHttpEndpoint {

  public const PATH = '/brebo-internal/intake/europakozijn';

  public function __construct(
    private readonly WebsiteIntakeSignatureVerifier $signatures,
    private readonly WebsiteIntakeHandler $handler,
  ) {}

  /**
   * @param array<string,string> $headers
   * @return array{status:int,body:array<string,mixed>}
   */
  public function dispatch(string $method, string $path, string $rawBody, array $headers, int $now): array {
    if ($method !== 'POST') {
      return ['status' => 405, 'body' => ['status' => 'error', 'error' => ['code' => 'method_not_allowed']]];
    }
    if ($path !== self::PATH) {
      return ['status' => 404, 'body' => ['status' => 'error', 'error' => ['code' => 'not_found']]];
    }
    if ($rawBody === '' || strlen($rawBody) > 64000) {
      return ['status' => 400, 'body' => ['status' => 'error', 'error' => ['code' => 'invalid_request']]];
    }
    try {
      $this->signatures->verify($method, $path, $rawBody, $headers, $now);
    }
    catch (Throwable) {
      return ['status' => 401, 'body' => ['status' => 'error', 'error' => ['code' => 'invalid_signature']]];
    }
    try {
      $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($payload)) {
        throw new JsonException('Expected JSON object.');
      }
      $result = $this->handler->handle($payload);
      return ['status' => 202, 'body' => [
        'status' => 'review_required',
        'request_id' => $payload['request_id'],
        'opportunity_id' => $result['opportunity_id'],
        'duplicate' => $result['duplicate'],
      ]];
    }
    catch (\InvalidArgumentException|JsonException) {
      return ['status' => 422, 'body' => ['status' => 'error', 'error' => ['code' => 'invalid_payload']]];
    }
    catch (Throwable) {
      return ['status' => 503, 'body' => ['status' => 'error', 'error' => ['code' => 'intake_unavailable']]];
    }
  }
}
