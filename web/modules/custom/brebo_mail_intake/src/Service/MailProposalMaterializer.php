<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Materializes structured mail findings as unpublished dossier proposals.
 *
 * These nodes are deliberately unpublished. Creating a proposal is not the same
 * as formally establishing a technical, financial, contractual or safety fact.
 */
final class MailProposalMaterializer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * @param array{actions?:array<int,array<string,mixed>>,signals?:array<int,array<string,mixed>>,risks?:array<int,array<string,mixed>>} $proposals
   *
   * @return array{actions:int[],signals:int[],risks:int[]}
   */
  public function materialize(NodeInterface $communication, array $proposals): array {
    if ($communication->bundle() !== 'brebo_communication') {
      throw new \InvalidArgumentException('Voorstellen mogen alleen uit BREBO Communication ontstaan.');
    }

    $result = ['actions' => [], 'signals' => [], 'risks' => []];
    foreach ($proposals['actions'] ?? [] as $proposal) {
      $result['actions'][] = $this->createAction($communication, $proposal);
    }
    foreach ($proposals['signals'] ?? [] as $proposal) {
      $result['signals'][] = $this->createSignal($communication, $proposal);
    }
    foreach ($proposals['risks'] ?? [] as $proposal) {
      $result['risks'][] = $this->createRisk($communication, $proposal);
    }
    return $result;
  }

  /** @param array<string, mixed> $proposal */
  private function createAction(NodeInterface $communication, array $proposal): int {
    $description = trim((string) ($proposal['description'] ?? ''));
    if ($description === '') {
      throw new \InvalidArgumentException('Een actievoorstel vereist description.');
    }

    $node = $this->createBase($communication, 'brebo_action', $this->proposalTitle('Actie', $description));
    $node->set('field_brebo_action_description', $description);
    $node->set('field_brebo_priority', trim((string) ($proposal['priority'] ?? 'Normaal')) ?: 'Normaal');
    $node->set('field_brebo_action_status', 'Open');
    if (!empty($proposal['due_date'])) {
      $node->set('field_brebo_due_date', (string) $proposal['due_date']);
    }
    $this->saveProposal($node, 'Ongepubliceerd actievoorstel uit Mail Intake; menselijke beoordeling vereist.');
    return (int) $node->id();
  }

  /** @param array<string, mixed> $proposal */
  private function createSignal(NodeInterface $communication, array $proposal): int {
    $description = trim((string) ($proposal['description'] ?? ''));
    if ($description === '') {
      throw new \InvalidArgumentException('Een signaalvoorstel vereist description.');
    }

    $node = $this->createBase($communication, 'brebo_signal', $this->proposalTitle('Signaal', $description));
    $node->set('field_brebo_signal_description', $description);
    $node->set('field_brebo_signal_severity', trim((string) ($proposal['severity'] ?? 'Aandacht')) ?: 'Aandacht');
    $node->set('field_brebo_signal_status', 'Nieuw');
    if (!empty($proposal['assessment'])) {
      $node->set('field_brebo_assessment', (string) $proposal['assessment']);
    }
    $this->saveProposal($node, 'Ongepubliceerd signaalvoorstel uit Mail Intake; menselijke beoordeling vereist.');
    return (int) $node->id();
  }

  /** @param array<string, mixed> $proposal */
  private function createRisk(NodeInterface $communication, array $proposal): int {
    foreach (['cause', 'event', 'consequence', 'measure', 'probability', 'impact'] as $required) {
      if (trim((string) ($proposal[$required] ?? '')) === '') {
        throw new \InvalidArgumentException(sprintf('Een risicovoorstel vereist %s.', $required));
      }
    }

    $event = trim((string) $proposal['event']);
    $node = $this->createBase($communication, 'brebo_risk', $this->proposalTitle('Risico', $event));
    $node->set('field_brebo_risk_cause', (string) $proposal['cause']);
    $node->set('field_brebo_risk_event', $event);
    $node->set('field_brebo_risk_consequence', (string) $proposal['consequence']);
    $node->set('field_brebo_risk_probability', (string) $proposal['probability']);
    $node->set('field_brebo_risk_impact', (string) $proposal['impact']);
    $node->set('field_brebo_risk_measure', (string) $proposal['measure']);
    $node->set('field_brebo_risk_status', 'Open');
    if (!empty($proposal['due_date'])) {
      $node->set('field_brebo_due_date', (string) $proposal['due_date']);
    }
    if (!empty($proposal['residual_risk'])) {
      $node->set('field_brebo_residual_risk', (string) $proposal['residual_risk']);
    }
    $this->saveProposal($node, 'Ongepubliceerd risicovoorstel uit Mail Intake; menselijke beoordeling vereist.');
    return (int) $node->id();
  }

  private function createBase(NodeInterface $communication, string $bundle, string $title): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $values = [
      'type' => $bundle,
      'title' => $title,
      'uid' => (int) $this->currentUser->id(),
      'status' => 0,
      'field_brebo_source_comm_ref' => ['target_id' => (int) $communication->id()],
      'field_brebo_responsible_user' => ['target_id' => (int) $this->currentUser->id()],
    ];

    foreach ([
      'field_brebo_building_ref' => 'field_brebo_building_ref',
      'field_brebo_project_ref' => 'field_brebo_project_ref',
      'field_brebo_comm_scope_target' => 'field_brebo_context_ref',
    ] as $sourceField => $targetField) {
      if ($communication->hasField($sourceField) && !$communication->get($sourceField)->isEmpty()) {
        $targetId = (int) $communication->get($sourceField)->target_id;
        if ($targetId > 0) {
          $values[$targetField] = ['target_id' => $targetId];
        }
      }
    }

    $node = $storage->create($values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException(sprintf('%s-voorstel kon niet worden aangemaakt.', $bundle));
    }
    return $node;
  }

  private function saveProposal(NodeInterface $node, string $revisionMessage): void {
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage($revisionMessage);
    $node->save();
  }

  private function proposalTitle(string $type, string $text): string {
    $clean = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    if (mb_strlen($clean) > 90) {
      $clean = mb_substr($clean, 0, 87) . '...';
    }
    return sprintf('[VOORSTEL] %s - %s', $type, $clean);
  }

}
