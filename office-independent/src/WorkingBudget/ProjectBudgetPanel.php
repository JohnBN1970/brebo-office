<?php
declare(strict_types=1);

namespace Brebo\Office\WorkingBudget;

use InvalidArgumentException;

/**
 * Server-rendered, read-only budget panel for an existing authorized Office
 * project page. Caller MUST enforce project ACL and escape output context.
 * No database writes, no external API and no invented financial totals.
 */
final class ProjectBudgetPanel
{
    public static function render(array $view): string
    {
        $projectId = $view['project_id'] ?? null;
        if (!is_int($projectId) || $projectId < 1 || !isset($view['kpis'], $view['source_status'])) {
            throw new InvalidArgumentException('Invalid project budget view.');
        }
        $kpis = $view['kpis'];
        $status = $view['source_status'];
        $fields = [
            'baseline_cents'=>'Vastgestelde werkbegroting',
            'awarded_cents'=>'Verstrekte inkoopopdrachten',
            'actual_cents'=>'Geboekte kosten',
            'open_commitment_cents'=>'Openstaande verplichtingen',
            'forecast_cents'=>'Verwachte eindkosten',
            'variance_cents'=>'Begrotingsafwijking',
        ];
        $html = '<section class="office-work-budget" aria-label="Werkbegroting">';
        $html .= '<h2>Werkbegroting</h2><div class="office-work-budget__kpis">';
        foreach ($fields as $key=>$label) {
            $amount = $kpis[$key] ?? null;
            if ($amount !== null && !is_int($amount)) throw new InvalidArgumentException('Invalid KPI value.');
            $value = $amount === null ? 'Nog niet beschikbaar' : '€ '.number_format($amount / 100, 2, ',', '.');
            $html .= '<div class="office-work-budget__kpi"><strong>'.self::e($label).'</strong><span>'.self::e($value).'</span></div>';
        }
        $html .= '</div><h3>Bronstatus</h3><dl>';
        foreach ($status as $source=>$state) {
            if (!is_scalar($source) || !is_scalar($state)) throw new InvalidArgumentException('Invalid source state.');
            $html .= '<dt>'.self::e((string)$source).'</dt><dd>'.self::e((string)$state).'</dd>';
        }
        $html .= '</dl>';
        $labor = $view['labor_evidence'] ?? null;
        if ($labor !== null) {
            if (!is_array($labor) || !in_array($labor['state'] ?? null,
                ['unavailable', 'provisional', 'exceptions_present'], true)
                || !array_key_exists('labor_actual_cents', $labor)
                || $labor['labor_actual_cents'] !== null) {
                throw new InvalidArgumentException('Invalid provisional labor evidence.');
            }
            $html .= '<h3>OnSite – urenregistratie</h3>';
            if ($labor['state'] === 'unavailable') {
                $html .= '<p>Urenregistratie niet beschikbaar.</p>';
            } else {
                $seconds = $labor['duration_seconds'] ?? null;
                $sessions = $labor['session_count'] ?? null;
                if (!is_int($seconds) || $seconds < 0 || !is_int($sessions) || $sessions < 0) {
                    throw new InvalidArgumentException('Invalid labor duration.');
                }
                $html .= '<p>Geregistreerde aanwezigheid: '
                    . self::e(number_format($seconds / 3600, 2, ',', '.'))
                    . ' uur in '.self::e((string)$sessions).' sessies.</p>';
                if ($labor['state'] === 'exceptions_present') {
                    $html .= '<p>Er zijn afwijkingen in de urenregistratie.</p>';
                }
                $html .= '<p>Voorlopige registratie; niet goedgekeurd of als arbeidskosten geboekt.</p>';
            }
        }
        $html .= '<h3>Begrotingsregels</h3>';
        $lines = $view['cost_lines'] ?? [];
        if (!is_array($lines)) throw new InvalidArgumentException('Invalid budget lines.');
        if ($lines === []) {
            $html .= '<p>Geen vastgestelde begrotingsregels beschikbaar.</p>';
        } else {
            $types = ['labor'=>'Arbeid', 'material'=>'Materiaal', 'equipment'=>'Materieel',
                'subcontract'=>'Onderaanneming', 'other'=>'Overige kosten'];
            $totals = array_fill_keys(array_keys($types), 0);
            $html .= '<table><thead><tr><th scope="col">Regel</th><th scope="col">Kostensoort</th>'
                . '<th scope="col">Activiteit</th><th scope="col">Begroot excl. btw</th></tr></thead><tbody>';
            foreach ($lines as $line) {
                if (!is_array($line) || !is_string($line['code'] ?? null)
                    || !isset($types[$line['cost_type'] ?? ''])
                    || !is_int($line['baseline_cents'] ?? null) || $line['baseline_cents'] < 0) {
                    throw new InvalidArgumentException('Invalid budget line.');
                }
                if ($line['baseline_cents'] > PHP_INT_MAX - $totals[$line['cost_type']]) {
                    throw new InvalidArgumentException('Budget cost type total overflow.');
                }
                $totals[$line['cost_type']] += $line['baseline_cents'];
                $activity = $line['activity_ref'] ?? '';
                if (!is_string($activity)) throw new InvalidArgumentException('Invalid activity.');
                $html .= '<tr><td>'.self::e($line['code']).'</td><td>'
                    . self::e($types[$line['cost_type']]).'</td><td>'.self::e($activity)
                    . '</td><td>€ '.number_format($line['baseline_cents'] / 100, 2, ',', '.').'</td></tr>';
            }
            $html .= '</tbody></table><h3>Kostenverdeling</h3><dl>';
            foreach ($types as $type=>$label) {
                $html .= '<dt>'.self::e($label).'</dt><dd>€ '
                    .number_format($totals[$type] / 100, 2, ',', '.').'</dd>';
            }
            $html .= '</dl>';
        }
        $materials = $view['material_requirements'] ?? null;
        if ($materials !== null) {
            if (!is_array($materials) || !isset($materials['groups'], $materials['unmatched'])
                || !is_array($materials['groups']) || !is_array($materials['unmatched'])) {
                throw new InvalidArgumentException('Invalid material requirements.');
            }
            $html .= '<h3>Materiaalstaat</h3>';
            if ($materials['groups'] === []) {
                $html .= '<p>Geen gekoppelde materiaalartikelen beschikbaar.</p>';
            } else {
                $html .= '<table><thead><tr><th scope="col">Artikel</th><th scope="col">Specificatie</th>'
                    . '<th scope="col">Begrote hoeveelheid</th><th scope="col">Eenheid</th>'
                    . '<th scope="col">Bronregels</th></tr></thead><tbody>';
                foreach ($materials['groups'] as $group) {
                    if (!is_array($group) || !is_string($group['article_identity'] ?? null)
                        || !is_string($group['specification'] ?? null) || !is_string($group['unit'] ?? null)
                        || !is_int($group['budget_quantity'] ?? null) || $group['budget_quantity'] < 0
                        || !is_int($group['quantity_scale'] ?? null) || $group['quantity_scale'] < 1
                        || !is_array($group['allocations'] ?? null)) {
                        throw new InvalidArgumentException('Invalid material group.');
                    }
                    $quantity = number_format($group['budget_quantity'] / $group['quantity_scale'], 3, ',', '.');
                    $html .= '<tr><td>'.self::e($group['article_identity']).'</td><td>'
                        .self::e($group['specification']).'</td><td>'.self::e($quantity)
                        .'</td><td>'.self::e($group['unit']).'</td><td>';
                    $sources = [];
                    foreach ($group['allocations'] as $allocation) {
                        if (!is_array($allocation) || (!is_string($allocation['source_line_id'] ?? null)
                            && !is_int($allocation['source_line_id'] ?? null))) {
                            throw new InvalidArgumentException('Invalid material allocation.');
                        }
                        $sources[] = self::e((string)$allocation['source_line_id']);
                    }
                    $html .= implode(', ', $sources).'</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            if ($materials['unmatched'] !== []) {
                $html .= '<p>'.count($materials['unmatched'])
                    .' materiaalregels vragen handmatige artikelmatching.</p>';
            }
            $html .= '<p>Besteld, ontvangen en resterend worden pas weergegeven na aansluiting op gecontroleerde inkoopgegevens.</p>';
        }
        $html .= '<p>Prognoses en afwijkingen worden pas getoond wanneer alle benodigde bronnen zijn gecontroleerd.</p></section>';
        return $html;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
