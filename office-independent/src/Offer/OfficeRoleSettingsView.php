<?php
declare(strict_types=1);

namespace Brebo\Office\Offer;

require_once __DIR__ . '/OfficeRoleSettingsModuleCatalog.php';

/** Server-rendered settings fragment; caller must enforce settings.manage. */
final class OfficeRoleSettingsView
{
    public static function render(array $roles, array $modules, string $action, string $csrfToken): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="office-role-settings"><h1>Instellingen / Rollen en rechten</h1>';
        $html .= '<p>Moduletoegang, handelingen en gegevensfilters per rol.</p>';
        foreach ($roles as $role) {
            if (!is_array($role) || !isset($role['id'], $role['label'])) {
                continue;
            }
            $roleId = (int) $role['id'];
            if ($roleId < 1) {
                continue;
            }
            $html .= '<h2>' . $escape((string) $role['label']) . '</h2>';
            foreach ($modules as $module => $configuration) {
                if (!is_string($module) || !is_array($configuration)) {
                    continue;
                }
                $labels = OfficeRoleSettingsModuleCatalog::labels($module);
                if ($labels === []) {
                    continue;
                }
                $enabled = !empty($configuration['enabled']);
                $selected = (array) ($configuration['permissions'] ?? []);
                $scope = (string) ($configuration['scope'] ?? 'none');
                $html .= '<form method="post" action="' . $escape($action) . '"><fieldset><legend>' . $escape($module) . '</legend>';
                $html .= '<input type="hidden" name="csrf_token" value="' . $escape($csrfToken) . '">';
                $html .= '<input type="hidden" name="role_id" value="' . $roleId . '">';
                $html .= '<input type="hidden" name="module" value="' . $escape($module) . '">';
                $html .= '<input type="hidden" name="enabled" value="0">';
                $html .= '<label><input type="checkbox" name="enabled" value="1"' . ($enabled ? ' checked' : '') . '> Module inschakelen</label>';
                $html .= '<details' . ($enabled ? ' open' : '') . '><summary>Rechten en filters</summary>';
                foreach ($labels as $permission => $label) {
                    $html .= '<label><input type="checkbox" name="permissions[]" value="' . $escape($permission) . '"' . (in_array($permission, $selected, true) ? ' checked' : '') . '> ' . $escape($label) . '</label>';
                }
                if ($module === 'offers') {
                    $html .= '<label>Gegevensfilter <select name="scope">';
                    foreach (['none' => 'Geen toegang', 'assigned' => 'Toegewezen projecten', 'all' => 'Alle geautoriseerde projecten'] as $key => $label) {
                        $html .= '<option value="' . $key . '"' . ($scope === $key ? ' selected' : '') . '>' . $escape($label) . '</option>';
                    }
                    $html .= '</select></label><p>Projecttoegang blijft altijd beperkt door de bestaande Office-projectrechten. De filters worden nog niet als zelfstandige projectselectie toegepast.</p>';
                } else {
                    $html .= '<input type="hidden" name="scope" value="none">';
                    $html .= '<p>Rolrechten worden pas effectief nadat de Office-roltoewijzing aan medewerkers is aangesloten. Bankverzending blijft afzonderlijk geblokkeerd.</p>';
                }
                $html .= '</details><button type="submit">Opslaan</button></fieldset></form>';
            }
        }
        return $html . '</section>';
    }
}
