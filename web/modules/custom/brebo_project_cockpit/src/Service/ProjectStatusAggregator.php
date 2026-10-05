<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Service;

use Drupal\brebo_project_cockpit\Contract\ProjectStatusReadRepositoryInterface;

/**
 * Builds one deterministic project status from operational source domains.
 */
final class ProjectStatusAggregator {

  public function __construct(
    private readonly ProjectStatusReadRepositoryInterface $statusRepository,
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
    $source = $this->statusRepository->domainStatusValues($bundle, $projectId, $statusFields);
    if (!$source['available']) {
      return $this->unavailable($label);
    }
    if ($source['total'] === 0) {
      return ['label' => $label, 'status' => 'grijs', 'total' => 0, 'attention_count' => 0, 'message' => 'Nog geen projectdata.'];
    }
    if ($source['status_values'] === []) {
      return ['label' => $label, 'status' => 'groen', 'total' => $source['total'], 'attention_count' => 0, 'message' => 'Data aanwezig; geen statusveld aangesloten.'];
    }

    $red = 0;
    $orange = 0;
    foreach ($source['status_values'] as $rawValue) {
      $value = mb_strtolower(trim($rawValue));
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

    return ['label' => $label, 'status' => $status, 'total' => $source['total'], 'attention_count' => $attention, 'message' => $message];
  }

  /** @return array<string, mixed> */
  private function clockDomain(int $projectId): array {
    $source = $this->statusRepository->clockStatusCounts($projectId);
    if (!$source['available']) {
      return $this->unavailable('Inzet');
    }

    $total = $source['total'];
    $red = $source['red'];
    $orange = $source['orange'];
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
