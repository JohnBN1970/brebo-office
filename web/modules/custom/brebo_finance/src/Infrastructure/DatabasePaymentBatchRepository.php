<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PaymentBatchRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePaymentBatchRepository implements PaymentBatchRepositoryInterface {
  public function __construct(private readonly Connection $database) {}
  public function ensureStorage(): void {}
  public function transactional(callable $callback): mixed {$transaction=$this->database->startTransaction();try{return $callback();}catch(\Throwable $e){$transaction->rollBack();throw $e;}}
  public function release(int $id): ?array {$r=$this->database->select('brebo_finance_payment_release','r')->fields('r')->condition('id',$id)->execute()->fetchAssoc();return$r?:NULL;}
  public function invoice(int $id): ?array {$r=$this->database->select('brebo_finance_purchase_invoice','i')->fields('i')->condition('id',$id)->execute()->fetchAssoc();return$r?:NULL;}
  public function batch(int $id): ?array {$r=$this->database->select('brebo_finance_payment_batch','b')->fields('b')->condition('id',$id)->execute()->fetchAssoc();return$r?:NULL;}
  public function items(int $batchId): array {return$this->database->select('brebo_finance_payment_batch_item','i')->fields('i')->condition('batch_id',$batchId)->orderBy('position')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
  public function releaseInOpenBatch(int $releaseId): bool {$q=$this->database->select('brebo_finance_payment_batch_item','i');$q->innerJoin('brebo_finance_payment_batch','b','b.id = i.batch_id');$q->condition('i.release_id',$releaseId)->condition('b.status',['cancelled','rejected','executed','reconciled'],'NOT IN');return(bool)$q->countQuery()->execute()->fetchField();}
  public function insertBatch(array $values): int {return(int)$this->database->insert('brebo_finance_payment_batch')->fields($values)->execute();}
  public function insertItem(array $values): int {return(int)$this->database->insert('brebo_finance_payment_batch_item')->fields($values)->execute();}
  public function updateBatch(int $batchId,array $values): void {$this->database->update('brebo_finance_payment_batch')->fields($values)->condition('id',$batchId)->execute();}
  public function updateItems(int $batchId,array $values): void {$this->database->update('brebo_finance_payment_batch_item')->fields($values)->condition('batch_id',$batchId)->execute();}
  public function insertAudit(array $values): void {$this->database->insert('brebo_finance_audit')->fields($values)->execute();}
}
