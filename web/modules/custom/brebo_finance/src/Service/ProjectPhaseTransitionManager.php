<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Central authority for financially protected BREBO project phase transitions.
 */
final class ProjectPhaseTransitionManager {

  private const TRANSITIONS = [
    'start_execution' => [
      'gate' => 'execution_start',
      'target' => 'execution',
    ],
    'close_project' => [
      'gate' => 'project_closeout',
      'target' => 'closed',
    ],
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FinancialPhaseGateManager $phaseGateManager,
  ) {}

  /**
   * Performs a protected project transition after deterministic gate checks.
   */
  public function transition(int $projectNid, string $transition, string $reason, int $actorUid): void {
    if (!isset(self::TRANSITIONS[$transition])) {
      throw new InvalidArgumentException('Unsupported BREBO project phase transition.');
    }
    if ($actorUid <= 0 || trim($reason) === '') {
      throw new InvalidArgumentException('A human actor and transition reason are required.');
    }

    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if ($project === NULL || $project->bundle() !== 'brebo_project') {
      throw new UnexpectedValueException('BREBO project does not exist.');
    }

    $definition = self::TRANSITIONS[$transition];
    $this->phaseGateManager->requireRelease($projectNid, $definition['gate']);

    $statusField = $this->resolveStatusField($project);
    $before = (string) $project->get($statusField)->value;
    $target = $definition['target'];
    if ($before === $target) {
      return;
    }

    if ($transition === 'close_project') {
      $this->assertCloseoutReady($projectNid);
    }

    $project->set($statusField, $target);
    $project->save();

    $this->database->insert('brebo_finance_audit')->fields([
      'project_nid' => $projectNid,
      'entity_type' => 'project_phase',
      'entity_id' => $projectNid,
      'action' => $transition,
      'before_hash' => hash('sha256', $before),
      'after_hash' => hash('sha256', $target),
      'payload' => json_encode([
        'from' => $before,
        'to' => $target,
        'gate' => $definition['gate'],
        'ai_override_allowed' => FALSE,
      ], JSON_THROW_ON_ERROR),
      'reason' => trim($reason),
      'created' => time(),
      'created_by' => $actorUid,
    ])->execute();
  }

  /**
   * Prevents closeout while material financial administration remains open.
   */
  private function assertCloseoutReady(int $projectNid): void {
    $openCommitments = (int) $this->database->select('brebo_finance_commitment', 'c')
      ->condition('project_nid', $projectNid)
      ->condition('status', ['cancelled', 'closed'], 'NOT IN')
      ->countQuery()->execute()->fetchField();
    if ($openCommitments > 0) {
      throw new RuntimeException('Project closeout is blocked while purchase commitments remain open.');
    }

    $openInvoices = (int) $this->database->select('brebo_finance_purchase_invoice', 'i')
      ->condition('project_nid', $projectNid)
      ->condition('status', ['paid', 'cancelled'], 'NOT IN')
      ->countQuery()->execute()->fetchField();
    if ($openInvoices > 0) {
      throw new RuntimeException('Project closeout is blocked while purchase invoices remain unpaid or unresolved.');
    }

    $openBilling = (int) $this->database->select('brebo_finance_billing_instalment', 'b')
      ->condition('project_nid', $projectNid)
      ->condition('status', ['paid', 'cancelled'], 'NOT IN')
      ->countQuery()->execute()->fetchField();
    if ($openBilling > 0) {
      throw new RuntimeException('Project closeout is blocked while billing instalments remain open.');
    }
  }

  /**
   * Keeps the transition authority compatible with the project model while the
   * canonical project status field is still being consolidated.
   */
  private function resolveStatusField(object $project): string {
    foreach (['field_brebo_project_status', 'field_brebo_status'] as $fieldName) {
      if ($project->hasField($fieldName)) {
        return $fieldName;
      }
    }
    throw new RuntimeException('BREBO project has no canonical project status field; protected transition cannot be performed.');
  }

}
