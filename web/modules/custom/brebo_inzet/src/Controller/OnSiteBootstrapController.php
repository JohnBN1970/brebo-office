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
    private readonly EntityTypeManagerInterface $userEntityTypeManager,
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

    $user = $this->userEntityTypeManager->getStorage('user')->load($uid);
    if ($user === NULL || !$user->isActive()) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'device_not_linked'], 401);
    }

    return new JsonResponse([
      'ok' => TRUE,
      'employee' => [
        'id' => (string) $user->id(),
        'employee_number' => $this->fieldValue($user, 'field_brebo_employee_number'),
        'display_name' => $user->getDisplayName(),
        'first_name' => $this->fieldValue($user, 'field_brebo_first_name'),
        'last_name' => $this->fieldValue($user, 'field_brebo_last_name'),
        'mobile' => $this->fieldValue($user, 'field_brebo_mobile'),
        'job_title' => $this->fieldValue($user, 'field_brebo_job_title'),
        'skills' => $this->fieldValues($user, 'field_brebo_skills'),
        'workforce_status' => $this->fieldValue($user, 'field_brebo_workforce_status') ?: 'active',
        'language' => $this->identityResolver->languageFor($user),
      ],
      'projects' => $this->assignmentProvider->currentForUser((int) $user->id()),
      'clock_locations' => $this->assignmentProvider->clockLocations(),
      'location_policy' => [
        'mode' => 'clock_action_only',
        'background_tracking' => FALSE,
        'automatic_presence_events' => FALSE,
        'store_coordinates' => FALSE,
      ],
    ]);
  }

  private function fieldValue(object $user, string $fieldName): string {
    if (!method_exists($user, 'hasField') || !$user->hasField($fieldName) || $user->get($fieldName)->isEmpty()) {
      return '';
    }
    return (string) ($user->get($fieldName)->value ?? '');
  }

  /**
   * @return list<string>
   */
  private function fieldValues(object $user, string $fieldName): array {
    if (!method_exists($user, 'hasField') || !$user->hasField($fieldName) || $user->get($fieldName)->isEmpty()) {
      return [];
    }
    $values = [];
    foreach ($user->get($fieldName) as $item) {
      $value = trim((string) ($item->value ?? ''));
      if ($value !== '') {
        $values[] = $value;
      }
    }
    return $values;
  }

  private function bearerToken(Request $request): ?string {
    $authorization = trim((string) $request->headers->get('Authorization', ''));
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
      return NULL;
    }
    return trim($matches[1]);
  }

}
