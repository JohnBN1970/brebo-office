<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\brebo_finance\Service\FinancialApprovalMatrix;
use Drupal\brebo_finance\Service\FinancialGateExposureResolver;
use Drupal\brebo_finance\Service\FinancialPhaseGateManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Human-controlled write API for financial phase-gate exceptions. */
final class FinancialPhaseGateExceptionController extends ControllerBase {

  public function __construct(
    private readonly FinancialPhaseGateManager $manager,
    private readonly FinancialGateExposureResolver $exposureResolver,
    private readonly FinancialApprovalMatrix $approvalMatrix,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_finance.financial_phase_gate_manager'),
      $container->get('brebo_finance.financial_gate_exposure_resolver'),
      $container->get('brebo_finance.financial_approval_matrix'),
    );
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
    return new JsonResponse(['status' => 'requested', 'exception_id' => $id, 'project_nid' => $project_nid, 'gate' => $gate, 'phase_gate' => $this->manager->evaluate($project_nid, $gate)], 201);
  }

  public function decide(int $exception_id, Request $request): JsonResponse {
    $exception = $this->manager->exceptionMetadata($exception_id);
    $exposure = $this->exposureResolver->resolve($exception['project_nid'], $exception['finding_ids']);

    // Fail closed: incomplete financial exposure always requires executive authority.
    if ($exposure['unresolved'] !== []) {
      if (!$this->currentUser()->hasPermission('approve brebo finance executive')) {
        throw new AccessDeniedHttpException('Financial exposure is incomplete; executive approval is required.');
      }
      $authorization = [
        'authorized' => TRUE,
        'level' => 'executive_unresolved_exposure',
        'required_permissions' => ['approve brebo finance executive'],
        'exposure_amount' => $exposure['exposure_amount'],
      ];
    }
    else {
      $authorization = $this->approvalMatrix->authorize($this->currentUser(), $exception['gate'], $exposure['exposure_amount']);
      if (!$authorization['authorized']) {
        throw new AccessDeniedHttpException(sprintf('Insufficient procuration for %s at exposure EUR %s.', $exception['gate'], $exposure['exposure_amount']));
      }
    }

    $data = $this->payload($request);
    $decision = (string) ($data['decision'] ?? '');
    $this->manager->decideException($exception_id, $decision, (string) ($data['note'] ?? ''), (int) $this->currentUser()->id());
    return new JsonResponse([
      'status' => $decision,
      'exception_id' => $exception_id,
      'gate' => $exception['gate'],
      'decided_by' => (int) $this->currentUser()->id(),
      'exposure' => $exposure,
      'authorization' => $authorization,
    ]);
  }

  private function payload(Request $request): array {
    try {
      $data = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new BadRequestHttpException('Request body must contain valid JSON.', $exception);
    }
    if (!is_array($data)) throw new BadRequestHttpException('Request body must be a JSON object.');
    return $data;
  }
}
