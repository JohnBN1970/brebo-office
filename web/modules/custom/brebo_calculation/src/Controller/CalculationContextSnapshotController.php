<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brebo_calculation\Service\CalculationContextSnapshotService;
use Drupal\brebo_calculation\Service\CalcIntegrationRequestAuthenticator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Exposes Office-owned calculation source context to the external Calc workbench. */
final class CalculationContextSnapshotController extends ControllerBase {

  public function __construct(
    private readonly CalculationContextSnapshotService $snapshots,
    private readonly CalcIntegrationRequestAuthenticator $authenticator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.calculation_context_snapshot'),
      $container->get('brebo_calculation.calc_request_authenticator'),
    );
  }

  public function get(Request $request, int $calculation): JsonResponse {
    $this->authenticator->assertSigned($request, '');
    return new JsonResponse([
      'contract' => 'brebo-calculation-context-snapshot-v1',
      'context' => $this->snapshots->latest($calculation),
    ], 200, ['Cache-Control' => 'no-store, private']);
  }

}
