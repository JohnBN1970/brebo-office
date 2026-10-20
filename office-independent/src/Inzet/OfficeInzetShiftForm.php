<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only preview of the planned create-shift interaction.
 * No POST endpoint is exposed until CSRF and session-bound write authorization
 * have been connected and tested.
 */
final class OfficeInzetShiftForm {

  /**
   * Test-only active form. The HTTP endpoint must independently verify the
   * Office cookie session, CSRF token, project manage permission and team.
   */
  public static function renderEditable(array $project, array $people, string $action, string $csrfToken): string {
    if (!preg_match('~^/office-test/[a-z0-9/-]+$~D', $action)) {
      throw new \InvalidArgumentException('Only test Office action allowed');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $csrfToken)) {
      throw new \InvalidArgumentException('Invalid session CSRF token');
    }
    $projectId = filter_var($project['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!is_int($projectId)) throw new \InvalidArgumentException('Invalid project');
    $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-new-shift"><h2>Nieuwe dienst</h2>'
      . '<form method="post" action="' . $escape($action) . '">'
      . '<input type="hidden" name="csrf_token" value="' . $escape($csrfToken) . '">'
      . '<input type="hidden" name="project_id" value="' . $projectId . '">'
      . '<p>Project: ' . $escape($project['title'] ?? '') . '</p>'
      . '<label for="shift-person">Medewerker</label><select id="shift-person" name="person_id" required>';
    foreach ($people as $person) {
      $id = filter_var($person['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (!is_int($id)) continue;
      $label = trim((string)($person['display_name'] ?? ''))
        . ((string)($person['personnel_number'] ?? '') !== '' ? ' · ' . (string)$person['personnel_number'] : '')
        . ' (#' . $id . ')';
      $html .= '<option value="' . $id . '">' . $escape($label) . '</option>';
    }
    return $html . '</select><label for="shift-start">Begin (lokale tijd)</label>'
      . '<input id="shift-start" name="starts_at_local" type="datetime-local" required>'
      . '<label for="shift-end">Einde (lokale tijd)</label>'
      . '<input id="shift-end" name="ends_at_local" type="datetime-local" required>'
      . '<button type="submit">Dienst opslaan</button></form></section>';
  }

  public static function render(array $projects, array $people): string {
    $escape = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<section class="inzet-new-shift"><h2>Nieuwe dienst</h2>'
      . '<p>Voorbeeld voor één project: alleen medewerkers van dit project. Opslaan is nog niet ingeschakeld.</p>'
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
      $html .= '<option value="' . (int)$id . '">' . $escape(trim((string)($person['display_name'] ?? '')) . ((string)($person['personnel_number'] ?? '') !== '' ? ' · ' . (string)$person['personnel_number'] : '') . ' (#' . (int)$id . ')') . '</option>';
    }
    return $html . '</select><label for="shift-start">Begin (lokale tijd)</label><input id="shift-start" type="datetime-local" disabled>'
      . '<label for="shift-end">Einde (lokale tijd)</label><input id="shift-end" type="datetime-local" disabled>'
      . '<button type="button" disabled>Opslaan nog niet beschikbaar</button></form></section>';
  }
}
