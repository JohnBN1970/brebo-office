<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Assigns a conservative review priority to advisory Mail Intake findings.
 *
 * Priority is only a work-queue aid. It is not a legal, financial or safety
 * decision and never creates a formal deadline or dossier fact.
 */
final class MailAttentionScorer {

  /**
   * @param array<string, mixed> $meaning
   *
   * @return array{label:string,score:int,basis:string}
   */
  public function score(string $classification, array $meaning): array {
    $signals = is_array($meaning['signals'] ?? NULL) ? $meaning['signals'] : [];
    $subtypes = is_array($meaning['subtypes'] ?? NULL) ? $meaning['subtypes'] : [];

    $score = 0;
    $basis = [];

    if (!empty($signals['deadline_present'])) {
      $score += 2;
      $basis[] = 'termijnsignaal';
    }
    if (!empty($signals['risk_present'])) {
      $score += 2;
      $basis[] = 'risicosignaal';
    }
    if (!empty($signals['action_requested'])) {
      $score += 1;
      $basis[] = 'actie gevraagd';
    }
    if (!empty($signals['amount_present']) && in_array($classification, ['financieel', 'administratief', 'juridisch'], TRUE)) {
      $score += 1;
      $basis[] = 'bedrag';
    }
    if (in_array('bekeuring', $subtypes, TRUE)) {
      $score += 1;
      $basis[] = 'bekeuring';
    }

    $label = match (TRUE) {
      $score >= 4 => 'Hoog',
      $score >= 2 => 'Aandacht',
      default => 'Normaal',
    };

    return [
      'label' => $label,
      'score' => $score,
      'basis' => $basis === []
        ? 'Geen extra prioriteitssignalen gevonden.'
        : 'Werkbakprioriteit op basis van: ' . implode(', ', $basis) . '.',
    ];
  }

}
