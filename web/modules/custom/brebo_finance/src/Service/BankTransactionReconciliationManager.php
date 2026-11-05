<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\BankReconciliationRepositoryInterface;
use Drupal\brebo_finance\Contract\PurchaseInvoiceReadbackGatewayInterface;

/** Deterministically classifies ABN mutations and closes Moneybird evidence. */
final class BankTransactionReconciliationManager {

  public function __construct(
    private readonly BankReconciliationRepositoryInterface $repository,
    private readonly VatCalculator $decimal,
    private readonly PurchaseInvoiceReadbackGatewayInterface $invoiceReadback,
  ) {}

  public function reconcile(array $activity): array {
    $this->repository->ensureStorage();
    $transactionId = trim((string) ($activity['transaction_id'] ?? ''));
    $amount = (string) ($activity['amount'] ?? '0');
    $currency = strtoupper(trim((string) ($activity['currency'] ?? 'EUR')));
    $counterpartyIban = $this->normalizeIban((string) ($activity['counterparty_iban'] ?? ''));
    $endToEndId = trim((string) ($activity['end_to_end_id'] ?? ''));
    if ($transactionId === '' || $this->decimal->compare($amount, '0') === 0) return $this->result('neutral', 'bank_activity_incomplete', 'Bankmutatie mist een stabiele referentie of bedrag.', []);

    $existing = $this->existingByTransaction($transactionId);
    if ($existing) return $this->result((string) $existing['traffic_light'], (string) $existing['reason_code'], 'Bankmutatie is al beoordeeld.', $existing);

    $candidates = $endToEndId !== '' ? $this->repository->batchItemsByEndToEndId($endToEndId, ['released', 'submitted', 'executed', 'reconciled']) : [];
    if (count($candidates) === 1) {
      $item = reset($candidates);
      $amountOk = $this->decimal->compare((string) $item['amount'], ltrim($amount, '-')) === 0;
      $currencyOk = strtoupper((string) $item['currency']) === $currency;
      $ibanOk = $counterpartyIban === '' || $this->normalizeIban((string) $item['creditor_iban']) === $counterpartyIban;
      if (!$amountOk || !$currencyOk || !$ibanOk) return $this->persist($activity, $item, 'red', 'brebo_bank_material_mismatch', 'Bankuitvoering wijkt materieel af van de vrijgegeven BREBO-betaalinstructie.', 'not_checked');
      return $this->closeMoneybird($activity, $item);
    }
    if (count($candidates) > 1) return $this->persist($activity, NULL, 'red', 'duplicate_end_to_end_id', 'Dezelfde EndToEndId wijst naar meerdere BREBO-betaalinstructies.', 'not_checked');
    return $this->persist($activity, NULL, 'orange', 'external_bank_payment', 'Bankmutatie is buiten een BREBO-betaalrun ontstaan en vraagt automatische vervolgmatching of classificatie.', 'pending');
  }

  /**
   * Bank evidence is matched to BREBO-owned payment instructions only.
   * Never read back Moneybird invoice documents or infer payment from their state.
   */
  private function closeMoneybird(array $activity, array $item): array {
    return $this->persist(
      $activity,
      $item,
      'green',
      'brebo_bank_instruction_matched',
      'Bankmutatie sluit aan op de BREBO-betaalinstructie; factuurstatus blijft onder beheer van BREBO.',
      'not_checked'
    );
  }

  private function persist(array $activity, ?array $item, string $light, string $reason, string $message, string $moneybirdState): array {
    $now=time();$transactionId=trim((string)($activity['transaction_id']??''));
    $fields=['bank_provider'=>'abnamro','bank_transaction_id'=>$transactionId,'booking_date'=>(string)($activity['booking_date']??''),'amount'=>(string)($activity['amount']??'0'),'currency'=>strtoupper((string)($activity['currency']??'EUR')),'counterparty_iban'=>$this->normalizeIban((string)($activity['counterparty_iban']??'')),'end_to_end_id'=>(string)($activity['end_to_end_id']??''),'batch_id'=>$item?(int)$item['batch_id']:NULL,'batch_item_id'=>$item?(int)$item['id']:NULL,'invoice_id'=>$item?(int)$item['invoice_id']:NULL,'release_id'=>$item?(int)$item['release_id']:NULL,'traffic_light'=>$light,'reason_code'=>$reason,'message'=>$message,'moneybird_state'=>$moneybirdState,'created'=>$now,'changed'=>$now];
    if(!$this->repository->insert($fields)){$existing=$this->existingByTransaction($transactionId);if($existing)return $this->result((string)$existing['traffic_light'],(string)$existing['reason_code'],'Bankmutatie is gelijktijdig al beoordeeld.',$existing);throw new RuntimeException('Bank reconciliation could not be persisted.');}
    return $this->result($light,$reason,$message,$fields);
  }

  private function existingByTransaction(string $transactionId): array|false {
    return $this->repository->existing('abnamro',$transactionId) ?: FALSE;
  }

  private function result(string $light,string $reason,string $message,array $evidence):array{return['traffic_light'=>$light,'reason_code'=>$reason,'message'=>$message,'evidence'=>$evidence];}
  private function normalizeIban(string $iban):string{return strtoupper((string)preg_replace('/\s+/','',trim($iban)));}
}
