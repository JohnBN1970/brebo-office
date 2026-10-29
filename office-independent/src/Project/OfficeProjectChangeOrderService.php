<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use Brebo\Office\Finance\OfficeFinanceAccess;
use PDO;

final class OfficeProjectChangeOrderService
{
    private OfficeProjectChangeOrderRepository $changes;

    public function __construct(private readonly PDO $db)
    {
        $this->changes = new OfficeProjectChangeOrderRepository($db);
    }

    public function listForContract(
        string $operatorId,
        int $projectId,
        int $contractId
    ): array {
        $this->requireAccess($operatorId, $projectId, $contractId, false);

        return $this->changes->forContract($contractId);
    }

    public function createDraft(
        string $operatorId,
        int $projectId,
        int $contractId,
        string $changeNumber,
        string $description,
        string $changeType,
        string $amount
    ): int {
        $this->requireAccess($operatorId, $projectId, $contractId, true);

        $statement = $this->db->prepare(
            'SELECT status
             FROM office_project_contracts
             WHERE id = :contract_id
               AND office_project_id = :project_id'
        );

        $statement->execute([
            ':contract_id' => $contractId,
            ':project_id' => $projectId,
        ]);

        if ($statement->fetchColumn() !== 'draft') {
            throw new \DomainException('Contract not editable');
        }

        return $this->changes->createDraft(
            $contractId,
            $changeNumber,
            $description,
            $changeType,
            $amount
        );
    }

    public function approve(
        string $operatorId,
        int $projectId,
        int $contractId,
        int $changeId
    ): array {
        $this->requireAccess($operatorId, $projectId, $contractId, true);

        if ($changeId < 1) {
            throw new \InvalidArgumentException('Invalid change');
        }

        $this->db->beginTransaction();

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';

            $contract = $this->db->prepare(
                'SELECT contract_amount, status
                 FROM office_project_contracts
                 WHERE id = :contract_id
                   AND office_project_id = :project_id' . $lock
            );

            $contract->execute([
                ':contract_id' => $contractId,
                ':project_id' => $projectId,
            ]);

            $row = $contract->fetch(PDO::FETCH_ASSOC);

            if (!$row || $row['status'] !== 'draft') {
                throw new \DomainException('Contract not editable');
            }

            $change = $this->db->prepare(
                'SELECT id, change_type, amount, status
                 FROM office_project_change_orders
                 WHERE id = :change_id
                   AND contract_id = :contract_id'
            );

            $change->execute([
                ':change_id' => $changeId,
                ':contract_id' => $contractId,
            ]);

            $item = $change->fetch(PDO::FETCH_ASSOC);

            if (!$item || $item['status'] !== 'draft') {
                throw new \DomainException('Change not approvable');
            }

            $approved = $this->changes->approvedForContract($contractId);

            $approved[] = [
                'change_type' => $item['change_type'],
                'amount' => $item['amount'],
            ];

            $value = OfficeProjectContractValue::calculate(
                (string) $row['contract_amount'],
                $approved
            );

            $update = $this->db->prepare(
                "UPDATE office_project_change_orders
                 SET status = 'approved',
                     approved_at = CURRENT_TIMESTAMP
                 WHERE id = :change_id
                   AND contract_id = :contract_id
                   AND status = 'draft'"
            );

            $update->execute([
                ':change_id' => $changeId,
                ':contract_id' => $contractId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new \DomainException('Change approval conflict');
            }

            $this->db->commit();

            return $value;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function requireAccess(
        string $operatorId,
        int $projectId,
        int $contractId,
        bool $write
    ): void {
        if ($projectId < 1 || $contractId < 1) {
            throw new \InvalidArgumentException('Invalid project or contract');
        }

        $finance = new OfficeFinanceAccess($this->db, $operatorId);

        if (!$finance->allows('read')) {
            throw new \DomainException('Finance access required');
        }

        $projectAllowed = false;

        foreach ($finance->draftProjects() as $project) {
            if ((int) $project['office_project_id'] === $projectId) {
                $projectAllowed = true;
                break;
            }
        }

        if (!$projectAllowed) {
            throw new \DomainException('Project access required');
        }

        $statement = $this->db->prepare(
            'SELECT 1
             FROM office_project_contracts
             WHERE id = :contract_id
               AND office_project_id = :project_id'
        );

        $statement->execute([
            ':contract_id' => $contractId,
            ':project_id' => $projectId,
        ]);

        if ($statement->fetchColumn() === false) {
            throw new \DomainException('Contract does not belong to project');
        }

        if ($write) {
            if (!ctype_digit($operatorId)
                || !(new OfficeNativeProjectAccess($this->db))
                    ->canManage((int) $operatorId, $projectId)) {
                throw new \DomainException('Project management access required');
            }
        }
    }
}
