<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Core\Database\Connection;

/** Computes operational urgency and project impact for glass positions. */
final class GlassOperationalRiskEvaluator {
  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $position @return array{score:int,level:string,reasons:array<int,string>,impact:array<string,mixed>} */
  public function evaluate(array $position): array {
    $score=0;$reasons=[];$state=(string)($position['technical_status']??'concept');$id=(int)($position['id']??0);
    $impact=['delay_days'=>0,'labour_hours_at_risk'=>0.0,'milestone_risk'=>FALSE,'summary'=>'Geen directe projectimpact berekend'];
    if(($position['technical_check_state']??'')==='blocked'){$score+=100;$reasons[]='Technisch geblokkeerd';$impact['milestone_risk']=TRUE;}
    elseif(($position['technical_check_state']??'')==='expert_review'){$score+=70;$reasons[]='Deskundige beoordeling nodig';}
    if($state==='measured'){$score+=45;$reasons[]='Wacht op technische vrijgave';}
    if($state==='approved'&&!$this->hasProcurementRequest($id)){$score+=40;$reasons[]='Vrijgegeven maar nog niet naar inkoop';}
    if($state==='ordered'){
      $delivery=$this->deliveryStatus($id);
      if($delivery['late']){
        $score+=90;$reasons[]='Levering te laat sinds '.$delivery['date'];
        $impact['delay_days']=$delivery['delay_days'];
        $impact['labour_hours_at_risk']=$this->estimatedInstallationHours($position);
        $impact['milestone_risk']=$delivery['delay_days']>=2;
        $impact['summary']=sprintf('%d dag(en) leververtraging · %.1f montage-uren potentieel geblokkeerd%s',$delivery['delay_days'],$impact['labour_hours_at_risk'],$impact['milestone_risk']?' · mijlpaalrisico':'');
      } elseif($delivery['today']){$score+=35;$reasons[]='Levering vandaag verwacht';$impact['labour_hours_at_risk']=$this->estimatedInstallationHours($position);$impact['summary']=sprintf('Levering vandaag · %.1f montage-uren afhankelijk van ontvangst',$impact['labour_hours_at_risk']);}
      elseif($delivery['date']===NULL){$score+=50;$reasons[]='Besteld zonder bevestigde leverdatum';$impact['labour_hours_at_risk']=$this->estimatedInstallationHours($position);$impact['milestone_risk']=TRUE;$impact['summary']=sprintf('Leverdatum onbekend · %.1f montage-uren niet planzeker',$impact['labour_hours_at_risk']);}
    }
    if($state==='delivered'){$score+=25;$reasons[]='Geleverd en klaar voor montage';$impact['summary']='Materiaal beschikbaar; montage kan worden vrijgegeven';}
    $level=$score>=80?'kritiek':($score>=40?'aandacht':($score>0?'actie':'normaal'));
    return ['score'=>$score,'level'=>$level,'reasons'=>$reasons,'impact'=>$impact];
  }

  private function estimatedInstallationHours(array $position): float {
    $qty=max(1,(int)($position['quantity']??1));$area=max(0.0,(float)($position['area_m2']??0));$weight=max(0.0,(float)($position['estimated_weight_kg']??0));
    $hours=(0.35+($area*0.30)+($weight>100?0.75:($weight>50?0.25:0.0)))*$qty;
    return round($hours,1);
  }

  private function hasProcurementRequest(int $positionId): bool {
    if(!$this->database->schema()->tableExists('brebo_procurement_request_line'))return FALSE;
    return(bool)$this->database->select('brebo_procurement_request_line','l')->condition('source_domain','brebo_glass_position')->condition('source_reference',(string)$positionId)->countQuery()->execute()->fetchField();
  }

  /** @return array{date:?string,late:bool,today:bool,delay_days:int} */
  private function deliveryStatus(int $positionId): array {
    $result=['date'=>NULL,'late'=>FALSE,'today'=>FALSE,'delay_days'=>0];
    if(!$this->database->schema()->tableExists('brebo_procurement_order')||!$this->database->schema()->tableExists('brebo_procurement_request_line'))return$result;
    $query=$this->database->select('brebo_procurement_request_line','l');$query->innerJoin('brebo_procurement_order','o','o.request_id = l.request_id');$query->addField('o','expected_delivery_date');
    $query->condition('l.source_domain','brebo_glass_position')->condition('l.source_reference',(string)$positionId)->condition('o.status','ordered')->orderBy('o.id','DESC')->range(0,1);
    $date=$query->execute()->fetchField();if(!$date)return$result;$today=date('Y-m-d');$late=(string)$date<$today;
    $delay=$late?(int)(new \DateTimeImmutable((string)$date))->diff(new \DateTimeImmutable($today))->days:0;
    return['date'=>(string)$date,'late'=>$late,'today'=>(string)$date===$today,'delay_days'=>$delay];
  }
}
