<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Controller;

use Drupal\brebo_inzet\Service\OnSiteAssignmentProvider;
use Drupal\brebo_inzet\Service\OnSiteDeviceRegistry;
use Drupal\brebo_inzet\Service\OnSiteIdentityResolver;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class OnSiteBootstrapController extends ControllerBase {

  public function __construct(
    private readonly OnSiteDeviceRegistry $deviceRegistry,
    private readonly OnSiteAssignmentProvider $assignmentProvider,
    private readonly OnSiteIdentityResolver $identityResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_inzet.onsite_device_registry'),
      $container->get('brebo_inzet.onsite_assignment_provider'),
      $container->get('brebo_inzet.onsite_identity_resolver'),
      $container->get('entity_type.manager'),
    );
  }

  public function bootstrap(Request $request): JsonResponse {
    $token = $this->bearerToken($request);
    $uid = $token === NULL ? NULL : $this->deviceRegistry->resolve($token);
    if ($uid === NULL) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'device_not_linked'], 401);
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user === NULL || !$user->isActive()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'device_not_linked'], 401);
    }

    return new JsonResponse([
      'ok' => TRUE,
      'employee' => [
        'id' => (string) $user->id(),
        'display_name' => $user->getDisplayName(),
        'language' => $this->identityResolver->languageFor($user),
      ],
      'projects' => $this->assignmentProvider->currentForUser((int) $user->id()),
    ]);
  }

  private function bearerToken(Request $request): ?string {
    $authorization = trim((string) $request->headers->get('Authorization', ''));
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
      return NULL;
    }
    return trim($matches[1]);
  }

}
