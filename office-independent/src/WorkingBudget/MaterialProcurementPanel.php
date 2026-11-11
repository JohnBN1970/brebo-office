<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Read-only HTML view of verified consolidated material and procurement data.
 * Caller enforces project ACL and supplies authoritative rows; no database writes.
 */
final class MaterialProcurementPanel
{
    public static function render(array $rows): string
    {
        $html = '<section class="office-material-procurement" aria-label="Materiaal en inkoop">'
            . '<h3>Materiaal &amp; Inkoop</h3>';
        if ($rows === []) return $html.'<p>Geen gecontroleerde materiaal- en inkoopgegevens beschikbaar.</p></section>';
        $html .= '<div style="overflow-x:auto"><table><thead><tr>'
            . '<th scope="col">Artikel</th><th scope="col">Specificatie</th><th scope="col">Eenheid</th>'
            . '<th scope="col">Begroot</th><th scope="col">Besteld</th><th scope="col">Ontvangen</th>'
            . '<th scope="col">Nog bestellen</th><th scope="col">Nog leveren</th><th scope="col">Overbesteld</th>'
            . '</tr></thead><tbody>';
        $fields = ['budget_quantity_milli','ordered_quantity_milli','received_quantity_milli',
            'remaining_to_order_milli','outstanding_delivery_milli','overordered_milli'];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('Invalid procurement row.');
            foreach (['article_code','specification','unit'] as $key) {
                if (!is_string($row[$key] ?? null)) throw new InvalidArgumentException('Invalid article identity.');
            }
            foreach ($fields as $key) {
                if (!is_int($row[$key] ?? null) || $row[$key] < 0) {
                    throw new InvalidArgumentException('Invalid procurement quantity.');
                }
            }
            $html .= '<tr><th scope="row">'.self::e($row['article_code']).'</th><td>'
                .self::e($row['specification']).'</td><td>'.self::e($row['unit']).'</td>';
            foreach ($fields as $key) {
                $html .= '<td>'.self::e(number_format($row[$key] / 1000, 3, ',', '.')).'</td>';
            }
            $html .= '</tr>';
        }
        return $html.'</tbody></table></div><p>Besteld en ontvangen zijn geen geboekte kosten of betalingen.</p></section>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
