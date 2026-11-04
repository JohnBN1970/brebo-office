<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;
use InvalidArgumentException;

/** Server-rendered form fragment; embedding route must provide CSRF and authorize POST. */
final class OfficeCommercialContextForm {
    public static function render(array $context, string $action, string $csrf): string {
        if ($action === '' || $csrf === '') throw new InvalidArgumentException('Form action and CSRF required');
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $position = $context['position_code'] ?? '';
        $categories = $context['work_categories'] ?? [];
        if (!is_array($categories)) $categories = [];
        $html = '<form method="post" action="' . $e($action) . '"><input type="hidden" name="csrf" value="' . $e($csrf) . '">';
        $html .= '<fieldset><legend>Commerciele positie BREBO</legend>';
        foreach (OfficeCommercialCalcHandoff::POSITIONS as $code) {
            $html .= '<label><input type="radio" name="commercial_position" value="' . $e($code) . '"'
                . ($position === $code ? ' checked' : '') . ' required>' . $e(ucfirst($code)) . '</label>';
        }
        $html .= '</fieldset><fieldset><legend>Werkcategorieen</legend>';
        foreach (OfficeCommercialCalcHandoff::ACTIVITIES as $code) {
            $html .= '<label><input type="checkbox" name="work_categories[]" value="' . $e($code) . '"'
                . (in_array($code, $categories, true) ? ' checked' : '') . '>' . $e(ucfirst($code)) . '</label>';
        }
        $html .= '</fieldset><button type="submit">Commerciele gegevens opslaan</button></form>';
        return $html;
    }
}
