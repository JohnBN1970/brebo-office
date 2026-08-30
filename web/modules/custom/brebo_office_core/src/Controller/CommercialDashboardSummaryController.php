<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides the read-only commercial summary for the Office dashboard.
 */
final class CommercialDashboardSummaryController extends ControllerBase {

  public function summary(): JsonResponse {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_opportunity')
      ->condition('field_brebo_opp_active', 1)
      ->execute();

    $timezone_name = (string) ($this->config('system.date')->get('timezone.default') ?: date_default_timezone_get());
    $timezone = new \DateTimeZone($timezone_name ?: 'Europe/Brussels');
    $today = new \DateTimeImmutable('today', $timezone);
    $today_value = $today->format('Y-m-d');
    $close_limit = $today->modify('+30 days')->format('Y-m-d');

    $summary = [
      'active' => 0,
      'pipeline_value' => 0.0,
      'weighted_value' => 0.0,
      'overdue_follow_up' => 0,
      'closing_30d' => 0,
      'negotiation' => 0,
    ];

    foreach ($storage->loadMultiple($ids) as $opportunity) {
      if (!$opportunity instanceof NodeInterface || !$opportunity->access('view')) {
        continue;
      }

      $summary['active']++;
      $value = (float) ($opportunity->get('field_brebo_opp_value')->value ?? 0);
      $probability = max(0, min(100, (int) ($opportunity->get('field_brebo_opp_probability')->value ?? 0)));
      $summary['pipeline_value'] += $value;
      $summary['weighted_value'] += $value * ($probability / 100);

      $stage = trim((string) ($opportunity->get('field_brebo_opp_stage')->value ?? ''));
      if ($stage === 'Onderhandeling') {
        $summary['negotiation']++;
      }

      $next_date = trim((string) ($opportunity->get('field_brebo_opp_next_date')->value ?? ''));
      if ($next_date !== '' && $next_date < $today_value) {
        $summary['overdue_follow_up']++;
      }

      $close_date = trim((string) ($opportunity->get('field_brebo_opp_close_date')->value ?? ''));
      if ($close_date !== '' && $close_date >= $today_value && $close_date <= $close_limit) {
        $summary['closing_30d']++;
      }
    }

    $summary['pipeline_value'] = round($summary['pipeline_value'], 2);
    $summary['weighted_value'] = round($summary['weighted_value'], 2);

    return new JsonResponse($summary, 200, [
      'Cache-Control' => 'private, no-store, max-age=0',
    ]);
  }

}
