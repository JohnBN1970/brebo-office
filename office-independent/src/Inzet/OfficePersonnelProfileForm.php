<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only personnel profile preview for an already authorized manager.
 * No POST route, account creation or OnSite activation is exposed.
 */
final class OfficePersonnelProfileForm {
  public static function render(int $personId, string $displayName): string {
    if ($personId < 1 || trim($displayName) === '') {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $countries = ['NL' => 'Nederland (+31)', 'BE' => 'België (+32)', 'DE' => 'Duitsland (+49)', 'PL' => 'Polen (+48)', 'RO' => 'Roemenië (+40)', 'TR' => 'Turkije (+90)'];
    $languages = ['nl' => 'Nederlands', 'en' => 'English', 'pl' => 'Polski', 'ro' => 'Română', 'tr' => 'Türkçe', 'de' => 'Deutsch'];
    $skills = ['Glaszetten', 'Kozijnmontage', 'Timmeren', 'Schilderen', 'Kitten', 'Projectleiding'];
    $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;align-items:start';
    $field = 'display:flex;flex-direction:column;gap:6px;font-weight:600;color:#25364a';
    $control = 'width:100%;max-width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;color:#475569;font:inherit';
    $html = '<section aria-label="Medewerkersdossier" style="border:1px solid #dbe1e8;border-radius:12px;padding:clamp(16px,3vw,28px);margin:18px 0;background:#fff">'
      . '<h3 style="margin:0 0 8px">Medewerkersdossier · ' . $e($displayName) . '</h3>'
      . '<p style="margin:0 0 20px;color:#64748b">Conceptweergave. Opslaan en OnSite-activatie zijn nog niet beschikbaar vanuit dit formulier.</p>'
      . '<fieldset disabled style="border:0;margin:0;padding:0;min-width:0">'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Contactgegevens</h4>'
      . '<div style="' . $grid . '"><label style="' . $field . '">Landcode telefoon<span style="display:flex;align-items:center;gap:10px"><img src="https://flagcdn.com/w40/nl.png" width="30" height="20" alt="Vlag Nederland" style="object-fit:cover;border:1px solid #cbd5e1;border-radius:2px"><select name="phone_country" style="' . $control . '">';
    foreach ($countries as $code => $label) {
      $html .= '<option value="' . $e($code) . '"' . ($code === 'NL' ? ' selected' : '') . '>' . $e($label) . '</option>';
    }
    $html .= '</select></span></label><label style="' . $field . '">Telefoonnummer<input name="phone_number" type="tel" autocomplete="tel" placeholder="06 12345678" style="' . $control . '"></label></div></section>'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Nationaliteit en taal</h4><div style="' . $grid . '">'
      . '<label style="' . $field . '">Nationaliteit<input name="nationality" placeholder="Bijv. PL" style="' . $control . '"></label>'
      . '<label style="' . $field . '">Voorkeurstaal<select name="preferred_language" style="' . $control . '">';
    foreach ($languages as $code => $label) {
      $html .= '<option value="' . $e($code) . '">' . $e($label) . '</option>';
    }
    $html .= '</select></label></div></section>'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Skills</h4>'
      . '<div style="' . $grid . '">';
    foreach ($skills as $skill) {
      $html .= '<label style="display:flex;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;border-radius:7px">'
        . '<input type="checkbox" name="skills[]" value="' . $e($skill) . '"> ' . $e($skill) . '</label>';
    }
    $html .= '</div></section><section><h4 style="margin:0 0 14px">Certificaten</h4>'
      . '<div style="' . $grid . '"><label style="' . $field . '">VCA geldig tot<input type="date" name="vca_expiry" style="' . $control . '"></label></div></section>'
      . '</fieldset><p style="margin:20px 0 0;color:#64748b">Gevoelige identiteitsgegevens worden pas toegevoegd met afzonderlijke toegangscontrole.</p>'
      . '</section>';
    return $html;
  }
}
