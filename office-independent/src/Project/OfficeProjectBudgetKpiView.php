<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/** Read-only dashboard presentation for an already authorized project. */
final class OfficeProjectBudgetKpiView
{
    public static function render(array $comparison): string
    {
        $state=$comparison['state']??null;
        if (!in_array($state,['baseline_missing','comparable'],true)) throw new \InvalidArgumentException('Ongeldige begrotingsstatus.');
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $fmt=static function(mixed $cents):string {
            if (!is_int($cents)) return 'Niet beschikbaar';
            return '€ '.number_format($cents/100,2,',','.');
        };
        $awarded=$comparison['commitment_cents']??null;
        if (!is_int($awarded)||$awarded<0)throw new \InvalidArgumentException('Ongeldige verplichtingen.');
        $budget=$comparison['budget_cents']??null;
        $remaining=$comparison['remaining_cents']??null;
        if ($state==='comparable' && (!is_int($budget)||$budget<0||!is_int($remaining))) {
            throw new \InvalidArgumentException('Ongeldige begrotingsvergelijking.');
        }
        if ($state==='baseline_missing' && ($budget!==null||$remaining!==null)) {
            throw new \InvalidArgumentException('Ontbrekende begroting mag geen nulwaarde zijn.');
        }
        $signal=$state==='baseline_missing'?'onbekend':($remaining<0?'rood':($remaining===0?'geel':'groen'));
        $cards=[
            'Vastgestelde inkoopbegroting'=>$fmt($budget),
            'Verstrekte inkoopopdrachten'=>$fmt($awarded),
            'Nog beschikbaar'=>$fmt($remaining),
            'Budgetstatus'=>ucfirst($signal),
        ];
        $html='<section class="project-budget-kpis" aria-label="Inkoopbudgetbewaking"><h2>Inkoopbudgetbewaking</h2><div class="project-v3-kpis">';
        foreach($cards as $label=>$value) {
            $html.='<article><small>'.$e($label).'</small><strong>'.$e($value).'</strong></article>';
        }
        return $html.'</div><p>Verplichtingen zijn geen werkelijk geboekte kosten. Bedragen exclusief btw.</p></section>';
    }
}
