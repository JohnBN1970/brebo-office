<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only preview of the planned create-shift interaction.
 * No POST endpoint is exposed until CSRF and session-bound write authorization
 * have been connected and tested.
 */
final class OfficeInzetShiftForm {
  public static function render(array $projects, array $people): string {
    $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-new-shift"><h2>Nieuwe dienst</h2>'
      . '<p>Voorbereiding: opslaan is nog niet ingeschakeld.</p>'
      . '<form aria-label="Nieuwe dienst voorbereiden" onsubmit="return false">'
      . '<label for="shift-project">Project</label><select id="shift-project" disabled>';
    foreach ($projects as $project) {
      $id = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if ($id === false) continue;
      $html .= '<option value="' . (int)$id . '">' . $escape($project['title'] ?? '') . '</option>';
    }
    $html .= '</select><label for="shift-person">Medewerker</label><select id="shift-person" disabled>';
    foreach ($people as $person) {
      $id = filter_var($person['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if ($id === false) continue;
      $html .= '<option value="' . (int)$id . '">' . $escape($person['display_name'] ?? '') . '</option>';
    }
    return $html . '</select><label for="shift-start">Begin (UTC)</label><input id="shift-start" type="datetime-local" disabled>'
      . '<label for="shift-end">Einde (UTC)</label><input id="shift-end" type="datetime-local" disabled>'
      . '<button type="button" disabled>Opslaan nog niet beschikbaar</button></form></section>';
  }
}
