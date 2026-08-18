<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Matches required work skills against verified qualification evidence.
 */
final class WorkforceQualificationMatcher {

  /**
   * @param array<int, string> $requirements
   *   Required skill IDs keyed by ID or as values.
   * @param array<int, array<string, mixed>> $qualifications
   *   Qualification records with skill_id, label, status and optional expires.
   *
   * @return array{status: string, missing: array<int, string>, expiring: array<int, string>, message: string}
   */
  public function match(array $requirements, array $qualifications, ?\DateTimeImmutable $on = NULL): array {
    $on ??= new \DateTimeImmutable('today');
    $limit = $on->modify('+30 days');
    $required = [];
    foreach ($requirements as $id => $label) {
      $skillId = is_int($id) ? (string) $label : (string) $id;
      $required[$skillId] = is_int($id) ? (string) $label : (string) $label;
    }

    $valid = [];
    $expiring = [];
    foreach ($qualifications as $qualification) {
      $skillId = (string) ($qualification['skill_id'] ?? '');
      if ($skillId === '' || (string) ($qualification['status'] ?? '') !== 'Geldig') {
        continue;
      }
      $expiresValue = trim((string) ($qualification['expires'] ?? ''));
      $expires = $expiresValue !== '' ? new \DateTimeImmutable($expiresValue) : NULL;
      if ($expires !== NULL && $expires < $on) {
        continue;
      }
      $valid[$skillId] = TRUE;
      if ($expires !== NULL && $expires <= $limit) {
        $expiring[$skillId] = $required[$skillId] ?? (string) ($qualification['label'] ?? $skillId);
      }
    }

    $missing = array_diff_key($required, $valid);
    if ($missing !== []) {
      return [
        'status' => 'Blokkade',
        'missing' => array_values($missing),
        'expiring' => array_values($expiring),
        'message' => 'Ontbrekend of ongeldig bewijs: ' . implode(', ', $missing) . '.',
      ];
    }
    if ($expiring !== []) {
      return [
        'status' => 'Waarschuwing',
        'missing' => [],
        'expiring' => array_values($expiring),
        'message' => 'Binnen 30 dagen verlopend: ' . implode(', ', $expiring) . '.',
      ];
    }

    return [
      'status' => 'Passend',
      'missing' => [],
      'expiring' => [],
      'message' => $required === [] ? 'Geen vakbekwaamheidseisen ingesteld.' : 'Alle vereiste kwalificaties zijn geldig.',
    ];
  }

}
