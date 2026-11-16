<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/** Scores public tenders against BREBO's commercial profile. */
final class TenderLeadScorer {

  private const TERMS = [
    'bouwkundig onderhoud' => 22,
    'vastgoedonderhoud' => 22,
    'planmatig onderhoud' => 20,
    'nen 2767' => 24,
    'conditiemeting' => 20,
    'mjop' => 22,
    'dmjop' => 22,
    'bouwbegeleiding' => 20,
    'onderhoudsmanagement' => 20,
    'directievoering' => 16,
    'renovatie' => 14,
    'verduurzaming' => 14,
    'kozijnen' => 16,
    'beglazing' => 16,
    'glas' => 10,
    'gevel' => 12,
    'inspectie' => 12,
  ];

  /** @return array{score:int,reasons:string[]} */
  public function score(array $tender): array {
    $haystack = mb_strtolower(implode(' ', array_filter([
      $tender['title'] ?? '', $tender['description'] ?? '', $tender['cpv'] ?? '',
      $tender['buyer'] ?? '', $tender['region'] ?? '',
    ])));
    $score = 0;
    $reasons = [];
    foreach (self::TERMS as $term => $weight) {
      if (str_contains($haystack, $term)) {
        $score += $weight;
        $reasons[] = $term;
      }
    }
    if (preg_match('/woningcorporatie|gemeente|provincie|universiteit|vve|vastgoedbeheer/', $haystack)) {
      $score += 8;
      $reasons[] = 'passend opdrachtgeverstype';
    }
    if (!empty($tender['deadline'])) {
      $days = (int) floor((strtotime((string) $tender['deadline']) - time()) / 86400);
      if ($days >= 7 && $days <= 45) {
        $score += 8;
        $reasons[] = 'werkbare inschrijftermijn';
      }
    }
    return ['score' => min(100, $score), 'reasons' => array_values(array_unique($reasons))];
  }

}
