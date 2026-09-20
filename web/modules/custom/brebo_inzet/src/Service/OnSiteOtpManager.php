<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\PrivateKey;

final class OnSiteOtpManager {

  private const TTL = 600;
  private const MAX_ATTEMPTS = 5;

  public function __construct(
    private readonly OnSiteIdentityResolver $identityResolver,
    private readonly OnSiteSmsSenderInterface $smsSender,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirable,
    private readonly PrivateKey $privateKey,
    private readonly FloodInterface $flood,
  ) {}

  /**
   * Starts an OTP challenge without exposing whether a number exists.
   *
   * @return array{challenge_id: string, expires_in: int}
   */
  public function request(string $mobile, string $clientIp): array {
    $normalized = $this->identityResolver->normalizeMobile($mobile);
    if ($normalized === '') {
      throw new \InvalidArgumentException('Ongeldig mobiel nummer.');
    }

    $identifier = hash('sha256', $normalized . '|' . $clientIp);
    if (!$this->flood->isAllowed('brebo_onsite_otp_request', 5, 900, $identifier)) {
      throw new \RuntimeException('Te veel verificatieverzoeken. Probeer later opnieuw.');
    }
    $this->flood->register('brebo_onsite_otp_request', 900, $identifier);

    $challengeId = bin2hex(random_bytes(24));
    $user = $this->identityResolver->resolveByMobile($normalized);

    // Unknown numbers receive an indistinguishable challenge response, but no SMS.
    if ($user === NULL) {
      $this->store()->setWithExpire($challengeId, [
        'valid' => FALSE,
        'attempts' => 0,
      ], self::TTL);
      return ['challenge_id' => $challengeId, 'expires_in' => self::TTL];
    }

    $code = (string) random_int(100000, 999999);
    $language = $this->identityResolver->languageFor($user);
    $this->store()->setWithExpire($challengeId, [
      'valid' => TRUE,
      'uid' => (int) $user->id(),
      'mobile' => $normalized,
      'language' => $language,
      'code_hash' => $this->hashCode($challengeId, $code),
      'attempts' => 0,
    ], self::TTL);

    $this->smsSender->sendVerificationCode($normalized, $code, $language);
    return ['challenge_id' => $challengeId, 'expires_in' => self::TTL];
  }

  /**
   * @return array{uid: int, language: string}|null
   */
  public function verify(string $challengeId, string $code): ?array {
    $challengeId = trim($challengeId);
    $code = trim($code);
    if ($challengeId === '' || preg_match('/^\d{6}$/', $code) !== 1) {
      return NULL;
    }

    $store = $this->store();
    $data = $store->get($challengeId);
    if (!is_array($data) || !($data['valid'] ?? FALSE)) {
      return NULL;
    }

    $attempts = (int) ($data['attempts'] ?? 0);
    if ($attempts >= self::MAX_ATTEMPTS) {
      $store->delete($challengeId);
      return NULL;
    }

    if (!hash_equals((string) ($data['code_hash'] ?? ''), $this->hashCode($challengeId, $code))) {
      $data['attempts'] = $attempts + 1;
      $store->setWithExpire($challengeId, $data, self::TTL);
      return NULL;
    }

    $store->delete($challengeId);
    return [
      'uid' => (int) $data['uid'],
      'language' => (string) ($data['language'] ?? 'nl'),
    ];
  }

  private function store() {
    return $this->keyValueExpirable->get('brebo_inzet.onsite_otp');
  }

  private function hashCode(string $challengeId, string $code): string {
    return hash_hmac('sha256', $challengeId . '|' . $code, $this->privateKey->get());
  }

}
