<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialPhaseGateManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Human-controlled write API for financial phase-gate exceptions. */
final class FinancialPhaseGateExceptionController extends ControllerBase {

  public function __construct(private readonly FinancialPhaseGateManager $manager) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_finance.financial_phase_gate_manager'));
  }

  public function requestException(int $project_nid, string $gate, Request $request): JsonResponse {
    $data = $this->payload($request);
    $id = $this->manager->requestException(
      $project_nid,
      $gate,
      is_array($data['finding_ids'] ?? NULL) ? $data['finding_ids'] : [],
      (string) ($data['reason'] ?? ''),
      (string) ($data['control_measure'] ?? ''),
      (string) ($data['expires_at'] ?? ''),
      is_array($data['evidence'] ?? NULL) ? $data['evidence'] : [],
      (int) $this->currentUser()->id(),
    );

    return new JsonResponse([
      'status' => 'requested',
      'exception_id' => $id,
      'project_nid' => $project_nid,
      'gate' => $gate,
      'phase_gate' => $this->manager->evaluate($project_nid, $gate),
    ], 201);
  }

  public function decide(int $exception_id, Request $request): JsonResponse {
    $data = $this->payload($request);
    $decision = (string) ($data['decision'] ?? '');
    $this->manager->decideException(
      $exception_id,
      $decision,
      (string) ($data['note'] ?? ''),
      (int) $this->currentUser()->id(),
    );

    return new JsonResponse([
      'status' => $decision,
      'exception_id' => $exception_id,
      'decided_by' => (int) $this->currentUser()->id(),
    ]);
  }

  private function payload(Request $request): array {
    try {
      $data = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new BadRequestHttpException('Request body must contain valid JSON.', $exception);
    }
    if (!is_array($data)) {
      throw new BadRequestHttpException('Request body must be a JSON object.');
    }
    return $data;
  }

}
