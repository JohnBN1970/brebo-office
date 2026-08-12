<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Converts meaning signals into advisory next steps for human review.
 *
 * Advice is intentionally non-formal: it does not create actions, risks,
 * deadlines or other dossier facts by itself.
 */
final class MailFollowupAdvisor {

  /**
   * @param array<string, mixed> $meaning
   *
   * @return string[]
   */
  public function advise(string $classification, array $meaning): array {
    $signals = is_array($meaning['signals'] ?? NULL) ? $meaning['signals'] : [];
    $subtypes = is_array($meaning['subtypes'] ?? NULL) ? $meaning['subtypes'] : [];
    $advice = [];

    if (!empty($signals['action_requested'])) {
      $advice[] = 'actie beoordelen';
    }
    if (!empty($signals['deadline_present'])) {
      $advice[] = 'termijn bewaken';
    }
    if (!empty($signals['risk_present'])) {
      $advice[] = 'risico beoordelen';
    }
    if (!empty($signals['amount_present']) && in_array($classification, ['financieel', 'administratief', 'juridisch'], TRUE)) {
      $advice[] = 'bedrag controleren';
    }

    foreach ($subtypes as $subtype) {
      switch ((string) $subtype) {
        case 'bekeuring':
          $advice[] = 'bekeuring beoordelen';
          break;

        case 'betaling':
          $advice[] = 'betaling beoordelen';
          break;

        case 'commercieel_voorstel':
          $advice[] = 'voorstel beoordelen';
          break;

        case 'keuring':
          $advice[] = 'keuring beoordelen/plannen';
          break;
      }
    }

    return array_values(array_unique($advice));
  }

}
