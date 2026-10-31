<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

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
                if (!is_string($module) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $module) || !is_array($configuration)) {
                    continue;
                }
                $enabled = !empty($configuration['enabled']);
                $selected = (array) ($configuration['permissions'] ?? []);
                $scope = (string) ($configuration['scope'] ?? 'none');
                $html .= '<form method="post" action="' . $escape($action) . '"><fieldset><legend>' . $escape($module) . '</legend>';
                $html .= '<input type="hidden" name="csrf_token" value="' . $escape($csrfToken) . '">';
                $html .= '<input type="hidden" name="role_id" value="' . $roleId . '">';
                $html .= '<input type="hidden" name="module" value="' . $escape($module) . '">';
                $html .= '<label><input type="checkbox" name="enabled" value="1"' . ($enabled ? ' checked' : '') . '> Module inschakelen</label>';
                $html .= '<details' . ($enabled ? ' open' : '') . '><summary>Rechten en filters</summary>';
                foreach (['read' => 'Bekijken', 'create' => 'Aanmaken', 'update' => 'Wijzigen', 'issue' => 'Uitbrengen', 'record_acceptance' => 'Acceptatie registreren', 'link_change' => 'Meerwerk koppelen', 'manage' => 'Beheren'] as $permission => $label) {
                    $html .= '<label><input type="checkbox" name="permissions[]" value="' . $permission . '"' . (in_array($permission, $selected, true) ? ' checked' : '') . '> ' . $escape($label) . '</label>';
                }
                $html .= '<label>Gegevensfilter <select name="scope">';
                foreach (['none' => 'Geen toegang', 'own' => 'Eigen gegevens', 'assigned' => 'Toegewezen projecten', 'all' => 'Alle gegevens'] as $key => $label) {
                    $html .= '<option value="' . $key . '"' . ($scope === $key ? ' selected' : '') . '>' . $escape($label) . '</option>';
                }
                $html .= '</select></label></details><button type="submit">Opslaan</button></fieldset></form>';
            }
        }
        return $html . '</section>';
    }
}
