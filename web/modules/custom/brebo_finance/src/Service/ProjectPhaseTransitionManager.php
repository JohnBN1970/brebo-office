<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\ProjectLifecycleGatewayInterface;
use Drupal\brebo_office_core\Project\ProjectLifecycle;
use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Central authority for financially protected BREBO project phase transitions.
 */
final class ProjectPhaseTransitionManager {

  private const TRANSITIONS = [
    'start_execution' => [
      'gate' => 'execution_start',
      'target' => ProjectLifecycle::EXECUTION,
    ],
    'close_project' => [
      'gate' => 'project_closeout',
      'target' => ProjectLifecycle::CLOSED,
    ],
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly ProjectLifecycleGatewayInterface $projects,
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

    $definition = self::TRANSITIONS[$transition];
    $target = $definition['target'];
    if ($this->projects->status($projectNid) === $target) {
      return;
    }

    $this->phaseGateManager->requireRelease($projectNid, $definition['gate']);

    if ($transition === 'close_project') {
      $this->assertCloseoutReady($projectNid);
    }

    $result = $this->projects->transition($projectNid, $target);
    if (!$result['changed']) {
      return;
    }
    $before = $result['before'];

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

}
