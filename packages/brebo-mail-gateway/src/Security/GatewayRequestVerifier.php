<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Security;

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\MailGateway\Domain\GatewayRequest;

final class GatewayRequestVerifier {

  public function __construct(
    private readonly string $keyId,
    private readonly string $secret,
    private readonly int $maxClockSkewSeconds = 300,
  ) {}

  public function verify(GatewayRequest $request, int $now): bool {
    $receivedKeyId = $request->header('X-Brebo-Key-Id');
    $receivedTimestamp = $request->header('X-Brebo-Timestamp');
    $receivedSignature = $request->header('X-Brebo-Signature');

    if ($receivedKeyId === '' || $receivedTimestamp === '' || $receivedSignature === '') {
      return FALSE;
    }
    if (!hash_equals($this->keyId, $receivedKeyId) || !ctype_digit($receivedTimestamp)) {
      return FALSE;
    }

    $timestamp = (int) $receivedTimestamp;
    if (abs($now - $timestamp) > $this->maxClockSkewSeconds) {
      return FALSE;
    }

    $expected = GatewayRequestSignature::sign(
      $this->keyId,
      $this->secret,
      $timestamp,
      $request->method,
      $request->path,
      $request->body,
    );

    return hash_equals($expected->signature, $receivedSignature);
  }
}
