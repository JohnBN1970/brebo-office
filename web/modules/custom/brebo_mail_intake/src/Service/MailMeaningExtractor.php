<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Extracts coarse, explainable meaning signals without making formal decisions.
 */
final class MailMeaningExtractor {

  /**
   * @return array{signals:array<string,bool>,subtypes:string[],work_scope:array<string,mixed>,confidence:float,basis:string}
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

    $workScope = $this->extractWorkScope($text);
    $signalCount = count(array_filter($signals));
    $evidenceCount = $signalCount + count($subtypes) + ($workScope['requested'] !== [] ? 1 : 0);

    return [
      'signals' => $signals,
      'subtypes' => array_values(array_unique($subtypes)),
      'work_scope' => $workScope,
      'confidence' => $evidenceCount === 0 ? 0.0 : min(90.0, 50.0 + (($evidenceCount - 1) * 10.0)),
      'basis' => $evidenceCount === 0
        ? 'Geen beheerste betekenissignalen gevonden.'
        : sprintf('Deterministische betekenisextractie vond %d beheerste aanwijzing(en); menselijke controle blijft vereist.', $evidenceCount),
    ];
  }

  /**
   * Extracts commercial work-scope signals from the request itself.
   *
   * A positive montage request does not imply supply. Supply is only marked
   * requested when the mail explicitly asks for levering/material supply.
   *
   * @return array{requested:string[],not_requested:string[],confidence:float,basis:string}
   */
  private function extractWorkScope(string $text): array {
    $montage = $this->containsAny($text, [
      'montage offerte', 'montageofferte', 'montage offertes', 'montageoffertes',
      'aanvraag montage', 'montage aanvraag', 'montageaanvraag',
      'prijs voor montage', 'offerte voor montage', 'montagewerkzaamheden',
    ]);
    $supply = $this->containsAny($text, [
      'levering en montage', 'leveren en monteren', 'levering inclusief montage',
      'levering kozijnen', 'leveren kozijnen', 'kozijnen leveren',
      'levering glas', 'glas leveren', 'levering materialen', 'materialen leveren',
    ]);

    $requested = [];
    $notRequested = [];
    if ($montage) {
      $requested[] = 'montage';
      if (!$supply) {
        $notRequested[] = 'levering';
      }
    }
    if ($supply) {
      $requested[] = 'levering';
    }

    return [
      'requested' => $requested,
      'not_requested' => $notRequested,
      'confidence' => ($montage || $supply) ? 0.95 : 0.0,
      'basis' => $montage && !$supply
        ? 'De aanvraag noemt expliciet montage; levering wordt niet als gevraagde prestatie genoemd.'
        : ($supply ? 'De aanvraag noemt expliciet levering.' : 'Geen expliciete werksoort uit de aanvraag afgeleid.'),
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
