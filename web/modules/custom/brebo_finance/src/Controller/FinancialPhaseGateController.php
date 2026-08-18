<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialPhaseGatePresenter;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Read-only API for the five BREBO financial phase gates. */
final class FinancialPhaseGateController extends ControllerBase {

  public function __construct(private readonly FinancialPhaseGatePresenter $presenter) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_phase_gate_presenter'));
  }

  public function overview(int $project_nid): JsonResponse {
    return new JsonResponse([
      'project_nid' => $project_nid,
      'generated_at' => time(),
      'source' => 'BREBO Office Financial Controller',
      'phase_gates' => $this->presenter->present($project_nid),
    ]);
  }

}
