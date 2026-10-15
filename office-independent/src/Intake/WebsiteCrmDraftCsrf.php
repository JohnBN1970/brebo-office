<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Stateless, short-lived CSRF token tied to the authenticated operator session
 * and specific CRM opportunity. The signing secret must remain server-side.
 */
final class WebsiteCrmDraftCsrf {

  public function __construct(private readonly string $secret) {
    if (strlen($secret) < 32) {
      throw new \InvalidArgumentException('CRM CSRF secret must be at least 32 characters.');
    }
  }

  public function issue(string $sessionId, int $opportunityId, int $timestamp): string {
    if ($sessionId === '' || $opportunityId < 1 || $timestamp < 1) {
      throw new \InvalidArgumentException('Invalid CRM CSRF context.');
    }
    $payload = $timestamp . ':' . $opportunityId;
    return $payload . ':' . hash_hmac('sha256', $sessionId . ':' . $payload, $this->secret);
  }

  public function verify(string $token, string $sessionId, int $opportunityId, int $now): bool {
    if ($sessionId === '' || $opportunityId < 1 || $now < 1 || strlen($token) > 200
      || !preg_match('/^([0-9]{1,12}):([0-9]{1,18}):([a-f0-9]{64})$/D', $token, $matches)) {
      return false;
    }
    $issued = (int) $matches[1];
    if ($issued > $now || $now - $issued > 900 || (int) $matches[2] !== $opportunityId) {
      return false;
    }
    return hash_equals($this->issue($sessionId, $opportunityId, $issued), $token);
  }
}
