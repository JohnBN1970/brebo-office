<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_office_core\Service\AdministrationAccessManager;
use Drupal\brebo_office_core\Service\OnboardingTourManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Persists guided onboarding progress for the current user. */
final class OnboardingTourController extends ControllerBase {

  public function __construct(
    private readonly OnboardingTourManager $tours,
    private readonly AdministrationAccessManager $access,
    private readonly CsrfTokenGenerator $csrf,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_office_core.onboarding_tour_manager'),
      $container->get('brebo_office_core.administration_access_manager'),
      $container->get('csrf_token'),
    );
  }

  public function progress(Request $request): JsonResponse {
    if ($this->currentUser()->isAnonymous()) {
      throw new AccessDeniedHttpException();
    }
    $token = (string) $request->query->get('token', '');
    if (!$this->csrf->validate($token, 'brebo_guided_tour')) {
      throw new AccessDeniedHttpException('Invalid CSRF token.');
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'error' => 'invalid_payload'], 400);
    }
    $tourId = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($payload['tour_id'] ?? '')));
    $action = (string) ($payload['action'] ?? '');
    $step = max(0, (int) ($payload['step'] ?? 0));
    if ($tourId === '') {
      return new JsonResponse(['ok' => FALSE, 'error' => 'missing_tour'], 400);
    }

    $user = User::load((int) $this->currentUser()->id());
    if ($user === NULL) {
      throw new AccessDeniedHttpException();
    }

    match ($action) {
      'start' => $this->tours->start($user, $tourId, $step),
      'advance' => $this->tours->advance($user, $tourId, $step),
      'complete' => $this->tours->complete($user, $tourId),
      'skip' => $this->tours->skip($user, $tourId, $step),
      default => NULL,
    };

    if ($tourId === 'welcome_core' && $action === 'complete') {
      $this->access->completeOnboarding($user, (int) $this->currentUser()->id());
    }

    return new JsonResponse([
      'ok' => TRUE,
      'status' => $this->tours->status($user, $tourId),
      'onboarding_completed' => $tourId === 'welcome_core' && $action === 'complete',
    ]);
  }

}
