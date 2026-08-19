<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_calculation\Service\CalculationReadinessInspector;
use Drupal\brebo_calculation\Service\CalculationWorkAssignmentManager;
use Drupal\Core\Database\Connection;

/** Aggregates personal actions from BREBO Office domains into one work queue. */
final class PersonalWorkQueue {
  public function __construct(
    private readonly InvoiceBlockerActionManager $financeActions,
    private readonly CalculationWorkAssignmentManager $calculationActions,
    private readonly CalculationReadinessInspector $calculationReadiness,
    private readonly Connection $database,
  ) {}

  public function build(int $uid): array {
    $finance=$this->financeActions->ownerSummary($uid);$calculations=$this->calculationActions->forOwner($uid);$items=[];
    foreach($finance['items'] as $item){$items[]=['domain'=>'finance','source'=>'invoice_blocker','id'=>(int)$item['id'],'project_nid'=>(int)$item['project_nid'],'title'=>(string)$item['action'],'status'=>(string)$item['status'],'due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/brebo-office/finance/projects/'.(int)$item['project_nid'],'meta'=>['invoice_line_id'=>(int)$item['invoice_line_id']]];}
    foreach($calculations as $item){
      $calcId=(int)$item['calculation_id'];$items[]=['domain'=>'calculation','source'=>'calculation_assignment','id'=>(int)$item['id'],'project_nid'=>null,'title'=>(string)$item['action'],'status'=>(string)$item['status'],'due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/admin/brebo/calculations/'.$calcId.'/workbench','meta'=>['calculation_id'=>$calcId]];
      $version=$this->latestCalculationVersion($calcId);if($version===null)continue;$readiness=$this->calculationReadiness->inspect($calcId,$version);
      if(($readiness['blocking']??0)>0){$items[]=['domain'=>'calculation','source'=>'calculation_readiness','id'=>'readiness-'.$calcId.'-'.$version,'project_nid'=>null,'title'=>'Calculatie geblokkeerd: '.(int)$readiness['blocking'].' fout(en) oplossen vóór offertevrijgave','status'=>'blocked','due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>$item['urgency']==='no_deadline'?'today':(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/admin/brebo/calculations/'.$calcId.'/readiness','meta'=>['calculation_id'=>$calcId,'version'=>$version,'blocking'=>(int)$readiness['blocking'],'warnings'=>(int)$readiness['warnings'],'checks'=>$readiness['checks']]];}
      elseif(($readiness['warnings']??0)>0){$items[]=['domain'=>'calculation','source'=>'calculation_readiness','id'=>'readiness-'.$calcId.'-'.$version,'project_nid'=>null,'title'=>'Calculatie controleren: '.(int)$readiness['warnings'].' waarschuwing(en)','status'=>'review','due_date'=>$item['due_date']===null?null:(int)$item['due_date'],'urgency'=>(string)$item['urgency'],'days_overdue'=>(int)$item['days_overdue'],'href'=>'/admin/brebo/calculations/'.$calcId.'/readiness','meta'=>['calculation_id'=>$calcId,'version'=>$version,'blocking'=>0,'warnings'=>(int)$readiness['warnings'],'checks'=>$readiness['checks']]];}
    }
    usort($items,static function(array $a,array $b):int{$rank=['overdue'=>0,'today'=>1,'upcoming'=>2,'no_deadline'=>3];$r=($rank[$a['urgency']]??9)<=>($rank[$b['urgency']]??9);if($r!==0)return$r;$blocked=(($b['status']??'')==='blocked'?1:0)<=>(($a['status']??'')==='blocked'?1:0);if($blocked!==0)return$blocked;return($a['due_date']??PHP_INT_MAX)<=>($b['due_date']??PHP_INT_MAX);});
    $summary=['total'=>count($items),'overdue'=>0,'today'=>0,'upcoming'=>0,'no_deadline'=>0,'domains'=>[],'blocked'=>0];foreach($items as $item){$summary[$item['urgency']]++;$summary['domains'][$item['domain']]=($summary['domains'][$item['domain']]??0)+1;if(($item['status']??'')==='blocked')$summary['blocked']++;}
    return ['summary'=>$summary,'items'=>$items,'domains'=>['finance'=>['label'=>'Finance','count'=>$summary['domains']['finance']??0],'calculation'=>['label'=>'Calculatie','count'=>$summary['domains']['calculation']??0]]];
  }

  private function latestCalculationVersion(int $calculationId): ?string {
    if(!$this->database->schema()->tableExists('brebo_calculation_version'))return null;$version=$this->database->select('brebo_calculation_version','v')->fields('v',['version'])->condition('calculation_id',$calculationId)->orderBy('version','DESC')->range(0,1)->execute()->fetchField();return $version===false?null:(string)$version;
  }
}
