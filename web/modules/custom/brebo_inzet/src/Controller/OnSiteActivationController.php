<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\OnSiteActivationManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OnSiteActivationController extends ControllerBase {

  public function __construct(
    private readonly OnSiteActivationManager $activationManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_inzet.onsite_activation_manager'));
  }

  public function activate(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    $token = is_array($payload) ? (string) ($payload['activation_token'] ?? '') : '';
    $deviceToken = $this->activationManager->activate($token);
    if ($deviceToken === NULL) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_or_expired_activation'], 400);
    }

    return new JsonResponse([
      'ok' => TRUE,
      'device_token' => $deviceToken,
    ]);
  }

}
