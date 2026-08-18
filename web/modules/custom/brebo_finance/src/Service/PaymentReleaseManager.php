<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Releases only fully matched invoices with a valid payment split. */
final class PaymentReleaseManager {
  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
    private readonly FinancialPhaseGateManager $phaseGateManager,
    private readonly FinancialEuroTraceFindingSynchronizer $euroTraceSynchronizer,
  ) {}

  public function prepare(int $invoiceId,string $releaseNumber,string $requestedPaymentDate,int $userId):int{
    if(trim($releaseNumber)==='')throw new InvalidArgumentException('Payment release number is required.');
    $invoice=$this->loadInvoice($invoiceId);$this->euroTraceSynchronizer->sync('purchase_invoice',$invoiceId,$userId);$this->phaseGateManager->requireRelease((int)$invoice['project_nid'],'payment_release');
    if($invoice['match_status']!=='matched')throw new RuntimeException('Payment is blocked until every invoice line passes three-way matching.');
    if(in_array($invoice['status'],['paid','cancelled'],TRUE))throw new RuntimeException('A paid or cancelled invoice cannot be released.');
    $regular=(string)$invoice['regular_account_amount'];$gAccount=(string)$invoice['g_account_amount'];$splitTotal=$this->decimal->add($regular,$gAccount);
    if($this->decimal->compare($splitTotal,(string)$invoice['amount_inc_vat'])!==0)throw new RuntimeException('Regular-account and G-account amounts do not equal the invoice total.');
    if($this->decimal->compare($gAccount,'0')>0)$this->assertApprovedGAccountInstruction($invoiceId,$gAccount);
    $now=time();$releaseId=(int)$this->database->insert('brebo_finance_payment_release')->fields(['project_nid'=>$invoice['project_nid'],'invoice_id'=>$invoiceId,'release_number'=>trim($releaseNumber),'status'=>'pending_approval','regular_account_amount'=>$regular,'g_account_amount'=>$gAccount,'total_amount'=>$splitTotal,'currency'=>$invoice['currency'],'requested_payment_date'=>$requestedPaymentDate!==''?$requestedPaymentDate:NULL,'requested'=>$now,'requested_by'=>$userId,'created'=>$now,'created_by'=>$userId,'changed'=>$now,'changed_by'=>$userId])->execute();
    $this->audit($invoice,$releaseId,'payment_release_requested',$userId,['regular_account_amount'=>$regular,'g_account_amount'=>$gAccount,'total_amount'=>$splitTotal]);
    $this->euroTraceSynchronizer->sync('payment_release',$releaseId,$userId);
    return $releaseId;
  }

  public function decide(int $releaseId,string $decision,string $note,int $userId):void{
    if(!in_array($decision,['approved','rejected'],TRUE)||trim($note)==='')throw new InvalidArgumentException('Decision must be approved or rejected and requires a note.');
    $release=$this->loadRelease($releaseId,'pending_approval');if((int)$release['requested_by']===$userId)throw new RuntimeException('The requester may not approve their own payment release.');
    $invoice=$this->loadInvoice((int)$release['invoice_id']);$this->euroTraceSynchronizer->sync('payment_release',$releaseId,$userId);
    if($decision==='approved'){$this->phaseGateManager->requireRelease((int)$invoice['project_nid'],'payment_release');if($invoice['match_status']!=='matched')throw new RuntimeException('Invoice matching changed; payment approval is blocked.');if($this->decimal->compare((string)$release['g_account_amount'],'0')>0)$this->assertApprovedGAccountInstruction((int)$release['invoice_id'],(string)$release['g_account_amount']);}
    $now=time();$this->database->update('brebo_finance_payment_release')->fields(['status'=>$decision,'approved'=>$decision==='approved'?$now:NULL,'approved_by'=>$decision==='approved'?$userId:NULL,'approval_note'=>trim($note),'changed'=>$now,'changed_by'=>$userId])->condition('id',$releaseId)->execute();
    $this->audit($invoice,$releaseId,'payment_release_'.$decision,$userId,['note'=>trim($note)]);$this->euroTraceSynchronizer->sync('payment_release',$releaseId,$userId);
  }

  public function markExecuted(int $releaseId,string $moneybirdPaymentRef,int $userId):void{
    if(trim($moneybirdPaymentRef)==='')throw new InvalidArgumentException('Moneybird or bank payment reference is required.');
    $release=$this->loadRelease($releaseId,'approved');$invoice=$this->loadInvoice((int)$release['invoice_id']);$this->euroTraceSynchronizer->sync('payment_release',$releaseId,$userId);$this->phaseGateManager->requireRelease((int)$invoice['project_nid'],'payment_release');
    $now=time();$this->database->update('brebo_finance_payment_release')->fields(['status'=>'executed','moneybird_payment_ref'=>trim($moneybirdPaymentRef),'executed'=>$now,'executed_by'=>$userId,'changed'=>$now,'changed_by'=>$userId])->condition('id',$releaseId)->execute();
    $this->database->update('brebo_finance_purchase_invoice')->fields(['status'=>'paid','changed'=>$now,'changed_by'=>$userId])->condition('id',$invoice['id'])->execute();$this->audit($invoice,$releaseId,'payment_executed',$userId,['moneybird_payment_ref'=>trim($moneybirdPaymentRef)]);$this->euroTraceSynchronizer->sync('payment_release',$releaseId,$userId);
  }

  private function loadInvoice(int $invoiceId):array{$r=$this->database->select('brebo_finance_purchase_invoice','i')->fields('i')->condition('id',$invoiceId)->execute()->fetchAssoc();if($r===FALSE)throw new UnexpectedValueException('Purchase invoice does not exist.');return$r;}
  private function loadRelease(int $releaseId,string $requiredStatus):array{$r=$this->database->select('brebo_finance_payment_release','r')->fields('r')->condition('id',$releaseId)->execute()->fetchAssoc();if($r===FALSE||$r['status']!==$requiredStatus)throw new UnexpectedValueException("Payment release must have status $requiredStatus.");return$r;}
  private function assertApprovedGAccountInstruction(int $invoiceId,string $amount):void{$r=$this->database->select('brebo_finance_g_account_instruction','g')->fields('g',['g_account_amount','effective_from','effective_until'])->condition('direction','outgoing')->condition('source_type','purchase_invoice')->condition('source_id',$invoiceId)->condition('status','approved')->execute()->fetchAssoc();if($r===FALSE)throw new RuntimeException('An approved outgoing G-account instruction is required.');if($this->decimal->compare((string)$r['g_account_amount'],$amount)!==0)throw new RuntimeException('The invoice G-account amount differs from the approved instruction.');$today=date('Y-m-d');if((!empty($r['effective_from'])&&$r['effective_from']>$today)||(!empty($r['effective_until'])&&$r['effective_until']<$today))throw new RuntimeException('The approved G-account instruction is not currently valid.');}
  private function audit(array $invoice,int $releaseId,string $action,int $userId,array $payload):void{$this->database->insert('brebo_finance_audit')->fields(['project_nid'=>$invoice['project_nid'],'entity_type'=>'payment_release','entity_id'=>$releaseId,'action'=>$action,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'reason'=>'Controlled payment workflow after financial phase gate, three-way and G-account checks.','created'=>time(),'created_by'=>$userId])->execute();}
}
