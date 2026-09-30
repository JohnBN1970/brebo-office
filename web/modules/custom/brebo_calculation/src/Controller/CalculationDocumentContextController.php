<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Drupal\brebo_calculation\Service\CalculationDocumentContextService;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Exposes Office-owned document context to the external Calc workbench. */
final class CalculationDocumentContextController extends ControllerBase {

  public function __construct(
    private readonly CalculationDocumentContextService $context,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.document_context'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function snapshot(Request $request, int $calculation): JsonResponse {
    $this->authenticator->assertSigned($request, '');

    try {
      return new JsonResponse(
        $this->context->snapshot($calculation),
        200,
        ['Cache-Control' => 'no-store, private'],
      );
    }
    catch (\InvalidArgumentException $e) {
      return new JsonResponse(['error' => 'invalid_request', 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'not_available', 'message' => $e->getMessage()], 404);
    }
  }

}
