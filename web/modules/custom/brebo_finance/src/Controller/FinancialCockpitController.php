<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes the read-only project financial cockpit.
 */
final class FinancialCockpitController implements ContainerInjectionInterface {

  public function __construct(
    private readonly FinancialCockpitBuilder $cockpitBuilder,
    private readonly ProjectReferenceGatewayInterface $projects,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('brebo_finance.financial_cockpit_builder'),
      $container->get('brebo_finance.project_reference_gateway'),
      $container->get('current_user'),
    );
  }

  public function view(string $projectNid): JsonResponse {
    $projectId = (int) $projectNid;
    if (!$this->projects->exists($projectId)) {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
    if (!$this->projects->canView($projectId)) {
      throw new AccessDeniedHttpException('No access to this BREBO project.');
    }

    $response = new JsonResponse($this->cockpitBuilder->build($projectId));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
