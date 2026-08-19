<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/**
 * Turns controller findings into a concise, decision-oriented daily briefing.
 */
final class ControllerBriefingBuilder {

  private const array SEVERITY_WEIGHT = [
    'critical' => 4,
    'high' => 3,
    'medium' => 2,
    'low' => 1,
  ];

  public function __construct(private readonly Connection $database) {}

  /**
   * Builds a briefing without inventing owners, deadlines or financial impact.
   *
   * Critical and high findings are never hidden. Medium findings are limited to
   * the most urgent items; low findings remain available in the evidence pack.
   *
   * @return array<string, mixed>
   */
  public function build(int $projectNid, int $mediumLimit = 5): array {
    $findings = $this->openFindings($projectNid);
    usort($findings, [$this, 'compare']);

    $actions = [];
    $watchlist = [];
    $omittedMedium = 0;
    $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

    foreach ($findings as $finding) {
      $severity = (string) $finding['severity'];
      if (isset($counts[$severity])) {
        $counts[$severity]++;
      }

      $item = $this->toBriefingItem($finding);
      if (in_array($severity, ['critical', 'high'], TRUE)) {
        $actions[] = $item;
      }
      elseif ($severity === 'medium') {
        if (count($watchlist) < max(0, $mediumLimit)) {
          $watchlist[] = $item;
        }
        else {
          $omittedMedium++;
        }
      }
    }

    $missingOwnership = count(array_filter(
      [...$actions, ...$watchlist],
      static fn (array $item): bool => $item['owner_uid'] === NULL || $item['due_date'] === NULL,
    ));

    return [
      'project_nid' => $projectNid,
      'generated_at' => time(),
      'status' => $this->status($counts),
      'headline' => $this->headline($counts, count($actions)),
      'counts' => $counts,
      'actions' => $actions,
      'watchlist' => $watchlist,
      'omitted_medium_count' => $omittedMedium,
      'missing_owner_or_deadline_count' => $missingOwnership,
      'principle' => 'Afwijking → oorzaak → financieel gevolg → maatregel → eigenaar → deadline.',
    ];
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function openFindings(int $projectNid): array {
    return $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', [
        'id',
        'control_code',
        'origin',
        'severity',
        'status',
        'source_type',
        'source_id',
        'title',
        'cause',
        'consequence',
        'control_measure',
        'owner_uid',
        'due_date',
        'payload',
        'detected',
        'last_seen',
      ])
      ->condition('project_nid', $projectNid)
      ->condition('status', ['open', 'pending_verification'], 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @return array<string, mixed>
   */
  private function toBriefingItem(array $finding): array {
    $payload = [];
    if (!empty($finding['payload'])) {
      $decoded = json_decode((string) $finding['payload'], TRUE);
      $payload = is_array($decoded) ? $decoded : [];
    }

    return [
      'finding_id' => (int) $finding['id'],
      'severity' => (string) $finding['severity'],
      'status' => (string) $finding['status'],
      'title' => (string) $finding['title'],
      'cause' => (string) $finding['cause'],
      'financial_consequence' => (string) $finding['consequence'],
      'verified_amounts' => $this->verifiedAmounts($payload),
      'proposed_measure' => (string) $finding['control_measure'],
      'owner_uid' => $finding['owner_uid'] !== NULL ? (int) $finding['owner_uid'] : NULL,
      'due_date' => $finding['due_date'] ?: NULL,
      'assignment_required' => $finding['owner_uid'] === NULL || empty($finding['due_date']),
      'evidence_reference' => [
        'control_code' => (string) $finding['control_code'],
        'origin' => (string) $finding['origin'],
        'source_type' => (string) $finding['source_type'],
        'source_id' => (int) $finding['source_id'],
        'last_seen' => (int) $finding['last_seen'],
      ],
    ];
  }

  /**
   * Returns only amounts explicitly present in deterministic evidence.
   *
   * @return array<string, string>
   */
  private function verifiedAmounts(array $payload): array {
    $amounts = [];
    foreach ([
      'amount_ex_vat',
      'amount_inc_vat',
      'variance_ex_vat',
      'forecast_impact_ex_vat',
      'lowest_balance',
      'recoverable_amount_ex_vat',
      'recovered_amount_ex_vat',
    ] as $key) {
      if (isset($payload[$key]) && is_scalar($payload[$key])) {
        $amounts[$key] = (string) $payload[$key];
      }
    }
    return $amounts;
  }

  private function compare(array $left, array $right): int {
    $severity = (self::SEVERITY_WEIGHT[$right['severity']] ?? 0)
      <=> (self::SEVERITY_WEIGHT[$left['severity']] ?? 0);
    if ($severity !== 0) {
      return $severity;
    }

    $leftDue = $left['due_date'] ?: '9999-12-31';
    $rightDue = $right['due_date'] ?: '9999-12-31';
    $due = strcmp((string) $leftDue, (string) $rightDue);
    return $due !== 0 ? $due : ((int) $left['detected'] <=> (int) $right['detected']);
  }

  /**
   * @param array<string, int> $counts
   */
  private function status(array $counts): string {
    if ($counts['critical'] > 0) {
      return 'critical';
    }
    if ($counts['high'] > 0) {
      return 'action_required';
    }
    if ($counts['medium'] > 0) {
      return 'attention';
    }
    return 'in_control';
  }

  /**
   * @param array<string, int> $counts
   */
  private function headline(array $counts, int $actionCount): string {
    if ($counts['critical'] > 0) {
      return sprintf('%d kritieke blokkade(s); besluit en eigenaar vereist.', $counts['critical']);
    }
    if ($actionCount > 0) {
      return sprintf('%d actiepunt(en) met hoge prioriteit.', $actionCount);
    }
    if ($counts['medium'] > 0) {
      return sprintf('%d aandachtspunt(en); geen kritieke blokkade.', $counts['medium']);
    }
    return 'Geen open financiële afwijkingen.';
  }

}
