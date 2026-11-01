<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Read-only personnel profile preview for an already authorized manager.
 * No POST route, account creation or OnSite activation is exposed.
 */
final class OfficePersonnelProfileForm {
  public static function render(int $personId, string $displayName, ?string $personnelNumber = null, ?bool $active = null): string {
    if ($personId < 1 || trim($displayName) === '') {
      throw new \InvalidArgumentException('Verified employee required');
    }
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $countries = ['NL' => ['Nederland', '+31'], 'BE' => ['België', '+32'], 'DE' => ['Duitsland', '+49'], 'PL' => ['Polen', '+48'], 'RO' => ['Roemenië', '+40'], 'TR' => ['Turkije', '+90']];
    // Inline SVG is compatible with Office's restrictive CSP; native select options cannot reliably render flags.
    $flags = [
      'NL' => ['#ae1c28','#ffffff','#21468b'],
      'DE' => ['#000000','#dd0000','#ffce00'],
      'PL' => ['#ffffff','#dc143c'],
      'RO' => ['#002b7f','#fcd116','#ce1126'],
      'BE' => ['#000000','#fdda24','#ef3340'],
    ];
    $flag = static function (string $country) use ($flags): string {
      $svg = '<svg aria-hidden="true" width="30" height="20" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg">';
      if ($country === 'TR') {
        return $svg . '<rect width="30" height="20" fill="#e30a17"/><circle cx="12" cy="10" r="6" fill="#fff"/><circle cx="14" cy="10" r="4.7" fill="#e30a17"/><path d="M19 5.8l1.2 2.7 3 .2-2.3 2 0.7 3-2.6-1.6-2.6 1.6.7-3-2.3-2 3-.2z" fill="#fff"/></svg>';
      }
      $colors = $flags[$country];
      foreach ($colors as $i => $color) {
        $vertical = in_array($country, ['BE','RO'], true);
        $size = ($vertical ? 30 : 20) / count($colors);
        $svg .= '<rect ' . ($vertical ? 'x="' . ($i * $size) . '" width="' . $size . '" height="20"' : 'y="' . ($i * $size) . '" height="' . $size . '" width="30"') . ' fill="' . $color . '"/>';
      }
      return $svg . '</svg>';
    };
    $languages = ['nl' => 'Nederlands', 'en' => 'English', 'pl' => 'Polski', 'ro' => 'Română', 'tr' => 'Türkçe', 'de' => 'Deutsch'];
    $skills = ['Glaszetten', 'Kozijnmontage', 'Timmeren', 'Schilderen', 'Kitten', 'Projectleiding'];
    $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;align-items:start';
    $field = 'display:flex;flex-direction:column;gap:6px;font-weight:600;color:#25364a';
    $control = 'width:100%;max-width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;color:#475569;font:inherit';
    $html = '<section aria-label="Medewerkersdossier" style="border:1px solid #dbe1e8;border-radius:12px;padding:clamp(16px,3vw,28px);margin:18px 0;background:#fff">'
      . '<h3 style="margin:0 0 8px">Medewerkersdossier · ' . $e($displayName) . '</h3>'
      . '<p style="margin:0 0 8px;color:#475569">Personeelsnummer: <strong>' . $e($personnelNumber ?: 'Niet toegewezen') . '</strong></p>'
      . '<p style="margin:0 0 12px;color:#475569">Status: <strong>' . ($active === true ? 'Actief' : ($active === false ? 'Inactief' : 'Nog niet bevestigd')) . '</strong></p>'
      . '<p style="margin:0 0 20px;color:#64748b">Conceptweergave. Opslaan en OnSite-activatie zijn nog niet beschikbaar vanuit dit formulier.</p>'
      . '<fieldset disabled style="border:0;margin:0;padding:0;min-width:0">'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Dienstverband</h4><label style="display:flex;align-items:center;gap:10px"><input type="checkbox" name="active" value="1"' . ($active === true ? ' checked' : '') . '> Actief</label></section>'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Contactgegevens</h4>'
      . '<style>.phone-flag{display:none}.phone-code-picker:has(select option[value="NL"]:checked) .phone-flag-NL,.phone-code-picker:has(select option[value="BE"]:checked) .phone-flag-BE,.phone-code-picker:has(select option[value="DE"]:checked) .phone-flag-DE,.phone-code-picker:has(select option[value="PL"]:checked) .phone-flag-PL,.phone-code-picker:has(select option[value="RO"]:checked) .phone-flag-RO,.phone-code-picker:has(select option[value="TR"]:checked) .phone-flag-TR{display:inline-flex}.phone-code-picker{display:flex;align-items:center;gap:10px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;padding:0 10px}.phone-code-picker select{border:0;background:transparent;min-width:0;flex:1;padding:10px 0;font:inherit;color:#475569}.phone-code-picker select:focus{outline:2px solid #175a91;outline-offset:2px}</style>'
      . '<div style="' . $grid . '"><label style="' . $field . '">Landcode telefoon<span class="phone-code-picker">';
    foreach ($countries as $code => [$name, $dial]) {
      $html .= '<span class="phone-flag phone-flag-' . $e($code) . '">' . $flag($code) . '</span>';
    }
    $html .= '<select name="phone_country" aria-label="Landcode telefoon">';
    foreach ($countries as $code => [$name, $dial]) {
      $html .= '<option value="' . $e($code) . '"' . ($code === 'NL' ? ' selected' : '') . '>' . $e($name . ' (' . $dial . ')') . '</option>';
    }
    $html .= '</select></span></label><label style="' . $field . '">Telefoonnummer<input name="phone_number" type="tel" autocomplete="tel" placeholder="06 12345678" style="' . $control . '"></label></div></section>'
      . '<section style="margin-bottom:24px"><h4 style="margin:0 0 14px">Nationaliteit en taal</h4><div style="' . $grid . '">'
      . '<label style="' . $field . '">Nationaliteit<select name="nationality" style="' . $control . '"><option value="">Kies nationaliteit</option>';
    foreach ($countries as $code => [$name, $dial]) {
      $html .= '<option value="' . $e($code) . '">' . $e($name) . '</option>';
    }
    $html .= '</select></label>'
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
