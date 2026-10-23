<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Escape and render evidence-only presence review without time approval controls. */
final class OnSitePresenceReviewView {
  public static function render(array $review): string {
    $escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html='<section class="onsite-presence-review"><h2>Aanwezigheidscontrole</h2>'
      .'<p>Automatisch geregistreerde aanwezigheid is bewijsmateriaal, geen goedgekeurde uren.</p>';
    $html.='<h3>Lopende aanwezigheid</h3><p>De teller is indicatief totdat vertrek is bevestigd. De pagina ververst automatisch.</p>';
    $html.='<table><thead><tr><th>Medewerker</th><th>Project</th><th>Zone</th><th>Start (UTC)</th><th>Lopende duur</th><th>Status</th></tr></thead><tbody>';
    foreach (($review['active']??[]) as $item) {
      $started = (string)($item['entered_at']??'');
      $startSeconds = strtotime($started);
      if ($startSeconds === false) continue;
      $duration = max(0,time()-$startSeconds);
      $html.='<tr><td>'.$escape($item['person_id']??'').'</td><td>'.$escape($item['project_id']??'')
        .'</td><td>'.$escape($item['zone_id']??'').'</td><td>'.$escape($started)
        .'</td><td><time class="onsite-live-duration" data-start="'.$escape($startSeconds).'">'.$escape(sprintf('%d:%02d:%02d',intdiv($duration,3600),intdiv($duration%3600,60),$duration%60))
        .'</time></td><td>Aanwezig (nog niet goedgekeurd)</td></tr>';
    }
    $html.='</tbody></table><h3>Afgeronde aanwezigheidsintervallen</h3><table><thead><tr>'
      .'<th>Medewerker</th><th>Project</th><th>Zone</th><th>Aankomst</th><th>Vertrek</th><th>Duur</th><th>Status</th>'
      .'</tr></thead><tbody>';
    foreach (($review['intervals']??[]) as $item) {
      $duration=isset($item['elapsed_seconds'])?(int)$item['elapsed_seconds']:0;
      $html.='<tr><td>'.$escape($item['person_id']??'').'</td><td>'.$escape($item['project_id']??'')
        .'</td><td>'.$escape($item['zone_id']??'').'</td><td>'.$escape($item['entered_at']??'')
        .'</td><td>'.$escape($item['left_at']??'').'</td><td>'.$escape(sprintf('%d:%02d',intdiv(max(0,$duration),3600),intdiv(max(0,$duration)%3600,60)))
        .'</td><td>Ter beoordeling</td></tr>';
    }
    $html.='</tbody></table><h3>Afwijkingen</h3><table><thead><tr><th>Type</th><th>Gebeurtenis</th></tr></thead><tbody>';
    $labels=['overlapping_arrival'=>'Dubbele aankomst','departure_without_arrival'=>'Vertrek zonder aankomst',
      'missing_departure'=>'Ontbrekend vertrek','zone_mismatch'=>'Afwijkende zone',
      'nonpositive_interval'=>'Ongeldig tijdsinterval'];
    foreach (($review['exceptions']??[]) as $item) {
      $reason=(string)($item['reason']??'');
      $html.='<tr><td>'.$escape($labels[$reason]??$reason).'</td><td>'.$escape($item['event_id']??'').'</td></tr>';
    }
    return $html.'</tbody></table></section>';
  }
}
