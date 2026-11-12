<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Presents already authorized native records. All views read the same source. */
final class OfficeCrmFunnelViews
{
    private static function h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function render(array $items,string $view,string $base='/office-test/crm/funnel',?string $month=null,bool $marketing=false):string
    {
        $view=OfficeCrmFunnel::normalizeView($view);$out='<nav class="crm-view-switch" aria-label="CRM-weergave">';
        foreach(['kanban'=>'Kanban','list'=>'Lijst','calendar'=>'Kalender'] as $key=>$label)$out.='<a'.($key===$view?' aria-current="page"':'').' href="'.self::h($base).'?view='.$key.'">'.$label.'</a> ';
        $out.='</nav>';
        if($items===[])$out.='<p class="crm-muted">Nog geen '.($marketing?'leads':'verkoopkansen').' in deze weergave.</p>';
        if($view==='kanban'){
            $stages=$marketing?['lead','lost']:array_values(array_diff(array_keys(OfficeCrmFunnel::STAGES),['lead']));
            $groups=array_fill_keys($stages,[]);foreach($items as $item)if(isset($groups[$item['stage']??'']))$groups[$item['stage']][]=$item;
            $out.='<div class="crm-kanban" style="display:flex;gap:12px;overflow-x:auto">';
            foreach($groups as $stage=>$rows){$out.='<section style="min-width:210px;flex:1"><h2>'.self::h(OfficeCrmFunnel::label($stage)).' ('.count($rows).')</h2>';foreach($rows as $item)$out.=self::card($item);$out.='</section>';}
            return $out.'</div>';
        }
        if($view==='list'){
            $out.='<table><thead><tr><th>Onderwerp / relatie</th><th>Fase</th><th>Volgende actie</th><th>Datum</th></tr></thead><tbody>';
            foreach($items as $item)$out.='<tr><td>'.self::link($item).'<br><small>'.self::h($item['relation_name']??'').'</small></td><td>'.self::h(OfficeCrmFunnel::label($item['stage']??'')).'</td><td>'.self::h($item['next_action']??'').'</td><td>'.self::h($item['due_date']??'').'</td></tr>';
            return $out.'</tbody></table>';
        }
        $dates=[];foreach($items as $item){$date=(string)($item['due_date']??'');if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)&&checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))$dates[$date][]=$item;}
        // Without an explicit month, preserve backwards-compatible agenda rendering for consumers.
        if($month===null){ksort($dates);$out.='<div class="crm-calendar">';foreach($dates as $date=>$rows){$out.='<section><h2>'.self::h($date).'</h2>';foreach($rows as $item)$out.=self::card($item);$out.='</section>';}return $out.'</div>';}
        if(!preg_match('/^\d{4}-(?:0[1-9]|1[0-2])$/D',$month))$month=date('Y-m');
        $first=new \DateTimeImmutable($month.'-01');$prev=$first->modify('-1 month')->format('Y-m');$next=$first->modify('+1 month')->format('Y-m');
        $out.='<nav class="crm-view-switch" aria-label="Kalendermaand"><a href="'.self::h($base).'?view=calendar&amp;month='.$prev.'">Vorige maand</a><strong>'.self::h($first->format('m-Y')).'</strong><a href="'.self::h($base).'?view=calendar&amp;month='.$next.'">Volgende maand</a></nav><div class="crm-calendar-grid">';
        for($n=1;$n<(int)$first->format('N');$n++)$out.='<div class="crm-calendar-empty"></div>';
        for($n=1;$n<=(int)$first->format('t');$n++){$date=$first->format('Y-m-').str_pad((string)$n,2,'0',STR_PAD_LEFT);$out.='<div class="crm-calendar-day"><h3>'.self::h($date).'</h3>';foreach($dates[$date]??[] as $item)$out.=self::card($item);$out.='</div>';}
        return $out.'</div>';
    }
    private static function link(array $item):string
    {
        $id=filter_var($item['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $name=self::h($item['name']??'');
        if(!is_int($id))return $name;
        $detail='<a href="/office-test/crm/verkoopkansen/detail?id='.$id.'">'.$name.'</a>';
        // Preserve the existing guarded Calc launch action present on active Office-test.
        $calcEnabled=getenv('OFFICE_BASE_PATH')==='/office-test'
            && getenv('OFFICE_TEST_CRM_ENABLED')==='enabled'
            && getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')==='enabled'
            && getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')==='enabled'
            && in_array((string)($item['stage']??''),['prospect','opportunity','proposal','negotiation'],true);
        return $calcEnabled
            ? $detail.' · <a href="/office-test/calculatie/start?opportunity_id='.$id.'">Calculatie</a>'
            : $detail;
    }
    private static function card(array $item):string
    {
        return '<article style="padding:10px;margin:8px 0;background:#f4f6f9;border-radius:6px"><strong>'.self::link($item).'</strong><p>'.self::h($item['relation_name']??'').'</p><p>'.self::h($item['next_action']??'').'</p><small>'.self::h($item['scheduled_at']??$item['due_date']??'').(!empty($item['completed_at'])?' · Afgerond':'').'</small></article>';
    }
}
