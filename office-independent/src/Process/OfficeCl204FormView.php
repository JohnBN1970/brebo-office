<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Reusable CL-204 fragment for a host-authorized building/opnames screen.
 * The host owns authentication, CSRF, form processing and building/project lookup.
 * CL-204 is a projectopname, not a technical inspection assignment.
 * No direct route, DDL, write or CSS is introduced here.
 */
final class OfficeCl204FormView
{
    public static function render(array $draft = []): string
    {
        require_once __DIR__.'/OfficeCl204Projectopname.php';
        $controls = is_array($draft['controls'] ?? null) ? $draft['controls'] : [];
        $out = '<section class="office-process-form office-cl204" data-form-code="CL-204">';
        $out .= '<h2>CL-204 · Projectopname</h2><p>Algemene opname; meerdere disciplines mogelijk. Conceptgegevens zijn geen vrijgave.</p>';
        $out .= '<fieldset><legend>Opnamecontroles</legend>';
        foreach (OfficeCl204Projectopname::controls() as $key => $definition) {
            $item = is_array($controls[$key] ?? null) ? $controls[$key] : [];
            $status = (string)($item['status'] ?? 'open');
            $out .= '<div class="office-process-form__field"><label for="cl204_'.self::e($key).'">'.self::e($definition['label']).'</label>';
            $out .= '<p>'.self::e($definition['detail']).'</p><select id="cl204_'.self::e($key).'" name="cl204[controls]['.self::e($key).'][status]">';
            foreach (OfficeCl204Projectopname::applicability() as $choice) {
                $out .= '<option value="'.self::e($choice).'"'.($choice === $status ? ' selected' : '').'>'.self::e(str_replace('_',' ',$choice)).'</option>';
            }
            $out .= '</select></div>';
        }
        $out .= '</fieldset><fieldset><legend>Vrijgavestatus (alleen bevoegd besluit)</legend>';
        $out .= '<p>V1 t/m V6 wordt door de bevoegde beoordelaar vastgesteld; niet door dit conceptformulier.</p>';
        $out .= '</fieldset></section>';
        return $out;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
