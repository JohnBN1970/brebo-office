<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Form values from a manager-authorized details read.
 * Rendering is separate from the existing read-only employee identity view.
 */
final class OfficePersonnelDetailsReadForm {
  /** @param array<string,mixed> $details */
  public static function render(array $details, int $personId, int $projectId, string $csrfToken): string {
    if ($personId < 1 || $projectId < 1 || preg_match('/^[a-f0-9]{64}$/D', $csrfToken) !== 1) {
      throw new \InvalidArgumentException('Verified personnel form context required');
    }
    $e = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $countries = ['NL'=>'Nederland (+31)','BE'=>'Belgie (+32)','DE'=>'Duitsland (+49)','PL'=>'Polen (+48)','RO'=>'Roemenie (+40)','TR'=>'Turkije (+90)'];
    $languages = ['nl'=>'Nederlands','en'=>'English','pl'=>'Polski','ro'=>'Romana','tr'=>'Turkce','de'=>'Deutsch'];
    $skills = ['Glaszetten','Kozijnmontage','Timmeren','Schilderen','Kitten','Projectleiding'];
    $country = (string)($details['phone_country'] ?? 'NL');
    $phone = (string)($details['phone_e164'] ?? '');
    $html = '<section aria-label="Medewerkersgegevens bewerken"><h3>Medewerkersgegevens</h3>'
      . '<form method="post" action="/office-test/personeel/medewerkers/opslaan">'
      . '<input type="hidden" name="person_id" value="' . $personId . '">'
      . '<input type="hidden" name="project_id" value="' . $projectId . '">'
      . '<input type="hidden" name="csrf_token" value="' . $e($csrfToken) . '">'
      . '<label>Landcode telefoon <select name="phone_country">';
    foreach ($countries as $key=>$label) $html .= '<option value="' . $key . '"' . ($country === $key ? ' selected' : '') . '>' . $e($label) . '</option>';
    $html .= '</select></label> <label>Telefoonnummer <input type="tel" name="phone_number" value="' . $e($phone) . '"></label>';
    $html .= '<label>Nationaliteit <select name="nationality"><option value="">Niet opgegeven</option>';
    foreach ($countries as $key=>$label) $html .= '<option value="' . $key . '"' . (($details['nationality'] ?? '') === $key ? ' selected' : '') . '>' . $e($label) . '</option>';
    $html .= '</select></label><label>Voorkeurstaal <select name="preferred_language">';
    foreach ($languages as $key=>$label) $html .= '<option value="' . $key . '"' . (($details['preferred_language'] ?? 'nl') === $key ? ' selected' : '') . '>' . $e($label) . '</option>';
    $html .= '</select></label><fieldset><legend>Vaardigheden</legend>';
    $selected = is_array($details['skills'] ?? null) ? $details['skills'] : [];
    foreach ($skills as $skill) $html .= '<label><input type="checkbox" name="skills[]" value="' . $e($skill) . '"' . (in_array($skill, $selected, true) ? ' checked' : '') . '> ' . $e($skill) . '</label> ';
    $html .= '</fieldset><label>VCA geldig tot <input type="date" name="vca_expiry" value="' . $e($details['vca_expiry'] ?? '') . '"></label>'
      . '<button type="submit">Opslaan</button></form></section>';
    return $html;
  }
}
