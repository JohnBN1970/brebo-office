<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_calculation\Service\CalculationWorkAssignmentManager;

/** Aggregates personal actions from BREBO Office domains into one work queue. */
final class PersonalWorkQueue {
  public function __construct(private readonly InvoiceBlockerActionManager $financeActions, private readonly CalculationWorkAssignmentManager $calculationActions) {}

  public function build(int $uid): array {
    $finance=$this->financeActions->ownerSummary($uid);$calculations=$this->calculationActions->forOwner($uid);$items=[];
    foreach($finance['items'] as $item){$items[]=['domain'=>'finance','source'=>'invoice_blocker','id'=>(int)$item['id'],'project_nid'=>(int)$item['project_nid'],'title'=>(string)$item['action'],'status'=>(string)$item['status'],'due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/brebo-office/finance/projects/'.(int)$item['project_nid'],'meta'=>['invoice_line_id'=>(int)$item['invoice_line_id']]];}
    foreach($calculations as $item){$items[]=['domain'=>'calculation','source'=>'calculation_assignment','id'=>(int)$item['id'],'project_nid'=>null,'title'=>(string)$item['action'],'status'=>(string)$item['status'],'due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/admin/brebo/calculations/'.(int)$item['calculation_id'].'/workbench','meta'=>['calculation_id'=>(int)$item['calculation_id']]];}
    usort($items,static function(array $a,array $b):int{$rank=['overdue'=>0,'today'=>1,'upcoming'=>2,'no_deadline'=>3];$r=($rank[$a['urgency']]??9)<=>($rank[$b['urgency']]??9);if($r!==0)return$r;return($a['due_date']??PHP_INT_MAX)<=>($b['due_date']??PHP_INT_MAX);});
    $summary=['total'=>count($items),'overdue'=>0,'today'=>0,'upcoming'=>0,'no_deadline'=>0,'domains'=>[]];foreach($items as $item){$summary[$item['urgency']]++;$summary['domains'][$item['domain']]=($summary['domains'][$item['domain']]??0)+1;}
    return ['summary'=>$summary,'items'=>$items,'domains'=>['finance'=>['label'=>'Finance','count'=>$summary['domains']['finance']??0],'calculation'=>['label'=>'Calculatie','count'=>$summary['domains']['calculation']??0]]];
  }
}
