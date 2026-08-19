<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/** Aggregates personal actions from BREBO Office domains into one work queue. */
final class PersonalWorkQueue {
  public function __construct(private readonly InvoiceBlockerActionManager $financeActions) {}

  public function build(int $uid): array {
    $finance=$this->financeActions->ownerSummary($uid);
    $items=[];
    foreach($finance['items'] as $item){
      $items[]=[
        'domain'=>'finance',
        'source'=>'invoice_blocker',
        'id'=>(int)$item['id'],
        'project_nid'=>(int)$item['project_nid'],
        'title'=>(string)$item['action'],
        'status'=>(string)$item['status'],
        'due_date'=>$item['due_date']===null?null:(int)$item['due_date'],
        'urgency'=>(string)$item['urgency'],
        'days_overdue'=>(int)$item['days_overdue'],
        'href'=>'/brebo-office/finance/projects/'.(int)$item['project_nid'],
        'meta'=>['invoice_line_id'=>(int)$item['invoice_line_id']],
      ];
    }
    usort($items,static function(array $a,array $b):int{
      $rank=['overdue'=>0,'today'=>1,'upcoming'=>2,'no_deadline'=>3];
      $r=($rank[$a['urgency']]??9)<=>($rank[$b['urgency']]??9);
      if($r!==0)return $r;
      return ($a['due_date']??PHP_INT_MAX)<=>($b['due_date']??PHP_INT_MAX);
    });
    $summary=['total'=>count($items),'overdue'=>0,'today'=>0,'upcoming'=>0,'no_deadline'=>0,'domains'=>[]];
    foreach($items as $item){$summary[$item['urgency']]++;$summary['domains'][$item['domain']]=($summary['domains'][$item['domain']]??0)+1;}
    return ['summary'=>$summary,'items'=>$items,'domains'=>['finance'=>['label'=>'Finance','count'=>$summary['domains']['finance']??0]]];
  }
}
