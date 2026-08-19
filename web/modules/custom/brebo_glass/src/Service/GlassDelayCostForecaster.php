<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/** Forecasts consequence costs without inventing unsupported price rates. */
final class GlassDelayCostForecaster {
  public function __construct(private readonly GlassFinancialImpactEvaluator $financialEvaluator) {}

  /** @param array<string,mixed> $position @param array<string,mixed> $risk */
  public function forecast(array $position,array $risk): array {
    $financial=$this->financialEvaluator->evaluate((int)($position['id']??0));
    $hours=(float)($risk['impact']['labour_hours_at_risk']??0.0);
    $days=(int)($risk['impact']['delay_days']??0);
    $rate=$financial['labour_hour_rate'];
    $idleLabour=$rate!==NULL?round($hours*(float)$rate,2):NULL;
    $base=(float)$financial['total_cost'];
    $knownConsequence=$idleLabour??0.0;
    $expected=round($base+$knownConsequence,2);
    $unknown=[];
    if($days>0){$unknown[]='herplanning';$unknown[]='eventuele versnelling';}
    if($rate===NULL&&$hours>0)$unknown[]='stilstand arbeid';
    if((float)$financial['equipment_cost']>0&&$days>0)$unknown[]='verlengd materieelgebruik';
    $confidence=$financial['priced']&&$rate!==NULL?'B':($financial['priced']?'C':'D');
    $summary=$financial['priced']?'Basis € '.number_format($base,2,',','.'):'Basis nog ongeprijsd';
    if($idleLabour!==NULL&&$idleLabour>0)$summary.=' · bekende stilstand € '.number_format($idleLabour,2,',','.');
    if($unknown)$summary.=' · nog te prijzen: '.implode(', ',$unknown);
    return ['base_value'=>$base,'idle_labour_cost'=>$idleLabour,'known_consequence_cost'=>$knownConsequence,'expected_known_exposure'=>$expected,'unknown_cost_drivers'=>$unknown,'confidence_class'=>$confidence,'summary'=>$summary];
  }
}
