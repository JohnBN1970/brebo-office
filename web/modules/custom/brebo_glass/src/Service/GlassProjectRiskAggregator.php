<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/** Aggregates glass operational and financial risk to project level. */
final class GlassProjectRiskAggregator {
  public function __construct(
    private readonly GlassOperationalRiskEvaluator $riskEvaluator,
    private readonly GlassFinancialImpactEvaluator $financialEvaluator,
  ) {}

  /** @param array<int,array<string,mixed>> $positions @return array<int,array<string,mixed>> */
  public function aggregate(array $positions): array {
    $projects=[];
    foreach($positions as$position){
      $projectId=(int)($position['project_nid']??0); if($projectId<=0)continue;
      $risk=$this->riskEvaluator->evaluate($position);
      $financial=$this->financialEvaluator->evaluate((int)$position['id']);
      $projects[$projectId]??=[
        'project_nid'=>$projectId,'positions'=>0,'critical'=>0,'attention'=>0,'action'=>0,'labour_hours_at_risk'=>0.0,
        'financial_value_at_risk'=>0.0,'priced_risk_positions'=>0,'unpriced_risk_positions'=>0,'labour_value_at_risk'=>0.0,
        'max_delay_days'=>0,'milestone_risk'=>FALSE,'top_score'=>0,'top_reason'=>'',
      ];
      $projects[$projectId]['positions']++;
      if($risk['level']==='kritiek')$projects[$projectId]['critical']++;elseif($risk['level']==='aandacht')$projects[$projectId]['attention']++;elseif($risk['level']==='actie')$projects[$projectId]['action']++;
      $hours=(float)($risk['impact']['labour_hours_at_risk']??0.0);$projects[$projectId]['labour_hours_at_risk']+=$hours;
      $projects[$projectId]['max_delay_days']=max($projects[$projectId]['max_delay_days'],(int)($risk['impact']['delay_days']??0));
      $projects[$projectId]['milestone_risk']=$projects[$projectId]['milestone_risk']||!empty($risk['impact']['milestone_risk']);
      if($risk['score']>0){
        if($financial['priced']){
          $projects[$projectId]['financial_value_at_risk']+=(float)$financial['total_cost'];
          $projects[$projectId]['priced_risk_positions']++;
          if($financial['labour_hour_rate']!==NULL)$projects[$projectId]['labour_value_at_risk']+=$hours*(float)$financial['labour_hour_rate'];
        } else $projects[$projectId]['unpriced_risk_positions']++;
      }
      if((int)$risk['score']>$projects[$projectId]['top_score']){$projects[$projectId]['top_score']=(int)$risk['score'];$projects[$projectId]['top_reason']=implode('; ',$risk['reasons']);}
    }
    foreach($projects as&$project){
      $project['labour_hours_at_risk']=round($project['labour_hours_at_risk'],1);
      $project['financial_value_at_risk']=round($project['financial_value_at_risk'],2);
      $project['labour_value_at_risk']=round($project['labour_value_at_risk'],2);
      $project['level']=$project['critical']>0?'kritiek':($project['attention']>0?'aandacht':($project['action']>0?'actie':'normaal'));
      $money=$project['priced_risk_positions']>0?' · € '.number_format((float)$project['financial_value_at_risk'],2,',','.').' calculatiewaarde risico':'';
      $unpriced=$project['unpriced_risk_positions']>0?' · '.$project['unpriced_risk_positions'].' risicopositie(s) nog ongeprijsd':'';
      $project['summary']=sprintf('%d positie(s) · %d kritiek · %d aandacht · %.1f montage-uren risico%s%s%s',$project['positions'],$project['critical'],$project['attention'],$project['labour_hours_at_risk'],$money,$unpriced,$project['milestone_risk']?' · mijlpaalrisico':'');
    }
    unset($project);
    uasort($projects,static fn(array$a,array$b):int=>[$b['top_score'],$b['critical'],$b['financial_value_at_risk'],$b['labour_hours_at_risk']]<=>[$a['top_score'],$a['critical'],$a['financial_value_at_risk'],$a['labour_hours_at_risk']]);
    return$projects;
  }
}
