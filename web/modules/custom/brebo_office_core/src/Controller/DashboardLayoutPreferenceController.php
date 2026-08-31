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
 * Loads and persists the authenticated user's dashboard layout preference.
 */
final class DashboardLayoutPreferenceController extends ControllerBase {

  private const VERSION = 1;
  private const MAX_ITEMS = 64;

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

  public function load(): JsonResponse {
    $uid = (int) $this->currentAccount->id();
    if ($uid <= 0) {
      return new JsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 403);
    }

    $stored = $this->userData->get('brebo_office_core', $uid, 'dashboard_layout');
    $layout = is_array($stored) ? $this->normalize($stored) : $this->defaults();

    return new JsonResponse($layout, 200, [
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
  }

  public function save(Request $request): JsonResponse {
    $uid = (int) $this->currentAccount->id();
    if ($uid <= 0) {
      return new JsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 403);
    }

    if (strlen($request->getContent()) > 16384) {
      return new JsonResponse(['status' => 'error', 'message' => 'Dashboard layout payload is too large.'], 413);
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid dashboard layout.'], 400);
    }

    if (!empty($payload['reset'])) {
      $this->userData->delete('brebo_office_core', $uid, 'dashboard_layout');
      return new JsonResponse(['status' => 'ok', 'layout' => $this->defaults()]);
    }

    $layout = $this->normalize($payload);
    $this->userData->set('brebo_office_core', $uid, 'dashboard_layout', $layout);

    return new JsonResponse(['status' => 'ok', 'layout' => $layout]);
  }

  private function defaults(): array {
    return [
      'version' => self::VERSION,
      'density' => 'normal',
      'order' => [],
      'hidden' => [],
      'collapsed' => [],
    ];
  }

  private function normalize(array $layout): array {
    return [
      'version' => self::VERSION,
      'density' => ($layout['density'] ?? 'normal') === 'compact' ? 'compact' : 'normal',
      'order' => $this->normalizeIds($layout['order'] ?? []),
      'hidden' => $this->normalizeIds($layout['hidden'] ?? []),
      'collapsed' => $this->normalizeIds($layout['collapsed'] ?? []),
    ];
  }

  private function normalizeIds(mixed $values): array {
    if (!is_array($values)) {
      return [];
    }

    $ids = [];
    foreach (array_slice($values, 0, self::MAX_ITEMS) as $value) {
      if (!is_string($value) || !preg_match('/^[a-z0-9_-]{1,64}$/', $value)) {
        continue;
      }
      $ids[$value] = $value;
    }

    return array_values($ids);
  }

}
