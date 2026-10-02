<?php
declare(strict_types=1);
namespace Drupal\brebo_finance\Infrastructure;
use Drupal\brebo_finance\Contract\SupplierPerformanceRepositoryInterface;
use Drupal\Core\Database\Connection;
final class DatabaseSupplierPerformanceRepository implements SupplierPerformanceRepositoryInterface {
 public function __construct(private readonly Connection $database){}
 public function snapshotExists(int $p,string $s,string $d,string $v):bool{return(int)$this->database->select('brebo_finance_supplier_score_snapshot','x')->condition('project_nid',$p)->condition('supplier_ref',trim($s))->condition('snapshot_date',$d)->condition('policy_version',trim($v))->countQuery()->execute()->fetchField()>0;}
 public function orders(int $p,string $s):array{return$this->database->select('brebo_finance_commitment','c')->fields('c',['id','amount_ex_vat','delivery_date'])->condition('project_nid',$p)->condition('supplier_ref',trim($s))->condition('status','cancelled','<>')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
 public function receipts(int $p,array $ids):array{if($ids===[])return[];$q=$this->database->select('brebo_finance_performance_receipt','r');$q->join('brebo_finance_commitment_line','l','l.id = r.commitment_line_id');$q->join('brebo_finance_commitment','c','c.id = l.commitment_id');$q->fields('r',['id','performance_date','quality_accepted']);$q->addField('c','delivery_date');$q->condition('r.project_nid',$p)->condition('r.status','verified')->condition('c.id',$ids,'IN');return$q->execute()->fetchAll(\PDO::FETCH_ASSOC);}
 public function invoices(int $p,string $s):array{return$this->database->select('brebo_finance_purchase_invoice','i')->fields('i',['id','amount_ex_vat','match_status'])->condition('project_nid',$p)->condition('supplier_ref',trim($s))->condition('status','cancelled','<>')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
 public function invoiceVariance(array $ids):string{if($ids===[])return'0.0000';$q=$this->database->select('brebo_finance_purchase_invoice_line','l')->condition('invoice_id',$ids,'IN');$q->addExpression('COALESCE(SUM(ABS(variance_amount_ex_vat)), 0)','total');return(string)$q->execute()->fetchField();}
 public function failureCost(int $p,string $s):string{$q=$this->database->select('brebo_finance_failure_cost','f')->condition('project_nid',$p)->condition('responsible_party_ref',trim($s))->condition('status',['validated','recovery_pending','closed'],'IN');$q->addExpression('COALESCE(SUM(net_failure_cost_ex_vat), 0)','total');return(string)$q->execute()->fetchField();}
 public function createSnapshot(array $snapshot,array $audit):int{$tx=$this->database->startTransaction();try{$id=(int)$this->database->insert('brebo_finance_supplier_score_snapshot')->fields($snapshot)->execute();$audit['entity_id']=$id;$this->database->insert('brebo_finance_audit')->fields($audit)->execute();return$id;}catch(\Throwable $e){$tx->rollBack();throw$e;}}
}
