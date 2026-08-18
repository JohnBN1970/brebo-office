<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/** Aggregates glass operational risk from positions to project level. */
final class GlassProjectRiskAggregator {
  public function __construct(private readonly GlassOperationalRiskEvaluator $riskEvaluator) {}

  /**
   * @param array<int,array<string,mixed>> $positions
   * @return array<int,array<string,mixed>> keyed by project nid
   */
  public function aggregate(array $positions): array {
    $projects=[];
    foreach($positions as $position){
      $projectId=(int)($position['project_nid']??0);
      if($projectId<=0) continue;
      $risk=$this->riskEvaluator->evaluate($position);
      $projects[$projectId]??=[
        'project_nid'=>$projectId,
        'positions'=>0,
        'critical'=>0,
        'attention'=>0,
        'action'=>0,
        'labour_hours_at_risk'=>0.0,
        'max_delay_days'=>0,
        'milestone_risk'=>FALSE,
        'top_score'=>0,
        'top_reason'=>'',
      ];
      $projects[$projectId]['positions']++;
      if($risk['level']==='kritiek') $projects[$projectId]['critical']++;
      elseif($risk['level']==='aandacht') $projects[$projectId]['attention']++;
      elseif($risk['level']==='actie') $projects[$projectId]['action']++;
      $projects[$projectId]['labour_hours_at_risk']+= (float)($risk['impact']['labour_hours_at_risk']??0.0);
      $projects[$projectId]['max_delay_days']=max($projects[$projectId]['max_delay_days'],(int)($risk['impact']['delay_days']??0));
      $projects[$projectId]['milestone_risk']=$projects[$projectId]['milestone_risk'] || !empty($risk['impact']['milestone_risk']);
      if((int)$risk['score']>$projects[$projectId]['top_score']){
        $projects[$projectId]['top_score']=(int)$risk['score'];
        $projects[$projectId]['top_reason']=implode('; ',$risk['reasons']);
      }
    }
    foreach($projects as &$project){
      $project['labour_hours_at_risk']=round($project['labour_hours_at_risk'],1);
      $project['level']=$project['critical']>0?'kritiek':($project['attention']>0?'aandacht':($project['action']>0?'actie':'normaal'));
      $project['summary']=sprintf('%d positie(s) · %d kritiek · %d aandacht · %.1f montage-uren risico%s',$project['positions'],$project['critical'],$project['attention'],$project['labour_hours_at_risk'],$project['milestone_risk']?' · mijlpaalrisico':'');
    }
    unset($project);
    uasort($projects,static fn(array $a,array $b):int=>[$b['top_score'],$b['critical'],$b['labour_hours_at_risk']]<=>[$a['top_score'],$a['critical'],$a['labour_hours_at_risk']]);
    return $projects;
  }
}
