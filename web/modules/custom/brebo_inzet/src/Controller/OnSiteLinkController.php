<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\OnSiteDeviceRegistry;
use Drupal\brebo_inzet\Service\OnSiteOtpManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OnSiteLinkController extends ControllerBase {

  public function __construct(
    private readonly OnSiteOtpManager $otpManager,
    private readonly OnSiteDeviceRegistry $deviceRegistry,
    private readonly EntityTypeManagerInterface $userEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.onsite_otp_manager'),
      $container->get('brebo_inzet.onsite_device_registry'),
      $container->get('entity_type.manager'),
    );
  }

  public function requestCode(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    $mobile = is_array($payload) ? (string) ($payload['mobile'] ?? '') : '';

    try {
      $challenge = $this->otpManager->request($mobile, (string) ($request->getClientIp() ?? 'unknown'));
      return new JsonResponse([
        'ok' => TRUE,
        'challenge_id' => $challenge['challenge_id'],
        'expires_in' => $challenge['expires_in'],
      ]);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_mobile'], 400);
    }
    catch (\RuntimeException $e) {
      // Fail closed when rate limited or SMS delivery is unavailable.
      return new JsonResponse(['ok' => FALSE, 'error' => 'verification_unavailable'], 503);
    }
  }

  public function verifyCode(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    $challengeId = is_array($payload) ? (string) ($payload['challenge_id'] ?? '') : '';
    $code = is_array($payload) ? (string) ($payload['code'] ?? '') : '';

    $verified = $this->otpManager->verify($challengeId, $code);
    if ($verified === NULL) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_or_expired_code'], 400);
    }

    $user = $this->userEntityTypeManager->getStorage('user')->load($verified['uid']);
    if ($user === NULL || !$user->isActive()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'user_unavailable'], 403);
    }

    $deviceToken = $this->deviceRegistry->issue((int) $user->id());

    return new JsonResponse([
      'ok' => TRUE,
      'device_token' => $deviceToken,
      'employee' => [
        'id' => (string) $user->id(),
        'display_name' => $user->getDisplayName(),
        'language' => $verified['language'],
      ],
    ]);
  }

}
