<?php

declare(strict_types=1);

namespace Drupal\brebo_document_data\Service;

/**
 * Conservative classifier for document dossier placement proposals.
 *
 * Classification proposes a dossier domain; it never creates canonical truth.
 */
final class DocumentContextClassifier {

  /** @return array{domain:string,confidence:float,reason:string,review_status:string} */
  public function classify(string $filename, string $text, array $hints = []): array {
    $haystack = mb_strtolower(trim($filename . "\n" . $text));
    $scores = [
      'building' => 0,
      'project' => 0,
      'crm' => 0,
      'brebo' => 0,
    ];

    $this->score($haystack, $scores, 'building', ['revisietekening', 'as-built', 'bag', 'bouwtekening', 'plattegrond', 'gevelaanzicht', 'vergunning', 'objectadres', 'gebouwinspectie']);
    $this->score($haystack, $scores, 'project', ['offerte', 'calculatie', 'opdracht', 'werkvoorbereiding', 'planning', 'inkoop', 'werkbon', 'oplevering', 'projectnummer', 'meerwerk', 'termijnstaat']);
    $this->score($haystack, $scores, 'crm', ['raamovereenkomst', 'contactpersoon', 'leverancier', 'prijslijst', 'relatie', 'organisatie', 'algemene afspraak']);
    $this->score($haystack, $scores, 'brebo', ['vca', 'kam', 'verzekering', 'certificaat', 'procedure', 'template', 'huisstijl', 'personeel', 'ict-beleid', 'algemene voorwaarden']);

    foreach (['building', 'project', 'crm', 'brebo'] as $domain) {
      if (($hints[$domain] ?? FALSE) === TRUE) {
        $scores[$domain] += 3;
      }
    }

    arsort($scores);
    $domains = array_keys($scores);
    $best = $domains[0];
    $bestScore = $scores[$best];
    $secondScore = $scores[$domains[1]] ?? 0;

    if ($bestScore <= 0) {
      return ['domain' => 'unclassified', 'confidence' => 0.0, 'reason' => 'Geen voldoende dossier-signaal gevonden.', 'review_status' => 'proposed'];
    }

    $margin = $bestScore - $secondScore;
    $confidence = min(0.95, 0.45 + ($bestScore * 0.08) + ($margin * 0.05));
    if ($margin <= 0) {
      return ['domain' => 'unclassified', 'confidence' => min(0.49, $confidence), 'reason' => 'Meerdere dossierdomeinen scoren gelijk; menselijke beoordeling vereist.', 'review_status' => 'proposed'];
    }

    return [
      'domain' => $best,
      'confidence' => $confidence,
      'reason' => sprintf('Documentinhoud scoort het sterkst voor %s (%d versus %d).', $best, $bestScore, $secondScore),
      'review_status' => 'proposed',
    ];
  }

  private function score(string $haystack, array &$scores, string $domain, array $terms): void {
    foreach ($terms as $term) {
      if (str_contains($haystack, mb_strtolower($term))) {
        $scores[$domain]++;
      }
    }
  }

}
