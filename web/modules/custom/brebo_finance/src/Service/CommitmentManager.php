<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_office_core\Service\ProjectDocumentNumberIssuer;
use Drupal\brebo_finance\Contract\CommitmentRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Creates controlled purchase commitments against a locked working budget. */
final class CommitmentManager {
  public function __construct(
    private readonly CommitmentRepositoryInterface $repository,
    private readonly VatCalculator $vatCalculator,
    private readonly FinancialPhaseGateManager $phaseGateManager,
    private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectDocumentNumberIssuer $documentNumberIssuer,
  ) {}

  public function createDraft(int $projectNid, string $supplierName, ?string $supplierRef, int $userId): int {
    if (trim($supplierName) === '') {
      throw new InvalidArgumentException('Supplier is required.');
    }
    if (!$this->hasLockedWorkingBudget($projectNid)) {
      throw new RuntimeException('Purchasing is blocked until the working budget baseline is locked.');
    }
    $this->phaseGateManager->requireRelease($projectNid, 'procurement_release');

    $project = $this->entityTypeManager->getStorage('node')->load($projectNid);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      throw new UnexpectedValueException('A BREBO project is required for commitment numbering.');
    }

    $now = time();
    $temporaryNumber = 'PENDING-' . strtoupper(bin2hex(random_bytes(6)));
    $commitmentId = $this->repository->insertCommitment([
      'project_nid' => $projectNid,
      'commitment_number' => $temporaryNumber,
      'supplier_ref' => $supplierRef,
      'supplier_name' => trim($supplierName),
      'status' => 'draft',
      'amount_ex_vat' => '0.0000',
      'vat_amount' => '0.0000',
      'amount_inc_vat' => '0.0000',
      'currency' => 'EUR',
      'created' => $now,
      'created_by' => $userId,
      'changed' => $now,
      'changed_by' => $userId,
    ]);

    try {
      $receipt = $this->documentNumberIssuer->issueAssignment($project, (string) $commitmentId, (int) date('Y', $now));
      $commitmentNumber = trim((string) ($receipt['number'] ?? ''));
      if ($commitmentNumber === '') {
        throw new RuntimeException('Administration-aware assignment numbering returned no number.');
      }
      $updated = $this->repository->updateCommitment($commitmentId, [
        'commitment_number' => $commitmentNumber,
        'changed' => time(),
        'changed_by' => $userId,
      ]);
      if ((int) $updated !== 1) {
        throw new RuntimeException('Commitment number could not be persisted.');
      }
      $this->audit($projectNid, $commitmentId, 'commitment_created', [
        'commitment_number' => $commitmentNumber,
        'number_receipt' => $receipt,
        'supplier_ref' => $supplierRef,
        'supplier_name' => trim($supplierName),
      ], time(), $userId);
    }
    catch (\Throwable $exception) {
      $current = $this->repository->commitmentNumber($commitmentId);
      if ($current === $temporaryNumber) {
        $this->repository->deleteCommitmentWithNumber($commitmentId, $temporaryNumber);
      }
      throw $exception;
    }

