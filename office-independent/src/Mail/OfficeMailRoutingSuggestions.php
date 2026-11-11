<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

/** Conservative fallback suggestions until the Office AI classifier is connected. */
final class OfficeMailRoutingSuggestions {
  /** @return array{module:string,reason:string} */
  public static function suggest(string $subject, string $body): array {
    $text = mb_strtolower(mb_substr($subject . ' ' . $body, 0, 4000, 'UTF-8'), 'UTF-8');
    $rules = [
      'finance' => ['factuur','betalingsherinnering','creditnota','betaalverzoek'],
      'inspecties' => ['inspectierapport','bouwkundige inspectie','schouwrapport'],
      'projecten' => ['uitvoeringsplanning','werkplanning','opleverdatum'],
      'gebouwen' => ['bag identificatie','gebouwadres','kadastraal'],
      'crm' => ['offerteaanvraag','prijsaanvraag','nieuwe aanvraag'],
    ];
    $hits = [];
    foreach ($rules as $module => $terms) {
      foreach ($terms as $term) {
        if (str_contains($text, $term)) { $hits[] = $module; break; }
      }
    }
    if (count($hits) !== 1) return ['module'=>'nieuw','reason'=>'Geen eenduidige automatische herkenning'];
    return ['module'=>$hits[0],'reason'=>'Voorlopige trefwoordherkenning; nog geen Office AI-classificatie'];
  }
}
