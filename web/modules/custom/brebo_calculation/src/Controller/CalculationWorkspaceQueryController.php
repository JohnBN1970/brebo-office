<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalculationWorkspaceStateService;
use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Read/query endpoints for the BREBO calculation workspace. */
final class CalculationWorkspaceQueryController extends ControllerBase {

  public function __construct(
    private readonly CalculationWorkspaceStateService $workspaceStateService,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.workspace_state'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function state(Request $request, int $calculation): JsonResponse {
    try {
      $this->authenticator->assertSigned($request, '');
      $state = $this->workspaceStateService->state($calculation);
      return new JsonResponse($state, 200, ['Cache-Control' => 'no-store, private']);
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => 'invalid_request', 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'not_available', 'message' => $e->getMessage()], 404);
    }
  }

}
