<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BankReconciliationRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;

final class DatabaseBankReconciliationRepository implements BankReconciliationRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function ensureStorage(): void {}
  public function existing(string $provider,string $transactionId): ?array {$r=$this->database->select('brebo_finance_bank_reconciliation','r')->fields('r')->condition('bank_provider',$provider)->condition('bank_transaction_id',$transactionId)->execute()->fetchAssoc();return$r?:NULL;}
  public function batchItemsByEndToEndId(string $endToEndId,array $batchStatuses): array {
    if(!$this->database->schema()->tableExists('brebo_finance_payment_batch_item'))return[];
    $q=$this->database->select('brebo_finance_payment_batch_item','i');$q->join('brebo_finance_payment_batch','b','b.id = i.batch_id');$q->fields('i')->addField('b','status','batch_status');$q->condition('i.end_to_end_id',$endToEndId)->condition('b.status',$batchStatuses,'IN');return array_values($q->execute()->fetchAllAssoc('id',\PDO::FETCH_ASSOC));
  }
  public function invoiceMoneybirdId(int $invoiceId): string {$r=$this->database->select('brebo_finance_purchase_invoice','i')->fields('i',['moneybird_id'])->condition('id',$invoiceId)->execute()->fetchAssoc();return(string)($r['moneybird_id']??'');}
  public function insert(array $fields): bool {try{$this->database->insert('brebo_finance_bank_reconciliation')->fields($fields)->execute();return TRUE;}catch(IntegrityConstraintViolationException){return FALSE;}}
}
