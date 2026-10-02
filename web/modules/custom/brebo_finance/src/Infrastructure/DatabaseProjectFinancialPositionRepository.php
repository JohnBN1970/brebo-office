<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ProjectFinancialPositionRepositoryInterface;
use Drupal\Core\Database\Connection;
use UnexpectedValueException;

final class DatabaseProjectFinancialPositionRepository implements ProjectFinancialPositionRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function values(int $p):array{
    $contract=$this->single('brebo_finance_project_contract','amount_ex_vat',['project_nid'=>$p,'status'=>'approved']);
    $rev=$this->sum('brebo_finance_revenue_mutation','amount_ex_vat',['project_nid'=>$p,'status'=>'approved']);
    $budgetId=$this->single('brebo_finance_budget','id',['project_nid'=>$p,'budget_type'=>'working','status'=>'locked']);
    return ['contract_revenue'=>$contract,'revenue_mutations'=>$rev,'baseline_cost'=>$this->sum('brebo_finance_budget_line','amount_ex_vat',['budget_id'=>(int)$budgetId]),'budget_mutations'=>$this->sum('brebo_finance_budget_mutation','amount_ex_vat',['project_nid'=>$p,'status'=>'approved']),'committed'=>$this->sumExcluded('brebo_finance_commitment','amount_ex_vat',$p,['cancelled']),'verified_performance'=>$this->sum('brebo_finance_performance_receipt','amount_ex_vat',['project_nid'=>$p,'status'=>'verified']),'invoiced'=>$this->sumExcluded('brebo_finance_purchase_invoice','amount_ex_vat',$p,['cancelled']),'paid_inc_vat'=>$this->sum('brebo_finance_payment_release','total_amount',['project_nid'=>$p,'status'=>'executed'])];
  }
  public function createSnapshot(array $fields):int{return(int)$this->database->insert('brebo_finance_forecast_snapshot')->fields($fields)->execute();}
  private function sum(string $t,string $f,array $c):string{$q=$this->database->select($t,'t');foreach($c as$n=>$v)$q->condition($n,$v);$q->addExpression("COALESCE(SUM($f), 0)",'total');return(string)$q->execute()->fetchField();}
  private function sumExcluded(string $t,string $f,int $p,array $x):string{$q=$this->database->select($t,'t')->condition('project_nid',$p)->condition('status',$x,'NOT IN');$q->addExpression("COALESCE(SUM($f), 0)",'total');return(string)$q->execute()->fetchField();}
  private function single(string $t,string $f,array $c):string{$q=$this->database->select($t,'t')->fields('t',[$f]);foreach($c as$n=>$v)$q->condition($n,$v);$v=$q->range(0,1)->execute()->fetchField();if($v===FALSE)throw new UnexpectedValueException("Required financial source $t is missing.");return(string)$v;}
  public function sourceStateHash(int $p):string{$tables=['brebo_finance_project_contract','brebo_finance_revenue_mutation','brebo_finance_budget','brebo_finance_commitment','brebo_finance_performance_receipt','brebo_finance_purchase_invoice','brebo_finance_payment_release','brebo_finance_billing_instalment','brebo_finance_sales_invoice','brebo_finance_sales_invoice_draft','brebo_finance_sales_invoice_outbox','brebo_finance_budget_mutation','brebo_finance_contract_obligation','brebo_finance_failure_cost','brebo_finance_change_order','brebo_finance_provisional_sum'];$s=$this->database->schema();$state=[];foreach($tables as$t){if(!$s->tableExists($t)||!$s->fieldExists($t,'project_nid'))continue;$q=$this->database->select($t,'t')->fields('t')->condition('project_nid',$p);if($s->fieldExists($t,'id'))$q->orderBy('id','ASC');$state[$t]=$q->execute()->fetchAll();}if($s->tableExists('brebo_finance_budget_line')&&$s->tableExists('brebo_finance_budget')){$q=$this->database->select('brebo_finance_budget_line','l');$q->join('brebo_finance_budget','b','b.id = l.budget_id');$q->fields('l')->condition('b.project_nid',$p)->orderBy('l.id','ASC');$state['brebo_finance_budget_line']=$q->execute()->fetchAll();}return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));}
}
