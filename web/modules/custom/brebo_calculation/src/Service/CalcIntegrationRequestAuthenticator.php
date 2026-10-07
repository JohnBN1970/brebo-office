<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalcIntegrationRuntimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Verifies signed server-to-server requests from BREBO Calc. */
final class CalcIntegrationRequestAuthenticator {

  public function __construct(
    private readonly CalcIntegrationRuntimeInterface $runtime,
  ) {}

  public function assertSigned(Request $request, string $body = ''): void {
    $secret = $this->runtime->sharedSecret();
    $timestamp = trim((string) $request->headers->get('X-BREBO-Timestamp', ''));
    $requestId = trim((string) $request->headers->get('X-BREBO-Request-Id', ''));
    $signature = trim((string) $request->headers->get('X-BREBO-Signature', ''));

    if ($secret === '' || !ctype_digit($timestamp) || !preg_match('/^[0-9a-fA-F-]{36}$/', $requestId) || !str_starts_with($signature, 'v1=')) {
      throw new AccessDeniedHttpException('Invalid Calc authentication.');
    }

    $now = time();
    if (abs($now - (int) $timestamp) > 300) {
      throw new AccessDeniedHttpException('Expired request.');
    }

    $replayKey = 'brebo_calc_v2_request:' . hash('sha256', $requestId);
    if ($this->runtime->has($replayKey)) {
      throw new AccessDeniedHttpException('Replayed request.');
    }

    $canonical = $request->getMethod()
      . "\n" . $request->getRequestUri()
      . "\n" . hash('sha256', $body)
      . "\n" . $timestamp
      . "\n" . $requestId;
    $expected = 'v1=' . hash_hmac('sha256', $canonical, $secret);
    if (!hash_equals($expected, $signature)) {
      throw new AccessDeniedHttpException('Invalid signature.');
    }

    $this->runtime->remember($replayKey, $now + 600);
  }

  public function claimLaunchNonce(string $nonce, int $expiresAt): void {
    $now = time();
    if (!preg_match('/^[0-9a-f]{32}$/i', $nonce) || $expiresAt < $now || $expiresAt > $now + 180) {
      throw new AccessDeniedHttpException('Expired or malformed launch nonce.');
    }

    $key = 'brebo_calc_launch_nonce:' . hash('sha256', strtolower($nonce));
    if ($this->runtime->has($key)) {
      throw new AccessDeniedHttpException('Launch token already consumed.');
    }

    $this->runtime->remember($key, $expiresAt + 60);
  }

}
