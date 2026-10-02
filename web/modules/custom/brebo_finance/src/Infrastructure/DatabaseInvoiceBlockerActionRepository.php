<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\InvoiceBlockerActionRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseInvoiceBlockerActionRepository implements InvoiceBlockerActionRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_finance_invoice_blocker_action')) return;
    $schema->createTable('brebo_finance_invoice_blocker_action', [
      'fields' => [
        'id' => ['type'=>'serial','not null'=>TRUE],
        'invoice_line_id' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'project_nid' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'owner_uid' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'action' => ['type'=>'text','size'=>'big','not null'=>TRUE],
        'due_date' => ['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
        'status' => ['type'=>'varchar','length'=>24,'not null'=>TRUE],
        'created' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'created_by' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
        'changed_by' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      ],
      'primary key' => ['id'],
      'indexes' => [
        'invoice_line'=>['invoice_line_id'],
        'project_status'=>['project_nid','status'],
        'owner_status'=>['owner_uid','status'],
        'due_date'=>['due_date'],
      ],
    ]);
  }

  public function activeIdForInvoiceLine(int $invoiceLineId): ?int {
    $id = $this->database->select('brebo_finance_invoice_blocker_action','a')
      ->fields('a',['id'])
      ->condition('invoice_line_id',$invoiceLineId)
      ->condition('status',['open','in_progress','waiting'],'IN')
      ->orderBy('changed','DESC')->range(0,1)->execute()->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function update(int $id, array $fields): void {
    $this->database->update('brebo_finance_invoice_blocker_action')->fields($fields)->condition('id',$id)->execute();
  }

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_finance_invoice_blocker_action')->fields($fields)->execute();
  }

  public function forProject(int $projectId): array {
    $rows=$this->database->select('brebo_finance_invoice_blocker_action','a')->fields('a')->condition('project_nid',$projectId)->orderBy('status','ASC')->orderBy('due_date','ASC')->orderBy('changed','DESC')->execute()->fetchAll();
    return array_map(static fn(object $row): array => (array) $row, $rows);
  }

  public function get(int $id): array {
    $row=$this->database->select('brebo_finance_invoice_blocker_action','a')->fields('a')->condition('id',$id)->execute()->fetchAssoc();
    return $row === FALSE ? [] : $row;
  }

}
