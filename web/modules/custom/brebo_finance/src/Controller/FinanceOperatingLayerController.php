<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\brebo_finance\Service\CommitmentManager;
use Drupal\brebo_finance\Service\WorkingBudgetApprovalManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Office operating layer for working budgets and commitments. */
final class FinanceOperatingLayerController implements ContainerInjectionInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkingBudgetApprovalManager $budgetApprovalManager,
    private readonly CommitmentManager $commitmentManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('brebo_finance.working_budget_approval_manager'),
      $container->get('brebo_finance.commitment_manager'),
      $container->get('current_user'),
    );
  }

  public function overview(int $project_nid): JsonResponse {
    $this->assertProjectAccess($project_nid);
    $budget = $this->database->select('brebo_finance_budget', 'b')->fields('b')->condition('project_nid', $project_nid)->condition('budget_type', 'working')->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $budgetId = $budget !== FALSE ? (int) $budget['id'] : 0;
    $lines = $budgetId ? $this->database->select('brebo_finance_budget_line', 'l')->fields('l')->condition('budget_id', $budgetId)->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC) : [];
    $approvals = $budgetId ? $this->database->select('brebo_finance_budget_approval', 'a')->fields('a')->condition('budget_id', $budgetId)->execute()->fetchAll(\PDO::FETCH_ASSOC) : [];
    $commitments = $this->database->select('brebo_finance_commitment', 'c')->fields('c')->condition('project_nid', $project_nid)->orderBy('id', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($commitments as &$commitment) {
      $commitment['lines'] = $this->database->select('brebo_finance_commitment_line', 'l')->fields('l')->condition('commitment_id', (int) $commitment['id'])->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    unset($commitment);
    return $this->json(['project_nid' => $project_nid, 'working_budget' => $budget !== FALSE ? $budget : NULL, 'budget_lines' => $lines, 'approvals' => $approvals, 'commitments' => $commitments]);
  }

  public function reviewBudget(int $project_nid, int $budget_id, Request $request): JsonResponse {
    $this->assertProjectAccess($project_nid);
    $this->assertBudgetBelongsToProject($budget_id, $project_nid);
    $data = $this->payload($request);
    $locked = $this->budgetApprovalManager->decide($budget_id, (string) ($data['discipline'] ?? ''), (string) ($data['decision'] ?? ''), (array) ($data['checklist'] ?? []), (string) ($data['note'] ?? ''), (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'project_nid' => $project_nid, 'budget_id' => $budget_id, 'locked' => $locked]);
  }

  public function createCommitment(int $project_nid, Request $request): JsonResponse {
    $this->assertProjectAccess($project_nid);
    $data = $this->payload($request);
    $id = $this->commitmentManager->createDraft($project_nid, (string) ($data['commitment_number'] ?? ''), (string) ($data['supplier_name'] ?? ''), isset($data['supplier_ref']) ? (string) $data['supplier_ref'] : NULL, (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'commitment_id' => $id], 201);
  }

  public function addCommitmentLine(int $project_nid, int $commitment_id, Request $request): JsonResponse {
    $this->assertProjectAccess($project_nid);
    $this->assertCommitmentBelongsToProject($commitment_id, $project_nid);
    $data = $this->payload($request);
    $lineId = $this->commitmentManager->addLine($commitment_id, (int) ($data['budget_line_id'] ?? 0), (string) ($data['description'] ?? ''), (string) ($data['quantity'] ?? ''), (string) ($data['unit'] ?? ''), (string) ($data['unit_price_ex_vat'] ?? ''), (string) ($data['vat_rate'] ?? '21'), (bool) ($data['vat_reverse_charge'] ?? FALSE), (string) ($data['non_deductible_vat_percentage'] ?? '0'), (int) $this->currentUser->id());
    return $this->json(['ok' => TRUE, 'project_nid' => $project_nid, 'commitment_id' => $commitment_id, 'commitment_line_id' => $lineId], 201);
  }

  private function assertProjectAccess(int $projectNid): void {
    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if ($project === NULL || $project->bundle() !== 'brebo_project') {
      throw new NotFoundHttpException('BREBO project does not exist.');
    }
    if (!$project->access('view', $this->currentUser)) {
      throw new AccessDeniedHttpException('No access to this BREBO project.');
    }
  }

  private function assertBudgetBelongsToProject(int $budgetId, int $projectNid): void {
    $ownerProject = $this->database->select('brebo_finance_budget', 'b')->fields('b', ['project_nid'])->condition('id', $budgetId)->condition('budget_type', 'working')->execute()->fetchField();
    if ($ownerProject === FALSE || (int) $ownerProject !== $projectNid) {
      throw new NotFoundHttpException('Working budget does not belong to this project.');
    }
  }

  private function assertCommitmentBelongsToProject(int $commitmentId, int $projectNid): void {
    $ownerProject = $this->database->select('brebo_finance_commitment', 'c')->fields('c', ['project_nid'])->condition('id', $commitmentId)->execute()->fetchField();
    if ($ownerProject === FALSE || (int) $ownerProject !== $projectNid) {
      throw new NotFoundHttpException('Commitment does not belong to this project.');
    }
  }

  private function payload(Request $request): array {
    try { $data = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new BadRequestHttpException('Invalid JSON payload.'); }
    if (!is_array($data)) throw new BadRequestHttpException('JSON object required.');
    return $data;
  }

  private function json(array $data, int $status = 200): JsonResponse {
    $response = new JsonResponse($data, $status);
    $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    return $response;
  }
}
