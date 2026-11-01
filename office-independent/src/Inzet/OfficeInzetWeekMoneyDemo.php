<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Office-test-only illustrative figures; no database reads or writes. */
final class OfficeInzetWeekMoneyDemo {
  public static function render(array $sessions, \DateTimeImmutable $monday, callable $escape, array $projectNames): string {
    // Internal employee rates are fictional. Sales rates are fictional role/calculation rates.
    $purchase = [900001=>50.00,900002=>42.50,900003=>47.50];
    $roleCalculation = [900001=>75.00,900002=>65.00,900003=>70.00];
    $days = [];
    for ($i=0;$i<7;$i++) $days[]=$monday->modify("+$i days");
    $people=[]; $projects=[]; $totalCost=0.; $totalValue=0.; $totalHours=0.;
    foreach ($sessions as $session) {
      $person=(int)($session['person_id']??0);
      $project=(int)($session['project_id']??0);
      if (!isset($purchase[$person],$roleCalculation[$person]) || $project<1) continue;
      try {
        $from=new \DateTimeImmutable((string)$session['started_at']);
        $until=new \DateTimeImmutable((string)$session['ended_at']);
      } catch (\Throwable $error) { continue; }
      if ($until<=$from) continue;
      foreach ($days as $date) {
        $start=$date->setTime(0,0)->getTimestamp();
        $end=$date->modify('+1 day')->setTime(0,0)->getTimestamp();
        $hours=max(0,min($until->getTimestamp(),$end)-max($from->getTimestamp(),$start))/3600;
        if ($hours<=0) continue;
        $key=$date->format('Y-m-d');
        $cost=$hours*$purchase[$person]; $value=$hours*$roleCalculation[$person];
        $people[$person]['days'][$key]=($people[$person]['days'][$key]??0)+$cost;
        $people[$person]['hours']=($people[$person]['hours']??0)+$hours;
        $people[$person]['cost']=($people[$person]['cost']??0)+$cost;
        $people[$person]['value']=($people[$person]['value']??0)+$value;
        $projects[$person][$project]['days'][$key]=($projects[$person][$project]['days'][$key]??0)+$cost;
        $projects[$person][$project]['cost']=($projects[$person][$project]['cost']??0)+$cost;
        $projects[$person][$project]['value']=($projects[$person][$project]['value']??0)+$value;
        $totalHours+=$hours; $totalCost+=$cost; $totalValue+=$value;
      }
    }
    $money=static fn(float $value):string=>'€ '.number_format($value,2,',','.');
    $cards=[
      ['Geregistreerde uren',number_format($totalHours,2,',','.').' u'],
      ['Inkoopkosten',$money($totalCost)],
      ['Calculatiewaarde',$money($totalValue)],
      ['Brutomarge',$money($totalValue-$totalCost)],
      ['Margepercentage',$totalValue>0?number_format(($totalValue-$totalCost)/$totalValue*100,1,',','.').'%':'—'],
      ['Medewerkers', (string)count($people)],
    ];
    $out='<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px;margin:16px 0">';
    foreach ($cards as [$label,$value]) {
      $out.='<div style="border:1px solid #dbe4ed;background:#f0f5fa;border-radius:12px;padding:14px"><div style="font-size:12px;color:#526477">'.$escape($label).'</div><div style="font-size:24px;font-weight:800;color:#185b8f;margin:8px 0">'.$escape($value).'</div></div>';
    }
    $out.='</div><h3>Medewerkers · financiële weekweergave</h3><div style="overflow-x:auto"><table style="width:100%;min-width:850px;border-spacing:5px"><thead><tr><th>Medewerker</th>';
    foreach ($days as $date) $out.='<th>'.$escape(['ma','di','wo','do','vr','za','zo'][(int)$date->format('N')-1].' '.$date->format('d-m')).'</th>';
    $out.='<th>Totaal inkoop</th></tr></thead><tbody>';
    ksort($people);
    foreach ($people as $person=>$data) {
      $out.='<tr><th style="text-align:left">Medewerker #'.(int)$person.'</th>';
      foreach ($days as $date) {
        $cost=(float)($data['days'][$date->format('Y-m-d')]??0);
        $out.='<td style="background:#f4f6fa;border-radius:8px;text-align:center;padding:10px">'.$escape($money($cost)).'</td>';
      }
      $out.='<td>'.$escape($money($data['cost'])).'</td></tr>';
      $detail='<table style="width:100%"><thead><tr><th>Project</th><th>Inkoop</th><th>Calculatiewaarde</th><th>Brutomarge</th></tr></thead><tbody>';
      foreach ($projects[$person]??[] as $project=>$values) {
        $detail.='<tr><td>'.$escape((string)($projectNames[$project]??('Project #'.$project))).'</td><td>'.$escape($money($values['cost'])).'</td><td>'.$escape($money($values['value'])).'</td><td>'.$escape($money($values['value']-$values['cost'])).'</td></tr>';
      }
      $out.='<tr><td colspan="9"><details><summary>Projecten van medewerker #'.(int)$person.' · calculatiewaarde '.$escape($money($data['value'])).'</summary>'.$detail.'</tbody></table></details></td></tr>';
    }
    if (!$people) $out.='<tr><td colspan="9">Geen demo-uren beschikbaar.</td></tr>';
    return $out.'</tbody></table></div><p><strong>DEMO:</strong> inkooptarieven en rol-/calculatietarieven zijn volledig fictief. Geen echte financiële resultaten, planning of facturatie.</p>';
  }
}
