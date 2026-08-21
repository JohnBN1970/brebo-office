<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Builds one deterministic project status from operational source domains.
 */
final class ProjectStatusAggregator {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /** @return array<string, mixed> */
  public function build(int $projectId): array {
    $domains = [
      'planning' => $this->domain('Planning', 'brebo_planning_activity', $projectId, ['field_brebo_planning_status', 'field_brebo_status']),
      'inzet' => $this->clockDomain($projectId),
      'quality' => $this->domain('Kwaliteit', 'brebo_deviation', $projectId, ['field_brebo_deviation_status', 'field_brebo_status']),
      'risks' => $this->domain('Risico’s', 'brebo_risk', $projectId, ['field_brebo_risk_status', 'field_brebo_status']),
      'actions' => $this->domain('Acties', 'brebo_action', $projectId, ['field_brebo_action_status', 'field_brebo_status']),
      'procurement' => $this->domain('Inkoop', 'brebo_rfq', $projectId, ['field_brebo_rfq_status', 'field_brebo_status']),
    ];

    $status = 'grijs';
    foreach ($domains as $domain) {
      $status = $this->worst($status, (string) $domain['status']);
    }

    $attention = [];
    foreach ($domains as $key => $domain) {
      if (in_array($domain['status'], ['rood', 'oranje'], TRUE)) {
        $attention[] = [
          'domain' => $key,
          'label' => $domain['label'],
          'status' => $domain['status'],
          'count' => $domain['attention_count'],
          'message' => $domain['message'],
        ];
      }
    }

    return ['status' => $status, 'domains' => $domains, 'attention' => $attention];
  }

  /** @return array<string, mixed> */
  private function domain(string $label, string $bundle, int $projectId, array $statusFields): array {
    $type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
    if ($type === NULL) {
      return $this->unavailable($label);
    }
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'])) {
      return $this->unavailable($label);
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId)
      ->execute();
    if ($ids === []) {
      return ['label' => $label, 'status' => 'grijs', 'total' => 0, 'attention_count' => 0, 'message' => 'Nog geen projectdata.'];
    }

    $statusField = NULL;
    foreach ($statusFields as $candidate) {
      if (isset($fields[$candidate])) {
        $statusField = $candidate;
        break;
      }
    }
    if ($statusField === NULL) {
      return ['label' => $label, 'status' => 'groen', 'total' => count($ids), 'attention_count' => 0, 'message' => 'Data aanwezig; geen statusveld aangesloten.'];
    }

    $red = 0;
    $orange = 0;
    foreach ($storage->loadMultiple($ids) as $node) {
      $value = mb_strtolower(trim((string) $node->get($statusField)->value));
      if ($this->matches($value, ['kritiek', 'critical', 'rood', 'blocked', 'geblokkeerd', 'overdue', 'verlopen', 'afgekeurd', 'rejected'])) {
        $red++;
      }
      elseif ($this->matches($value, ['open', 'oranje', 'attention', 'aandacht', 'pending', 'in review', 'in_review', 'concept', 'draft', 'risico'])) {
        $orange++;
      }
    }

    $status = $red > 0 ? 'rood' : ($orange > 0 ? 'oranje' : 'groen');
    $attention = $red + $orange;
    $message = $red > 0
      ? sprintf('%d kritisch/openstaand punt(en).', $red)
      : ($orange > 0 ? sprintf('%d punt(en) vragen aandacht.', $orange) : 'Geen actuele statusafwijkingen.');

    return ['label' => $label, 'status' => $status, 'total' => count($ids), 'attention_count' => $attention, 'message' => $message];
  }

  /** @return array<string, mixed> */
  private function clockDomain(int $projectId): array {
    $bundle = 'brebo_clock_registration';
    $type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
    if ($type === NULL) {
      return $this->unavailable('Inzet');
    }
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (!isset($fields['field_brebo_project_ref'], $fields['field_brebo_clock_severity'])) {
      return $this->unavailable('Inzet');
    }
    $base = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('field_brebo_project_ref', $projectId);
    $total = (int) (clone $base)->count()->execute();
    $red = (int) (clone $base)->condition('field_brebo_clock_severity', 'rood')->count()->execute();
    $orange = (int) (clone $base)->condition('field_brebo_clock_severity', 'oranje')->count()->execute();
    $status = $red > 0 ? 'rood' : ($orange > 0 ? 'oranje' : ($total > 0 ? 'groen' : 'grijs'));
    return [
      'label' => 'Inzet',
      'status' => $status,
      'total' => $total,
      'attention_count' => $red + $orange,
      'message' => $red > 0 ? "$red rode klokafwijking(en)." : ($orange > 0 ? "$orange klokafwijking(en) vragen aandacht." : 'Geen actuele klokafwijkingen.'),
    ];
  }

  /** @return array<string, mixed> */
  private function unavailable(string $label): array {
    return ['label' => $label, 'status' => 'grijs', 'total' => 0, 'attention_count' => 0, 'message' => 'Bron nog niet beschikbaar.'];
  }

  private function matches(string $value, array $needles): bool {
    foreach ($needles as $needle) {
      if ($value === $needle || str_contains($value, $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function worst(string $left, string $right): string {
    $rank = ['grijs' => 0, 'groen' => 1, 'oranje' => 2, 'rood' => 3];
    return ($rank[$right] ?? 0) > ($rank[$left] ?? 0) ? $right : $left;
  }

}
