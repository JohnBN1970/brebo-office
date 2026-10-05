<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\GatewayRequest;
use Brebo\MailGateway\Security\GatewayRequestVerifier;
use JsonException;
use Throwable;

final class GatewayRequestRouter {

  public function __construct(
    private readonly GatewayApiService $api,
    private readonly GatewayRequestVerifier $verifier,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function dispatch(GatewayRequest $request, int $now): array {
    if (!$this->verifier->verify($request, $now)) {
      return ['status' => 401, 'body' => ['error' => 'unauthorized']];
    }

    try {
      if ($request->method === 'GET' && $request->path === '/v1/health') {
        return ['status' => 200, 'body' => $this->api->health()];
      }

      $payload = $request->body === ''
        ? []
        : json_decode($request->body, TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($payload)) {
        $payload = [];
      }

      if ($request->method === 'POST' && $request->path === '/v1/domains') {
        return ['status' => 200, 'body' => $this->api->provisionDomain($payload)];
      }
      if ($request->method === 'POST' && $request->path === '/v1/mailboxes') {
        return ['status' => 200, 'body' => $this->api->provisionMailbox($payload)];
      }
      if ($request->method === 'POST' && $request->path === '/v1/aliases') {
        return ['status' => 200, 'body' => $this->api->provisionAlias(
          (string) ($payload['alias'] ?? ''),
          (string) ($payload['target'] ?? ''),
        )];
      }

      return ['status' => 404, 'body' => ['error' => 'not_found']];
    }
    catch (JsonException) {
      return ['status' => 400, 'body' => ['error' => 'invalid_json']];
    }
    catch (Throwable $e) {
      return ['status' => 422, 'body' => ['error' => 'provisioning_failed', 'message' => $e->getMessage()]];
    }
  }
}
