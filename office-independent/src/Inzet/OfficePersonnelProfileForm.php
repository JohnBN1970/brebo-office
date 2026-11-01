<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Non-writing profile form. Only rendered after verified manager/project access.
 * Saving is intentionally unavailable until canonical identity mapping and
 * per-field authorization are accepted.
 */
final class OfficePersonnelProfileForm {
  public static function render(int $personId, string $displayName): string {
    if ($personId < 1 || trim($displayName) === '') {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $countries = ['NL' => '🇳🇱 Nederland (+31)', 'BE' => '🇧🇪 België (+32)', 'DE' => '🇩🇪 Duitsland (+49)', 'PL' => '🇵🇱 Polen (+48)', 'RO' => '🇷🇴 Roemenië (+40)', 'TR' => '🇹🇷 Turkije (+90)'];
    $languages = ['nl' => 'Nederlands', 'en' => 'English', 'pl' => 'Polski', 'ro' => 'Română', 'tr' => 'Türkçe', 'de' => 'Deutsch'];
    $html = '<section aria-label="Medewerkersdossier" style="border:1px solid #dbe1e8;border-radius:12px;padding:18px;margin:18px 0">'
      . '<h3>Medewerkersdossier · ' . $e($displayName) . '</h3>'
      . '<p>Conceptweergave. Opslaan en OnSite-activatie zijn nog niet beschikbaar vanuit dit formulier.</p>'
      . '<fieldset disabled><legend>Contact en taal</legend>'
      . '<label>Landcode telefoon <select name="phone_country">';
    foreach ($countries as $code => $label) {
      $html .= '<option value="' . $e($code) . '"' . ($code === 'NL' ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    $html .= '</select></label> <label>Telefoonnummer <input name="phone_number" type="tel" autocomplete="tel" placeholder="06 12345678"></label>'
      . '<label>Nationaliteit <input name="nationality" placeholder="Bijv. PL"></label>'
      . '<label>Voorkeurstaal <select name="preferred_language">';
    foreach ($languages as $code => $label) {
      $html .= '<option value="' . $e($code) . '">' . $e($label) . '</option>';
    }
    $html .= '</select></label></fieldset>'
      . '<fieldset disabled><legend>Skills en certificaten</legend>'
      . '<label>Skills (meerdere mogelijk) <select name="skills[]" multiple size="6">'
      . '<option>Glaszetten</option><option>Kozijnmontage</option><option>Timmeren</option>'
      . '<option>Schilderen</option><option>Kitten</option><option>Projectleiding</option></select></label>'
      . '<label>VCA geldig tot <input type="date" name="vca_expiry"></label></fieldset>'
      . '<p>Gevoelige identiteitsgegevens worden pas toegevoegd met afzonderlijke toegangscontrole.</p>'
      . '</section>';
    return $html;
  }
}