    return $commitmentId;
  }

  public function addLine(int $commitmentId, int $budgetLineId, string $description, string $quantity, string $unit, string $unitPriceExVat, string $vatRate, bool $reverseCharge, string $nonDeductibleVatPercentage, int $userId): int {
    $commitment=$this->loadDraftCommitment($commitmentId); $this->phaseGateManager->requireRelease((int)$commitment['project_nid'],'procurement_release'); $budgetLine=$this->loadLockedBudgetLine($budgetLineId,(int)$commitment['project_nid']);
    $quantityValue=$this->positiveDecimal($quantity,'quantity'); $unitPriceValue=$this->positiveDecimal($unitPriceExVat,'unitPriceExVat'); $amountExVat=$this->vatCalculator->multiply($quantityValue,$unitPriceValue);
    $remaining=$this->remainingBudget($budgetLineId,(string)$budgetLine['amount_ex_vat']); if($this->vatCalculator->compare($amountExVat,$remaining)>0) throw new RuntimeException(sprintf('Commitment exceeds the remaining working-budget amount by EUR %s.',$this->vatCalculator->subtract($amountExVat,$remaining)));
    $vat=$this->vatCalculator->calculate($amountExVat,$vatRate,$reverseCharge,$nonDeductibleVatPercentage); return $this->repository->transactional(function () use ($commitmentId,$budgetLineId,$description,$quantityValue,$unit,$unitPriceValue,$vat,$userId,$commitment,$remaining,$amountExVat): int {
      $lineNumber=$this->repository->nextLineNumber($commitmentId); $now=time();
      $lineId=$this->repository->insertLine([
        'commitment_id'=>$commitmentId,'budget_line_id'=>$budgetLineId,'line_number'=>$lineNumber,'description'=>trim($description)!==''?trim($description):$budgetLine['description'],'quantity'=>$quantityValue,'unit'=>trim($unit)!==''?trim($unit):NULL,
        'unit_price_ex_vat'=>$unitPriceValue,'amount_ex_vat'=>$vat->amountExVat,'vat_code'=>$reverseCharge?'NL_REVERSE':'NL_'.str_replace('.0000','',$vat->vatRate),'vat_rate'=>$vat->vatRate,'vat_amount'=>$vat->vatAmount,'amount_inc_vat'=>$vat->amountIncVat,
        'vat_reverse_charge'=>$vat->reverseCharge?1:0,'delivered_amount_ex_vat'=>'0.0000','invoiced_amount_ex_vat'=>'0.0000','created'=>$now,'created_by'=>$userId,'changed'=>$now,'changed_by'=>$userId,
      ]);
      $this->refreshCommitmentTotals($commitmentId,$now,$userId);
      $this->audit((int)$commitment['project_nid'],$commitmentId,'commitment_line_added',['line_id'=>$lineId,'budget_line_id'=>$budgetLineId,'amount_ex_vat'=>$vat->amountExVat,'vat_amount'=>$vat->vatAmount,'amount_inc_vat'=>$vat->amountIncVat,'reverse_charge'=>$vat->reverseCharge,'remaining_budget_after'=>$this->vatCalculator->subtract($remaining,$amountExVat)],$now,$userId);
      $this->euroTraceSynchronizer->sync('commitment',$commitmentId,$userId);
      return $lineId;
    });
  }

  private function hasLockedWorkingBudget(int $projectNid): bool { return $this->repository->hasLockedWorkingBudget($projectNid); }
  private function loadDraftCommitment(int $commitmentId): array { $r=$this->repository->draftCommitment($commitmentId); if($r===NULL||$r['status']!=='draft') throw new UnexpectedValueException('A draft commitment is required.'); return $r; }
  private function loadLockedBudgetLine(int $budgetLineId,int $projectNid):array{$r=$this->repository->lockedBudgetLine($budgetLineId,$projectNid);if($r===NULL)throw new UnexpectedValueException('The commitment line must reference the locked working budget.');return $r;}
  private function remainingBudget(int $budgetLineId,string $budgetAmount):string{$committed=$this->repository->committedAmount($budgetLineId);$adj=$this->repository->approvedBudgetAdjustment($budgetLineId);return $this->vatCalculator->subtract($this->vatCalculator->add($budgetAmount,$adj),$committed);}
  private function refreshCommitmentTotals(int $commitmentId,int $now,int $userId):void{$t=$this->repository->commitmentTotals($commitmentId);$this->repository->updateCommitment($commitmentId,['amount_ex_vat'=>$t['amount_ex_vat'],'vat_amount'=>$t['vat_amount'],'amount_inc_vat'=>$t['amount_inc_vat'],'changed'=>$now,'changed_by'=>$userId]);}
  private function positiveDecimal(string $value,string $field):string{$n=str_replace(',','.',trim($value));try{if($this->vatCalculator->compare($n,'0')<=0)throw new InvalidArgumentException("$field must be greater than zero.");}catch(InvalidArgumentException){throw new InvalidArgumentException("$field must be a positive decimal with at most four decimal places.");}return$n;}
  private function audit(int $projectNid,int $commitmentId,string $action,array $payload,int $now,int $userId):void{$this->repository->insertAudit(['project_nid'=>$projectNid,'entity_type'=>'commitment','entity_id'=>$commitmentId,'action'=>$action,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),'reason'=>'Controlled purchase commitment after financial procurement phase gate against locked working budget.','created'=>$now,'created_by'=>$userId ]);}
}
