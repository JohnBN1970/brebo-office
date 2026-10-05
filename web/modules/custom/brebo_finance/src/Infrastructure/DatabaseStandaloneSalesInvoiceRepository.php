<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\StandaloneSalesInvoiceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseStandaloneSalesInvoiceRepository implements StandaloneSalesInvoiceRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function draftStorageAvailable(): bool {
    $schema=$this->database->schema();
    return $schema->tableExists('brebo_finance_sales_invoice_draft') && $schema->tableExists('brebo_finance_sales_invoice_draft_line');
  }

  public function releaseStorageAvailable(): bool {
    return $this->draftStorageAvailable() && $this->database->schema()->tableExists('brebo_finance_sales_invoice_outbox');
  }

  public function editableDraft(int $draftId): ?array {
    if(!$this->draftStorageAvailable()) return NULL;
    $row=$this->database->select('brebo_finance_sales_invoice_draft','d')->fields('d')
      ->condition('id',$draftId)->condition('project_nid',0)->condition('status','draft')->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function draft(int $draftId): ?array {
    if(!$this->draftStorageAvailable()) return NULL;
    $row=$this->database->select('brebo_finance_sales_invoice_draft','d')->fields('d')
      ->condition('id',$draftId)->condition('project_nid',0)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function lines(int $draftId): array {
    if(!$this->draftStorageAvailable()) return [];
    return array_values($this->database->select('brebo_finance_sales_invoice_draft_line','l')->fields('l')
      ->condition('draft_id',$draftId)->orderBy('line_number')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function saveDraft(int $draftId, string $draftNumber, array $draftFields, array $lineFields): int {
    $transaction=$this->database->startTransaction();
    try {
      if($draftId>0){
        $this->database->update('brebo_finance_sales_invoice_draft')->fields($draftFields)
          ->condition('id',$draftId)->condition('project_nid',0)->condition('status','draft')->execute();
        $this->database->delete('brebo_finance_sales_invoice_draft_line')->condition('draft_id',$draftId)->execute();
      } else {
        $draftFields['draft_number']=$draftNumber;
        $draftId=(int)$this->database->insert('brebo_finance_sales_invoice_draft')->fields($draftFields)->execute();
      }
      foreach($lineFields as $fields){
        $fields['draft_id']=$draftId;
        $this->database->insert('brebo_finance_sales_invoice_draft_line')->fields($fields)->execute();
      }
      return $draftId;
    } catch(\Throwable $e){
      $transaction->rollBack();
      throw $e;
    }
  }

  public function queueRelease(int $draftId, array $outboxFields, array $draftFields): int {
    $transaction=$this->database->startTransaction();
    try {
      $existing=$this->database->select('brebo_finance_sales_invoice_outbox','o')->fields('o',['id'])
        ->condition('draft_id',$draftId)->execute()->fetchField();
      if($existing!==FALSE) throw new \RuntimeException('This invoice draft already has a registration command.');
      $outboxId=(int)$this->database->insert('brebo_finance_sales_invoice_outbox')->fields($outboxFields)->execute();
      $this->database->update('brebo_finance_sales_invoice_draft')->fields($draftFields)
        ->condition('id',$draftId)->condition('status','draft')->execute();
      return $outboxId;
    } catch(\Throwable $e){
      $transaction->rollBack();
      throw $e;
    }
  }
}
