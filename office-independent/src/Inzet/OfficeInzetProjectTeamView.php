<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Native Office team presentation. Input must come from the verified
 * manageable-project personnel repository; never from a browser payload.
 * No Drupal forms, identity writes, or invitation-token generation.
 */
final class OfficeInzetProjectTeamView {
  /** @param list<array<string,mixed>> $people */
  public static function render(array $people, string $projectTitle, ?string $editUrlPrefix = null): string {
    $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-project-team" aria-label="Projectteam">'
      . '<h2>Projectteam</h2>'
      . '<p>Medewerkers gekoppeld aan ' . $escape($projectTitle) . '.</p>';
    if ($people === []) {
      return $html . '<p>Er zijn nog geen actieve medewerkers aan dit project gekoppeld.</p></section>';
    }
    $validPeople = [];
    foreach ($people as $person) {
      $id = filter_var($person['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (!is_int($id) || isset($validPeople[$id])) continue;
      $validPeople[$id] = $person;
    }
    $missingCount = 0;
    foreach ($validPeople as $person) {
      if (trim((string)($person['personnel_number'] ?? '')) === '') $missingCount++;
    }
    $html .= '<p><strong>' . count($validPeople) . ' medewerkers</strong>'
      . ($missingCount > 0 ? ' · ' . $missingCount . ' profiel(en) aanvullen' : ' · Personeelsnummers compleet')
      . '</p>';
    $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">';
    foreach ($validPeople as $id => $person) {
      $name = trim((string)($person['display_name'] ?? ''));
      $number = trim((string)($person['personnel_number'] ?? ''));
      $hasNumber = $number !== '';
      $html .= '<article aria-label="Medewerker" style="padding:16px;border:1px solid #dbe1e8;border-radius:12px;background:#f8fafc">'
        . '<strong>' . $escape($name !== '' ? $name : 'Medewerker #' . $id) . '</strong>'
        . '<small style="display:block;color:#57677b;margin-top:6px">'
        . $escape($hasNumber ? $number : 'Personeelsnummer niet ingevuld')
        . '</small>'
        . (!$hasNumber ? '<small style="display:block;margin-top:6px;color:#915b00">Profiel aanvullen vereist</small>' : '')
        . ($editUrlPrefix !== null ? '<a style="display:block;margin-top:10px" href="' . $escape($editUrlPrefix . $id) . '">Personeelsnummer wijzigen</a>' : '')
        . '</article>';
    }
    $html .= '</div>';
    $html .= '<p><small>Profielgegevens kunnen pas worden gewijzigd na controle van de personeelsidentiteit en bewerkrechten.</small></p>';
    return $html . '</section>';
  }
}
