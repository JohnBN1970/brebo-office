<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Conservative deterministic classifier for incoming mail.
 *
 * Primary classes stay deliberately coarse. Specific meanings such as a fine,
 * amount, deadline or event belong in a later extraction layer rather than in
 * an ever-growing classification taxonomy.
 */
final class MailClassifier {

  /**
   * @return array{classification:string,confidence:float,basis:string}
   */
  public function classify(string $subject, string $body): array {
    $text = mb_strtolower(trim($subject . "\n" . $body));
    $rules = [
      'financieel' => ['factuur', 'betaaltermijn', 'betaling', 'creditnota', 'incasso', 'aanmaning', 'rekening'],
      'juridisch' => ['aansprakelijk', 'juridisch', 'ingebrekestelling', 'sommatie', 'bezwaar', 'beroep', 'dagvaarding'],
      'personeel' => ['sollicitatie', 'arbeidsovereenkomst', 'salaris', 'verlof', 'ziekmelding', 'medewerker'],
      'leverancier' => ['leverancier', 'orderbevestiging', 'levertijd', 'backorder', 'pakbon'],
      'garantie' => ['garantie', 'garantieclaim', 'garantiewerk', 'nazorg'],
      'oplevering' => ['oplevering', 'opleverpunt', 'opleverlijst', 'restpunt'],
      'klacht' => ['klacht', 'ontevreden', 'schade', 'lekkage', 'gebrek'],
      'meerwerk' => ['meerwerk', 'minderwerk', 'aanvullende opdracht', 'extra werk'],
      'inkoop' => ['inkoop', 'bestelling', 'inkooporder'],
      'offerte' => ['offerte', 'prijsopgave', 'aanbieding', 'begroting'],
      'planning' => ['planning', 'werkplanning', 'startdatum', 'uitvoeringstermijn', 'weekplanning'],
      'bewonersmelding' => ['bewoner', 'huurder', 'woning', 'overlast', 'toegang woning'],
      'foto' => ['foto', 'afbeelding', 'beeldmateriaal'],
      'administratief' => [
        'verzekering', 'polis', 'kenteken', 'abonnement', 'registratie', 'vergunning',
        'bekeuring', 'verkeersboete', 'boetebeschikking', 'cjib', 'sanctiebedrag',
      ],
      'reclame' => ['aanbieding geldig', 'promotie', 'actieprijs', 'nieuwsbrief', 'marketing', 'korting'],
      'notificatie' => ['notificatie', 'melding', 'bevestiging van', 'statusupdate', 'automatisch bericht'],
      'spam' => ['ongewenste e-mail', 'spambericht', 'phishing', 'verdachte link'],
    ];

    $scores = [];
    foreach ($rules as $classification => $terms) {
      $hits = 0;
      foreach ($terms as $term) {
        if (str_contains($text, $term)) {
          $hits++;
        }
      }
      if ($hits > 0) {
        $scores[$classification] = $hits;
      }
    }

    if ($scores === []) {
      return [
        'classification' => 'overig',
        'confidence' => 35.0,
        'basis' => 'Geen beheerste classificatieregel gaf voldoende aanwijzing; menselijke controle vereist.',
      ];
    }

    arsort($scores);
    $best = (string) array_key_first($scores);
    $bestScore = (int) $scores[$best];
    $secondScore = count($scores) > 1 ? (int) array_values($scores)[1] : 0;
    $ambiguous = $secondScore === $bestScore;

    return [
      'classification' => $ambiguous ? 'overig' : $best,
      'confidence' => $ambiguous ? 45.0 : min(90.0, 60.0 + (($bestScore - 1) * 10.0)),
      'basis' => $ambiguous
        ? 'Meerdere classificaties scoorden gelijk; geen categorie als feit gepresenteerd.'
        : sprintf('Deterministische trefwoordclassificatie: %s met %d relevante aanwijzing(en).', $best, $bestScore),
    ];
  }

}
