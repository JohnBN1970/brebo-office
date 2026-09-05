<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialEuroTrace;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UnexpectedValueException;

/** Exposes source-backed traceability for one financial object. */
final class FinancialEuroTraceController extends ControllerBase {
  public function __construct(
    private readonly FinancialEuroTrace $trace,
    private readonly EntityTypeManagerInterface $financeEntityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_euro_trace'), $container->get('entity_type.manager'));
  }

  public function view(string $entity_type, int $entity_id): JsonResponse {
    try {
      $data = $this->trace->trace($entity_type, $entity_id);
    }
    catch (UnexpectedValueException) {
      throw new NotFoundHttpException('Financial trace source does not exist.');
    }

    $projectNid = (int) ($data['project_nid'] ?? 0);
    if ($projectNid > 0) {
      $project = $this->financeEntityTypeManager->getStorage('node')->load($projectNid);
      if ($project === NULL || $project->bundle() !== 'brebo_project') {
        throw new NotFoundHttpException();
      }
      if (!$project->access('view', $this->currentUser())) {
        throw new AccessDeniedHttpException();
      }
    }

    $response = new JsonResponse($data);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    return $response;
  }
}
