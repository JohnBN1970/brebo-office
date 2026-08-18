<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Compares personnel allocation with released working-budget hours.
 */
final class PersonnelBudgetControl {

  /**
   * @return array{lines: array<int|string, array<string, mixed>>, unlinked: array<int|string, array<string, mixed>>}
   */
  public function analyze(array $budget_lines, array $assignments): array {
    $lines = [];
    foreach ($budget_lines as $id => $line) {
      $lines[$id] = $line + [
        'budget_hours' => 0.0,
        'budget_actual_hours' => 0.0,
      ];
      $lines[$id]['allocated_hours'] = 0.0;
      $lines[$id]['assignment_actual_hours'] = 0.0;
      $lines[$id]['assignments'] = 0;
    }

    $unlinked = [];
    foreach ($assignments as $id => $assignment) {
      $line_id = $assignment['budget_line_id'] ?? NULL;
      if ($line_id === NULL || !isset($lines[$line_id])) {
        $unlinked[$id] = $assignment + [
          'control_status' => 'Blokkade',
          'control_message' => 'Geen geldige werkbegrotingsregel gekoppeld.',
        ];
        continue;
      }
      $lines[$line_id]['allocated_hours'] += max(0.0, (float) ($assignment['planned_hours'] ?? 0));
      $lines[$line_id]['assignment_actual_hours'] += max(0.0, (float) ($assignment['actual_hours'] ?? 0));
      $lines[$line_id]['assignments']++;
    }

    foreach ($lines as &$line) {
      $budget = max(0.0, (float) $line['budget_hours']);
      $allocated = (float) $line['allocated_hours'];
      $actual = max((float) $line['budget_actual_hours'], (float) $line['assignment_actual_hours']);
      $line['remaining_to_allocate'] = $budget - $allocated;
      $line['remaining_after_actual'] = $budget - $actual;
      $line['allocation_percent'] = $budget > 0 ? ($allocated / $budget) * 100 : ($allocated > 0 ? 100.0 : 0.0);
      $line['actual_percent'] = $budget > 0 ? ($actual / $budget) * 100 : ($actual > 0 ? 100.0 : 0.0);

      if ($budget <= 0 && ($allocated > 0 || $actual > 0)) {
        $line['control_status'] = 'Blokkade';
        $line['control_message'] = 'Personeelsuren gepland zonder vrijgegeven urenbudget.';
      }
      elseif ($actual > $budget) {
        $line['control_status'] = 'Blokkade';
        $line['control_message'] = 'Werkelijke uren overschrijden het vrijgegeven urenbudget.';
      }
      elseif ($allocated > $budget) {
        $line['control_status'] = 'Blokkade';
        $line['control_message'] = 'Geplande inzet overschrijdt het vrijgegeven urenbudget.';
      }
      elseif ($allocated < $budget) {
        $line['control_status'] = 'Waarschuwing';
        $line['control_message'] = 'Nog niet alle vrijgegeven uren zijn aan personen of ploegen toegewezen.';
      }
      else {
        $line['control_status'] = 'Akkoord';
        $line['control_message'] = 'Geplande inzet sluit aan op het vrijgegeven urenbudget.';
      }
    }
    unset($line);

    return ['lines' => $lines, 'unlinked' => $unlinked];
  }

}
