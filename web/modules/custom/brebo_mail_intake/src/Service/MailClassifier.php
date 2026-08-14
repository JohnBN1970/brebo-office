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
    $subjectText = mb_strtolower(trim($subject));
    $bodyText = mb_strtolower(trim($body));
    $text = trim($subjectText . "\n" . $bodyText);

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

    // Strong intent phrases get additional weight. This keeps the taxonomy
    // compact while allowing natural BREBO language to resolve weak ties.
    $intentRules = [
      'offerte' => [
        'graag een offerte',
        'wil graag een offerte',
        'willen graag een offerte',
        'kunt u een offerte',
        'kun je een offerte',
        'ontvang graag een offerte',
        'ontvangen graag een offerte',
        'graag uw offerte',
        'graag een prijsopgave',
        'graag uw prijs',
      ],
    ];

    $scores = [];
    $signals = [];
    foreach ($rules as $classification => $terms) {
      foreach ($terms as $term) {
        if (str_contains($bodyText, $term)) {
          $scores[$classification] = ($scores[$classification] ?? 0) + 1;
          $signals[$classification][] = 'tekst:' . $term;
        }
        if ($subjectText !== '' && str_contains($subjectText, $term)) {
          // Subject is a deliberate sender-supplied summary and therefore a
          // stronger signal than a single occurrence in the body.
          $scores[$classification] = ($scores[$classification] ?? 0) + 2;
          $signals[$classification][] = 'onderwerp:' . $term;
        }
      }
    }

    foreach ($intentRules as $classification => $phrases) {
      foreach ($phrases as $phrase) {
        if (str_contains($text, $phrase)) {
          $scores[$classification] = ($scores[$classification] ?? 0) + 3;
          $signals[$classification][] = 'intentie:' . $phrase;
        }
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

    if ($ambiguous) {
      return [
        'classification' => 'overig',
        'confidence' => 45.0,
        'basis' => 'Meerdere classificaties scoorden gelijk; geen categorie als feit gepresenteerd.',
      ];
    }

    $basisSignals = array_slice(array_unique($signals[$best] ?? []), 0, 4);
    return [
      'classification' => $best,
      'confidence' => min(95.0, 60.0 + (($bestScore - 1) * 7.5)),
      'basis' => sprintf(
        'Deterministische classificatie: %s met gewogen score %d%s.',
        $best,
        $bestScore,
        $basisSignals !== [] ? ' (' . implode(', ', $basisSignals) . ')' : '',
      ),
    ];
  }

}
