<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_office_core\Service\RiskEscalationManager;

/** Routes material glass risks to BREBO project, planning and procurement audiences. */
final class GlassRiskEscalationService {
  public function __construct(
    private readonly GlassOperationalRiskEvaluator $riskEvaluator,
    private readonly GlassDelayCostForecaster $costForecaster,
    private readonly RiskEscalationManager $escalations,
  ) {}

  /** @param array<string,mixed> $position */
  public function evaluateAndEscalate(array $position): ?int {
    $risk=$this->riskEvaluator->evaluate($position);
    if(!in_array($risk['level'],['kritiek','aandacht'],TRUE)) return NULL;
    $forecast=$this->costForecaster->forecast($position,$risk);
    $hours=(float)($risk['impact']['labour_hours_at_risk']??0.0);
    $money=(float)($forecast['expected_known_exposure']??0.0);
    $milestone=!empty($risk['impact']['milestone_risk']);

    if(!$milestone && $hours < 8.0 && $money < 1000.0 && $risk['level']!=='kritiek') return NULL;

    $audiences=['projectleiding'];
    if($hours>=8.0 || $milestone) $audiences[]='planning';
    if(in_array((string)($position['technical_status']??''),['approved','ordered'],TRUE)) $audiences[]='inkoop';

    $title=sprintf('Glasrisico %s · positie %s',strtoupper($risk['level']),(string)($position['position_code']??$position['id']??'?'));
    $payload=[
      'project_nid'=>(int)($position['project_nid']??0),
      'building_nid'=>(int)($position['building_nid']??0),
      'position_id'=>(int)($position['id']??0),
      'position_code'=>(string)($position['position_code']??''),
      'risk'=>$risk,
      'forecast'=>$forecast,
    ];
    return $this->escalations->escalate('brebo_glass_position',(string)($position['id']??0),$risk['level'],$title,$payload,$audiences);
  }
}
