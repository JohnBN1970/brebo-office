<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailProposalRepositoryInterface;

/**
 * Materializes structured mail findings as unpublished dossier proposals.
 *
 * Creating a proposal is not the same as formally establishing a technical,
 * financial, contractual or safety fact.
 */
final class MailProposalMaterializer {

  public function __construct(
    private readonly MailProposalRepositoryInterface $proposalRepository,
  ) {}

  /**
   * @param array{actions?:array<int,array<string,mixed>>,signals?:array<int,array<string,mixed>>,risks?:array<int,array<string,mixed>>} $proposals
   * @return array{actions:int[],signals:int[],risks:int[]}
   */
  public function materialize(int $communicationId, int $actorId, array $proposals): array {
    if ($actorId <= 0) {
      throw new \InvalidArgumentException('Een geldige beoordelaar is verplicht voor mailvoorstellen.');
    }
    $context = $this->proposalRepository->communicationContext($communicationId);
    if ($context === NULL) {
      throw new \InvalidArgumentException('Voorstellen mogen alleen uit BREBO Communication ontstaan.');
    }

    $result = ['actions' => [], 'signals' => [], 'risks' => []];
    foreach ($proposals['actions'] ?? [] as $proposal) {
      $result['actions'][] = $this->createAction($context, $actorId, $proposal);
    }
    foreach ($proposals['signals'] ?? [] as $proposal) {
      $result['signals'][] = $this->createSignal($context, $actorId, $proposal);
    }
    foreach ($proposals['risks'] ?? [] as $proposal) {
      $result['risks'][] = $this->createRisk($context, $actorId, $proposal);
    }
    return $result;
  }

  /** @param array<string,mixed> $context @param array<string,mixed> $proposal */
  private function createAction(array $context, int $actorId, array $proposal): int {
    $description = trim((string) ($proposal['description'] ?? ''));
    if ($description === '') {
      throw new \InvalidArgumentException('Een actievoorstel vereist description.');
    }

    $fields = [
      'field_brebo_action_description' => $description,
      'field_brebo_priority' => trim((string) ($proposal['priority'] ?? 'Normaal')) ?: 'Normaal',
      'field_brebo_action_status' => 'Open',
    ];
    if (!empty($proposal['due_date'])) {
      $fields['field_brebo_due_date'] = (string) $proposal['due_date'];
    }

    return $this->proposalRepository->createProposal(
      'brebo_action',
      $this->proposalTitle('Actie', $description),
      $actorId,
      $context,
      $fields,
      'Ongepubliceerd actievoorstel uit Mail Intake; menselijke beoordeling vereist.',
    );
  }

  /** @param array<string,mixed> $context @param array<string,mixed> $proposal */
  private function createSignal(array $context, int $actorId, array $proposal): int {
    $description = trim((string) ($proposal['description'] ?? ''));
    if ($description === '') {
      throw new \InvalidArgumentException('Een signaalvoorstel vereist description.');
    }

    $fields = [
      'field_brebo_signal_description' => $description,
      'field_brebo_signal_severity' => trim((string) ($proposal['severity'] ?? 'Aandacht')) ?: 'Aandacht',
      'field_brebo_signal_status' => 'Nieuw',
    ];
    if (!empty($proposal['assessment'])) {
      $fields['field_brebo_assessment'] = (string) $proposal['assessment'];
    }

    return $this->proposalRepository->createProposal(
      'brebo_signal',
      $this->proposalTitle('Signaal', $description),
      $actorId,
      $context,
      $fields,
      'Ongepubliceerd signaalvoorstel uit Mail Intake; menselijke beoordeling vereist.',
    );
  }

  /** @param array<string,mixed> $context @param array<string,mixed> $proposal */
  private function createRisk(array $context, int $actorId, array $proposal): int {
    foreach (['cause', 'event', 'consequence', 'measure', 'probability', 'impact'] as $required) {
      if (trim((string) ($proposal[$required] ?? '')) === '') {
        throw new \InvalidArgumentException(sprintf('Een risicovoorstel vereist %s.', $required));
      }
    }

    $event = trim((string) $proposal['event']);
    $fields = [
      'field_brebo_risk_cause' => (string) $proposal['cause'],
      'field_brebo_risk_event' => $event,
      'field_brebo_risk_consequence' => (string) $proposal['consequence'],
      'field_brebo_risk_probability' => (string) $proposal['probability'],
      'field_brebo_risk_impact' => (string) $proposal['impact'],
      'field_brebo_risk_measure' => (string) $proposal['measure'],
      'field_brebo_risk_status' => 'Open',
    ];
    if (!empty($proposal['due_date'])) {
      $fields['field_brebo_due_date'] = (string) $proposal['due_date'];
    }
    if (!empty($proposal['residual_risk'])) {
      $fields['field_brebo_residual_risk'] = (string) $proposal['residual_risk'];
    }

    return $this->proposalRepository->createProposal(
      'brebo_risk',
      $this->proposalTitle('Risico', $event),
      $actorId,
      $context,
      $fields,
      'Ongepubliceerd risicovoorstel uit Mail Intake; menselijke beoordeling vereist.',
    );
  }

  private function proposalTitle(string $type, string $text): string {
    $clean = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    if (mb_strlen($clean) > 90) {
      $clean = mb_substr($clean, 0, 87) . '...';
    }
    return sprintf('[VOORSTEL] %s - %s', $type, $clean);
  }

}
