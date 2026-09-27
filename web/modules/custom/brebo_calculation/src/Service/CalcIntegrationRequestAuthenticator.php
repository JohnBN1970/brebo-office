<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Verifies signed server-to-server requests from BREBO Calc. */
final class CalcIntegrationRequestAuthenticator {

  public function __construct(
    private readonly CacheBackendInterface $cache,
  ) {}

  public function assertSigned(Request $request, string $body = ''): void {
    $secret = trim((string) getenv('BREBO_CALC_SHARED_SECRET') ?: Settings::get('brebo_calc_shared_secret', ''));
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
    if ($this->cache->get($replayKey)) {
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

    $this->cache->set($replayKey, TRUE, $now + 600);
  }

}
