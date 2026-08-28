<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Persists the authenticated user's BREBO Office appearance preference.
 */
final class AppearancePreferenceController extends ControllerBase {

  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly AccountProxyInterface $currentAccount,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('user.data'),
      $container->get('current_user'),
    );
  }

  public function save(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    $mode = is_array($payload) ? ($payload['mode'] ?? NULL) : NULL;

    if (!in_array($mode, ['day', 'night', 'system'], TRUE)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid appearance mode.'], 400);
    }

    $uid = (int) $this->currentAccount->id();
    if ($uid <= 0) {
      return new JsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 403);
    }

    $this->userData->set('brebo_office_core', $uid, 'appearance_mode', $mode);

    return new JsonResponse(['status' => 'ok', 'mode' => $mode]);
  }

}
