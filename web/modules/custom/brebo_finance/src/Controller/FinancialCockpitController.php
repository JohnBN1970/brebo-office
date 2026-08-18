<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\brebo_finance\Service\FinancialCockpitBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exposes the read-only project financial cockpit.
 */
final class FinancialCockpitController implements ContainerInjectionInterface {

  public function __construct(
    private readonly FinancialCockpitBuilder $cockpitBuilder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('brebo_finance.financial_cockpit_builder'),
      $container->get('entity_type.manager'),
    );
  }

  public function view(string $projectNid): JsonResponse {
    $projectId = (int) $projectNid;
    $project = $this->entityTypeManager
      ->getStorage('node')
      ->load($projectId);
    if ($project === NULL) {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }

    $response = new JsonResponse($this->cockpitBuilder->build($projectId));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }

}
