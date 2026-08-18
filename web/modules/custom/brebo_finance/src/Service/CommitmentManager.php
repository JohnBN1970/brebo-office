<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Creates controlled purchase commitments against a locked working budget. */
final class CommitmentManager {
  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $vatCalculator,
    private readonly FinancialPhaseGateManager $phaseGateManager,
    private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer,
  ) {}

  public function createDraft(int $projectNid, string $commitmentNumber, string $supplierName, ?string $supplierRef, int $userId): int {
    if (trim($commitmentNumber) === '' || trim($supplierName) === '') throw new InvalidArgumentException('Commitment number and supplier are required.');
    if (!$this->hasLockedWorkingBudget($projectNid)) throw new RuntimeException('Purchasing is blocked until the working budget baseline is locked.');
    $this->phaseGateManager->requireRelease($projectNid, 'procurement_release');
    $now = time();
    return (int) $this->database->insert('brebo_finance_commitment')->fields([
      'project_nid'=>$projectNid,'commitment_number'=>trim($commitmentNumber),'supplier_ref'=>$supplierRef,'supplier_name'=>trim($supplierName),'status'=>'draft',
      'amount_ex_vat'=>'0.0000','vat_amount'=>'0.0000','amount_inc_vat'=>'0.0000','currency'=>'EUR','created'=>$now,'created_by'=>$userId,'changed'=>$now,'changed_by'=>$userId,
    ])->execute();
  }

  public function addLine(int $commitmentId, int $budgetLineId, string $description, string $quantity, string $unit, string $unitPriceExVat, string $vatRate, bool $reverseCharge, string $nonDeductibleVatPercentage, int $userId): int {
    $commitment=$this->loadDraftCommitment($commitmentId); $this->phaseGateManager->requireRelease((int)$commitment['project_nid'],'procurement_release'); $budgetLine=$this->loadLockedBudgetLine($budgetLineId,(int)$commitment['project_nid']);
    $quantityValue=$this->positiveDecimal($quantity,'quantity'); $unitPriceValue=$this->positiveDecimal($unitPriceExVat,'unitPriceExVat'); $amountExVat=$this->vatCalculator->multiply($quantityValue,$unitPriceValue);
    $remaining=$this->remainingBudget($budgetLineId,(string)$budgetLine['amount_ex_vat']); if($this->vatCalculator->compare($amountExVat,$remaining)>0) throw new RuntimeException(sprintf('Commitment exceeds the remaining working-budget amount by EUR %s.',$this->vatCalculator->subtract($amountExVat,$remaining)));
    $vat=$this->vatCalculator->calculate($amountExVat,$vatRate,$reverseCharge,$nonDeductibleVatPercentage); $transaction=$this->database->startTransaction();
    try {
      $lineNumber=$this->nextLineNumber($commitmentId); $now=time();
      $lineId=(int)$this->database->insert('brebo_finance_commitment_line')->fields([
        'commitment_id'=>$commitmentId,'budget_line_id'=>$budgetLineId,'line_number'=>$lineNumber,'description'=>trim($description)!==''?trim($description):$budgetLine['description'],'quantity'=>$quantityValue,'unit'=>trim($unit)!==''?trim($unit):NULL,
        'unit_price_ex_vat'=>$unitPriceValue,'amount_ex_vat'=>$vat->amountExVat,'vat_code'=>$reverseCharge?'NL_REVERSE':'NL_'.str_replace('.0000','',$vat->vatRate),'vat_rate'=>$vat->vatRate,'vat_amount'=>$vat->vatAmount,'amount_inc_vat'=>$vat->amountIncVat,
        'vat_reverse_charge'=>$vat->reverseCharge?1:0,'delivered_amount_ex_vat'=>'0.0000','invoiced_amount_ex_vat'=>'0.0000','created'=>$now,'created_by'=>$userId,'changed'=>$now,'changed_by'=>$userId,
      ])->execute();
      $this->refreshCommitmentTotals($commitmentId,$now,$userId);
      $this->audit((int)$commitment['project_nid'],$commitmentId,'commitment_line_added',['line_id'=>$lineId,'budget_line_id'=>$budgetLineId,'amount_ex_vat'=>$vat->amountExVat,'vat_amount'=>$vat->vatAmount,'amount_inc_vat'=>$vat->amountIncVat,'reverse_charge'=>$vat->reverseCharge,'remaining_budget_after'=>$this->vatCalculator->subtract($remaining,$amountExVat)],$now,$userId);
      $this->euroTraceSynchronizer->sync('commitment',$commitmentId,$userId);
      return $lineId;
    } catch (\Throwable $exception) { $transaction->rollBack(); throw $exception; }
  }

  private function hasLockedWorkingBudget(int $projectNid): bool { return (bool)$this->database->select('brebo_finance_budget','b')->condition('project_nid',$projectNid)->condition('budget_type','working')->condition('status','locked')->countQuery()->execute()->fetchField(); }
  private function loadDraftCommitment(int $commitmentId): array { $r=$this->database->select('brebo_finance_commitment','c')->fields('c')->condition('id',$commitmentId)->execute()->fetchAssoc(); if($r===FALSE||$r['status']!=='draft') throw new UnexpectedValueException('A draft commitment is required.'); return $r; }
  private function loadLockedBudgetLine(int $budgetLineId,int $projectNid):array{$q=$this->database->select('brebo_finance_budget_line','l');$q->join('brebo_finance_budget','b','b.id = l.budget_id');$r=$q->fields('l')->condition('l.id',$budgetLineId)->condition('b.project_nid',$projectNid)->condition('b.budget_type','working')->condition('b.status','locked')->execute()->fetchAssoc();if($r===FALSE)throw new UnexpectedValueException('The commitment line must reference the locked working budget.');return$r;}
  private function remainingBudget(int $budgetLineId,string $budgetAmount):string{$q=$this->database->select('brebo_finance_commitment_line','l');$q->join('brebo_finance_commitment','c','c.id = l.commitment_id');$q->condition('l.budget_line_id',$budgetLineId)->condition('c.status',['cancelled'],'NOT IN')->addExpression('COALESCE(SUM(l.amount_ex_vat), 0)','committed_total');$committed=(string)$q->execute()->fetchField();$mq=$this->database->select('brebo_finance_budget_mutation_line','ml');$mq->join('brebo_finance_budget_mutation','m','m.id = ml.mutation_id');$mq->condition('ml.budget_line_id',$budgetLineId)->condition('m.status','approved')->addExpression('COALESCE(SUM(ml.adjustment_ex_vat), 0)','approved_adjustment');$adj=(string)$mq->execute()->fetchField();return$this->vatCalculator->subtract($this->vatCalculator->add($budgetAmount,$adj),$committed);}
  private function nextLineNumber(int $commitmentId):int{$q=$this->database->select('brebo_finance_commitment_line','l');$q->condition('commitment_id',$commitmentId)->addExpression('COALESCE(MAX(line_number), 0) + 1','next_line');return(int)$q->execute()->fetchField();}
  private function refreshCommitmentTotals(int $commitmentId,int $now,int $userId):void{$q=$this->database->select('brebo_finance_commitment_line','l');$q->condition('commitment_id',$commitmentId)->addExpression('COALESCE(SUM(amount_ex_vat), 0)','amount_ex_vat')->addExpression('COALESCE(SUM(vat_amount), 0)','vat_amount')->addExpression('COALESCE(SUM(amount_inc_vat), 0)','amount_inc_vat');$t=$q->execute()->fetchAssoc();$this->database->update('brebo_finance_commitment')->fields(['amount_ex_vat'=>$t['amount_ex_vat'],'vat_amount'=>$t['vat_amount'],'amount_inc_vat'=>$t['amount_inc_vat'],'changed'=>$now,'changed_by'=>$userId])->condition('id',$commitmentId)->execute();}
  private function positiveDecimal(string $value,string $field):string{$n=str_replace(',','.',trim($value));try{if($this->vatCalculator->compare($n,'0')<=0)throw new InvalidArgumentException("$field must be greater than zero.");}catch(InvalidArgumentException){throw new InvalidArgumentException("$field must be a positive decimal with at most four decimal places.");}return$n;}
  private function audit(int $projectNid,int $commitmentId,string $action,array $payload,int $now,int $userId):void{$this->database->insert('brebo_finance_audit')->fields(['project_nid'=>$projectNid,'entity_type'=>'commitment','entity_id'=>$commitmentId,'action'=>$action,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'reason'=>'Controlled purchase commitment after financial procurement phase gate against locked working budget.','created'=>$now,'created_by'=>$userId])->execute();}
}
