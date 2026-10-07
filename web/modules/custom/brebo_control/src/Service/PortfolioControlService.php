<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_control\Contract\PortfolioProjectSourceInterface;

/**
 * Aggregates project control into a management portfolio view.
 */
final class PortfolioControlService {

  public function __construct(
    private readonly PortfolioProjectSourceInterface $projectSource,
  ) {}

  /** @return array<string, mixed> */
  public function analyze(): array {
    $facts = $this->projectSource->activeProjectFacts();
    if ($facts === []) {
      return [
        'project_count' => 0,
        'critical_or_high' => 0,
        'deteriorating' => 0,
        'portfolio_expected_result' => 0.0,
        'total_exposure_score' => 0.0,
        'top_risk_projects' => [],
        'projects_covering_80_pct_risk' => [],
        'projects' => [],
      ];
    }

    $projects = [];
    $totalExposure = 0.0;
    $totalExpectedResult = 0.0;
    foreach ($facts as $fact) {
      $warning = (array) $fact['warning'];
      $trend = (array) $fact['trend'];
      $snapshot = $warning['financial_snapshot'];
      $expectedResult = (float) $snapshot['expected_result'];
      $marginDelta = (float) $snapshot['margin_delta_pct'];
      $riskScore = (int) $warning['score'];
      $trendPenalty = ($trend['status'] ?? '') === 'deteriorating' ? 15 : 0;
      $resultExposure = $expectedResult < 0 ? abs($expectedResult) : 0.0;
      $marginExposure = $marginDelta < 0 ? abs($marginDelta) * 1000 : 0.0;
      $exposure = $resultExposure + $marginExposure + ($riskScore * 100) + ($trendPenalty * 100);

      $totalExposure += $exposure;
      $totalExpectedResult += $expectedResult;
      $projects[] = [
        'project_id' => (int) $fact['project_id'],
        'project' => (string) $fact['project'],
        'risk_score' => $riskScore,
        'risk_level' => (string) $warning['level'],
        'expected_result' => round($expectedResult, 2),
        'expected_margin_pct' => round((float) $snapshot['expected_margin_pct'], 2),
        'margin_delta_pct' => round($marginDelta, 2),
        'trend_status' => (string) ($trend['status'] ?? 'insufficient_data'),
        'trend_risk_delta' => (int) ($trend['risk_delta'] ?? 0),
        'trend_margin_delta_pct' => round((float) ($trend['margin_delta_pct'] ?? 0), 2),
        'exposure_score' => round($exposure, 2),
      ];
    }

    usort($projects, static fn(array $a, array $b): int => $b['exposure_score'] <=> $a['exposure_score']);

    $cumulative = 0.0;
    foreach ($projects as &$row) {
      $share = $totalExposure > 0 ? ((float) $row['exposure_score'] / $totalExposure) * 100 : 0.0;
      $cumulative += $share;
      $row['risk_share_pct'] = round($share, 1);
      $row['cumulative_risk_share_pct'] = round($cumulative, 1);
    }
    unset($row);

    $top80 = [];
    foreach ($projects as $row) {
      if (($row['cumulative_risk_share_pct'] - $row['risk_share_pct']) >= 80.0) {
        break;
      }
      $top80[] = $row;
    }

    $critical = count(array_filter($projects, static fn(array $row): bool => in_array($row['risk_level'], ['hoog', 'kritiek'], TRUE)));
    $deteriorating = count(array_filter($projects, static fn(array $row): bool => $row['trend_status'] === 'deteriorating'));

    return [
      'project_count' => count($projects),
      'critical_or_high' => $critical,
      'deteriorating' => $deteriorating,
      'portfolio_expected_result' => round($totalExpectedResult, 2),
      'total_exposure_score' => round($totalExposure, 2),
      'top_risk_projects' => array_slice($projects, 0, 10),
      'projects_covering_80_pct_risk' => $top80,
      'projects' => $projects,
    ];
  }

}
