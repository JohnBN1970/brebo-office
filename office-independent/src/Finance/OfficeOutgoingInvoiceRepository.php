<?php
declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

final class OfficeOutgoingInvoiceRepository {
  public function __construct(private readonly PDO $db) {}

  public function create(string $operatorId, array $input): int {
    $vatOptions = (new OfficeVatRegimeCatalog($this->db))->activeOptions();
    $draft = OfficeOutgoingInvoiceDraft::validate($input, array_keys($vatOptions));
    $access = new OfficeFinanceAccess($this->db, $operatorId);
    $allowed = false;
    foreach ($access->draftProjects() as $project) {
      if ((int)$project['office_project_id'] === $draft['office_project_id']) $allowed = true;
    }
    if (!$allowed) throw new \DomainException('Project unavailable');
    $permission = $this->db->prepare("SELECT 1 FROM office_project_memberships WHERE operator_id=? AND project_id=? AND permission='manage' LIMIT 1");
    $permission->execute([$operatorId, $draft['office_project_id']]);
    if ($permission->fetchColumn() === false) throw new \DomainException('Project write access required');
    $this->db->beginTransaction();
    try {
      $q = $this->db->prepare("INSERT INTO office_finance_sales_drafts (operator_id,office_project_id,customer_name,status,lines_json,created_at) VALUES (?,?,?,'draft',?,UTC_TIMESTAMP())");
      $q->execute([$operatorId,$draft['office_project_id'],$draft['customer_name'],json_encode($draft['lines'],JSON_THROW_ON_ERROR)]);
      $id = (int)$this->db->lastInsertId();
      $this->db->commit();
      return $id;
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }
  }

  public function listForOperator(string $operatorId, ?int $projectId = null): array {
    $access = new OfficeFinanceAccess($this->db, $operatorId);
    if (!$access->allows()) throw new \DomainException('Finance access required');
    $ids = array_map(static fn(array $p): int => (int)$p['office_project_id'], $access->draftProjects());
    if ($projectId !== null) $ids = array_values(array_filter($ids, static fn(int $id): bool => $id === $projectId));
    if ($projectId !== null && $ids === []) throw new \OutOfBoundsException('Project unavailable');
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0,count($ids),'?'));
    $q = $this->db->prepare("SELECT id,office_project_id,customer_name,status,created_at FROM office_finance_sales_drafts WHERE office_project_id IN ($placeholders) ORDER BY id DESC LIMIT 500");
    $q->execute($ids);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }
}
