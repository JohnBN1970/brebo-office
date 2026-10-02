<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\InvoiceBlockerActionRepositoryInterface;
use InvalidArgumentException;

final class InvoiceBlockerActionManager {
  public function __construct(private readonly InvoiceBlockerActionRepositoryInterface $repository) {}

  public function save(int $invoiceLineId, int $projectNid, int $ownerUid, string $action, ?int $dueDate, string $status, int $userId): array {
    if ($invoiceLineId <= 0 || $projectNid <= 0 || $ownerUid <= 0 || trim($action) === '') throw new InvalidArgumentException('Factuurregel, project, eigenaar en actie zijn verplicht.');
    if (!in_array($status, ['open','in_progress','waiting','resolved','cancelled'], true)) throw new InvalidArgumentException('Ongeldige opvolgstatus.');
    $this->repository->ensureStorage(); $now=time();
    $existing=$this->repository->activeIdForInvoiceLine($invoiceLineId);
    $fields=['project_nid'=>$projectNid,'owner_uid'=>$ownerUid,'action'=>trim($action),'due_date'=>$dueDate,'status'=>$status,'changed'=>$now,'changed_by'=>$userId];
    if($existing!==NULL){$this->repository->update($existing,$fields);$id=$existing;}else{$id=$this->repository->create(['invoice_line_id'=>$invoiceLineId,'created'=>$now,'created_by'=>$userId]+$fields);}
    return $this->get($id);
  }

  public function forProject(int $projectNid): array {$this->repository->ensureStorage();return $this->repository->forProject($projectNid);}
  public function get(int $id): array {$this->repository->ensureStorage();return $this->repository->get($id);}

}
