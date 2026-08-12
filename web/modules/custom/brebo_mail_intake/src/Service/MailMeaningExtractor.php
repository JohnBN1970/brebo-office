<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Extracts coarse, explainable meaning signals without making formal decisions.
 */
final class MailMeaningExtractor {

  /**
   * @return array{signals:array<string,bool>,subtypes:string[],confidence:float,basis:string}
   */
  public function extract(string $subject, string $body): array {
    $text = mb_strtolower(trim($subject . "\n" . $body));

    $signals = [
      'amount_present' => (bool) preg_match('/(?:€\s*\d|\beur\s*\d|\d+[,.]\d{2}\s*(?:euro|eur)\b)/iu', $text),
      'deadline_present' => $this->containsAny($text, [
        'uiterlijk', 'voor ', 'vóór ', 'deadline', 'betaaltermijn', 'vervaldatum',
        'bezwaartermijn', 'beroepstermijn', 'binnen 14 dagen', 'binnen veertien dagen',
        'binnen 30 dagen', 'binnen dertig dagen',
      ]),
      'action_requested' => $this->containsAny($text, [
        'graag ontvangen', 'graag betalen', 'verzoek', 'wij verzoeken', 'dient te',
        'moet worden', 'actie vereist', 'bevestig', 'reageer', 'aanleveren',
      ]),
      'risk_present' => $this->containsAny($text, [
        'aanmaning', 'ingebrekestelling', 'sommatie', 'incasso', 'boete',
        'sanctie', 'aansprakelijk', 'verzuim', 'opschorting', 'verval',
      ]),
    ];

    $subtypes = [];
    if ($this->containsAny($text, ['bekeuring', 'verkeersboete', 'boetebeschikking', 'cjib', 'sanctiebedrag'])) {
      $subtypes[] = 'bekeuring';
    }
    if ($this->containsAny($text, ['factuur', 'creditnota', 'aanmaning', 'incasso'])) {
      $subtypes[] = 'betaling';
    }
    if ($this->containsAny($text, ['offerte', 'prijsopgave', 'aanbieding', 'begroting'])) {
      $subtypes[] = 'commercieel_voorstel';
    }
    if ($this->containsAny($text, ['apk', 'algemene periodieke keuring'])) {
      $subtypes[] = 'keuring';
    }

    $signalCount = count(array_filter($signals));
    $evidenceCount = $signalCount + count($subtypes);

    return [
      'signals' => $signals,
      'subtypes' => array_values(array_unique($subtypes)),
      'confidence' => $evidenceCount === 0 ? 0.0 : min(90.0, 50.0 + (($evidenceCount - 1) * 10.0)),
      'basis' => $evidenceCount === 0
        ? 'Geen beheerste betekenissignalen gevonden.'
        : sprintf('Deterministische betekenisextractie vond %d beheerste aanwijzing(en); menselijke controle blijft vereist.', $evidenceCount),
    ];
  }

  /**
   * @param string[] $terms
   */
  private function containsAny(string $text, array $terms): bool {
    foreach ($terms as $term) {
      if (str_contains($text, $term)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
