<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides the lightweight quality summary for the Office dashboard.
 */
final class QualityDashboardSummaryController extends ControllerBase {

  public function summary(): JsonResponse {
    $storage = $this->entityTypeManager()->getStorage('node');

    $count = static function (string $bundle, array $conditions = []) use ($storage): int {
      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $bundle);
      foreach ($conditions as [$field, $value, $operator]) {
        $query->condition($field, $value, $operator);
      }
      return (int) $query->count()->execute();
    };

    $timezone_name = (string) ($this->config('system.date')->get('timezone.default') ?: date_default_timezone_get());
    $timezone = new \DateTimeZone($timezone_name ?: 'Europe/Brussels');
    $today = (new \DateTimeImmutable('today', $timezone))->format('Y-m-d');

    $summary = [
      'controls' => $count('brebo_verification'),
      'approved' => $count('brebo_verification', [['field_brebo_control_result', 'Akkoord', '=']]),
      'deviating' => $count('brebo_verification', [['field_brebo_control_result', 'Afwijking', '=']]),
      'open_deviations' => $count('brebo_deviation', [['field_brebo_deviation_status', 'Gesloten', '<>']]),
      'blocked_release' => $count('brebo_verification', [['field_brebo_blocks_release', 1, '=']]),
      'overdue' => $count('brebo_deviation', [
        ['field_brebo_deviation_status', 'Gesloten', '<>'],
        ['field_brebo_due_date', $today, '<'],
      ]),
    ];

    return new JsonResponse($summary, 200, ['Cache-Control' => 'private, no-store, max-age=0']);
  }

}
