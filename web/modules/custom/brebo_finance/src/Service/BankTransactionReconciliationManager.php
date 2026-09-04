<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Deterministically classifies ABN mutations and closes Moneybird evidence. */
final class BankTransactionReconciliationManager {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
    private readonly PurchaseInvoiceIntegrationClient $moneybird,
  ) {}

  public function reconcile(array $activity): array {
    $this->ensureStorage();
    $transactionId = trim((string) ($activity['transaction_id'] ?? ''));
    $amount = (string) ($activity['amount'] ?? '0');
    $currency = strtoupper(trim((string) ($activity['currency'] ?? 'EUR')));
    $counterpartyIban = $this->normalizeIban((string) ($activity['counterparty_iban'] ?? ''));
    $endToEndId = trim((string) ($activity['end_to_end_id'] ?? ''));
    if ($transactionId === '' || $this->decimal->compare($amount, '0') === 0) return $this->result('neutral', 'bank_activity_incomplete', 'Bankmutatie mist een stabiele referentie of bedrag.', []);

    $existing = $this->database->select('brebo_finance_bank_reconciliation', 'r')->fields('r')->condition('bank_provider', 'abnamro')->condition('bank_transaction_id', $transactionId)->execute()->fetchAssoc();
    if ($existing) return $this->result((string) $existing['traffic_light'], (string) $existing['reason_code'], 'Bankmutatie is al beoordeeld.', $existing);

    $candidates = [];
    if ($endToEndId !== '' && $this->database->schema()->tableExists('brebo_finance_payment_batch_item')) {
      $query = $this->database->select('brebo_finance_payment_batch_item', 'i');
      $query->join('brebo_finance_payment_batch', 'b', 'b.id = i.batch_id');
      $query->fields('i')->addField('b', 'status', 'batch_status');
      $query->condition('i.end_to_end_id', $endToEndId)->condition('b.status', ['released', 'submitted', 'executed', 'reconciled'], 'IN');
      $candidates = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    }
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

  private function closeMoneybird(array $activity, array $item): array {
    try { $remote = $this->moneybird->fetchAll(); }
    catch (\Throwable) { return $this->persist($activity, $item, 'orange', 'moneybird_reconciliation_unavailable', 'ABN-uitvoering is exact, maar Moneybird kon niet worden gecontroleerd.', 'unavailable'); }
    $local = $this->database->select('brebo_finance_purchase_invoice', 'i')->fields('i', ['moneybird_id'])->condition('id', (int) $item['invoice_id'])->execute()->fetchAssoc();
    $moneybirdId = (string) ($local['moneybird_id'] ?? '');
    $invoice = NULL;
    foreach ($remote as $candidate) if ((string) ($candidate['id'] ?? '') === $moneybirdId) { $invoice = $candidate; break; }
    if (!$invoice) return $this->persist($activity, $item, 'orange', 'moneybird_invoice_missing', 'ABN-uitvoering is exact, maar de gekoppelde Moneybird-inkoopfactuur ontbreekt in de readback.', 'missing');

    $payments = is_array($invoice['payments'] ?? NULL) ? $invoice['payments'] : [];
    $bankTransactionId = trim((string) ($activity['transaction_id'] ?? ''));
    $endToEndId = trim((string) ($activity['end_to_end_id'] ?? ''));
    $matchedPayment = NULL;
    foreach ($payments as $payment) {
      if (!is_array($payment)) continue;
      $refs = array_filter(array_map('strval', [$payment['financial_mutation_id'] ?? '', $payment['payment_transaction_id'] ?? '', $payment['transaction_identifier'] ?? '', $payment['linked_payment_id'] ?? '']));
      if (in_array($bankTransactionId, $refs, TRUE) || ($endToEndId !== '' && in_array($endToEndId, $refs, TRUE))) { $matchedPayment = $payment; break; }
    }
    if ($matchedPayment) {
      $paymentAmount = (string) ($matchedPayment['price'] ?? '0');
      if ($this->decimal->compare(ltrim($paymentAmount, '-'), (string) $item['amount']) !== 0) return $this->persist($activity, $item, 'red', 'moneybird_payment_amount_mismatch', 'Moneybird koppelt dezelfde betaling, maar met een afwijkend bedrag.', 'mismatch');
      return $this->persist($activity, $item, 'green', 'brebo_bank_moneybird_reconciled', 'BREBO-vrijgave, ABN-uitvoering en Moneybird-betaling sluiten aantoonbaar op elkaar aan.', 'linked');
    }
    $state = strtolower((string) ($invoice['state'] ?? ''));
    if ($state === 'paid' || $this->decimal->compare((string) ($invoice['paid_amount'] ?? '0'), '0') > 0) return $this->persist($activity, $item, 'orange', 'moneybird_paid_link_missing', 'ABN heeft exact uitgevoerd en Moneybird toont betaling, maar de bankmutatie is niet aantoonbaar aan dezelfde payment gekoppeld.', 'paid_unlinked');
    return $this->persist($activity, $item, 'orange', 'moneybird_payment_missing', 'ABN heeft exact uitgevoerd, maar Moneybird toont nog geen corresponderende betaling op de inkoopfactuur.', 'unpaid');
  }

  private function persist(array $activity, ?array $item, string $light, string $reason, string $message, string $moneybirdState): array {
    $now = time();
    $fields = ['bank_provider'=>'abnamro','bank_transaction_id'=>trim((string)$activity['transaction_id']),'booking_date'=>(string)($activity['booking_date']??''),'amount'=>(string)($activity['amount']??'0'),'currency'=>strtoupper((string)($activity['currency']??'EUR')),'counterparty_iban'=>$this->normalizeIban((string)($activity['counterparty_iban']??'')),'end_to_end_id'=>(string)($activity['end_to_end_id']??''),'batch_id'=>$item?(int)$item['batch_id']:NULL,'batch_item_id'=>$item?(int)$item['id']:NULL,'invoice_id'=>$item?(int)$item['invoice_id']:NULL,'release_id'=>$item?(int)$item['release_id']:NULL,'traffic_light'=>$light,'reason_code'=>$reason,'message'=>$message,'moneybird_state'=>$moneybirdState,'created'=>$now,'changed'=>$now];
    $this->database->insert('brebo_finance_bank_reconciliation')->fields($fields)->execute();
    return $this->result($light,$reason,$message,$fields);
  }
  private function result(string $light,string $reason,string $message,array $evidence):array{return['traffic_light'=>$light,'reason_code'=>$reason,'message'=>$message,'evidence'=>$evidence];}
  private function normalizeIban(string $iban):string{return strtoupper((string)preg_replace('/\s+/','',trim($iban)));}
  private function ensureStorage():void{$schema=$this->database->schema();if($schema->tableExists('brebo_finance_bank_reconciliation'))return;$schema->createTable('brebo_finance_bank_reconciliation',['description'=>'Bank to BREBO to Moneybird reconciliation evidence.','fields'=>['id'=>['type'=>'serial','not null'=>TRUE],'bank_provider'=>['type'=>'varchar','length'=>24,'not null'=>TRUE],'bank_transaction_id'=>['type'=>'varchar','length'=>128,'not null'=>TRUE],'booking_date'=>['type'=>'varchar','length'=>32,'not null'=>FALSE],'amount'=>['type'=>'numeric','precision'=>18,'scale'=>4,'not null'=>TRUE],'currency'=>['type'=>'varchar','length'=>3,'not null'=>TRUE],'counterparty_iban'=>['type'=>'varchar','length'=>34,'not null'=>FALSE],'end_to_end_id'=>['type'=>'varchar','length'=>64,'not null'=>FALSE],'batch_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'batch_item_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'invoice_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'release_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'traffic_light'=>['type'=>'varchar','length'=>16,'not null'=>TRUE],'reason_code'=>['type'=>'varchar','length'=>64,'not null'=>TRUE],'message'=>['type'=>'text','not null'=>TRUE],'moneybird_state'=>['type'=>'varchar','length'=>24,'not null'=>TRUE,'default'=>'pending'],'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE]],'primary key'=>['id'],'unique keys'=>['provider_transaction'=>['bank_provider','bank_transaction_id']],'indexes'=>['traffic_light'=>['traffic_light'],'invoice_id'=>['invoice_id'],'batch_id'=>['batch_id'],'moneybird_state'=>['moneybird_state']]]);}
}
