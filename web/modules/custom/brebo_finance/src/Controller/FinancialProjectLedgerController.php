<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialProjectLedger;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Read-only API for project financial drill-down. */
final class FinancialProjectLedgerController extends ControllerBase {
  public function __construct(
    private readonly FinancialProjectLedger $ledger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_project_ledger'), $container->get('entity_type.manager'));
  }

  public function view(int $project_nid): JsonResponse {
    $project = $this->entityTypeManager->getStorage('node')->load($project_nid);
    if ($project === NULL || $project->bundle() !== 'brebo_project') throw new NotFoundHttpException();
    if (!$project->access('view', $this->currentUser())) throw new AccessDeniedHttpException();
    $response = new JsonResponse($this->ledger->build($project_nid));
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }
}
