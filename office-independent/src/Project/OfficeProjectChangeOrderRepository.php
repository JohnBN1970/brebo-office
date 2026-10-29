<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

final class OfficeProjectChangeOrderRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function forContract(int $contractId): array
    {
        if ($contractId < 1) {
            throw new \InvalidArgumentException('Invalid contract');
        }

        $statement = $this->db->prepare(
            'SELECT id, contract_id, change_number, description,
                    change_type, amount, status, approved_at, created_at
             FROM office_project_change_orders
             WHERE contract_id = :contract_id
             ORDER BY id ASC'
        );

        $statement->execute([':contract_id' => $contractId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDraft(
        int $contractId,
        string $changeNumber,
        string $description,
        string $changeType,
        string $amount
    ): int {
        if ($contractId < 1) {
            throw new \InvalidArgumentException('Invalid contract');
        }

        $changeNumber = trim($changeNumber);
        $description = trim($description);

        if ($changeNumber === '' || strlen($changeNumber) > 100) {
            throw new \InvalidArgumentException('Invalid change number');
        }

        if ($description === '') {
            throw new \InvalidArgumentException('Description required');
        }

        if (!in_array($changeType, ['more', 'less'], true)) {
            throw new \InvalidArgumentException('Invalid change type');
        }

        if (!preg_match('/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $amount)) {
            throw new \InvalidArgumentException('Invalid amount');
        }

        if ($amount === '0.00') {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $statement = $this->db->prepare(
            'INSERT INTO office_project_change_orders
             (contract_id, change_number, description,
              change_type, amount, status)
             VALUES
             (:contract_id, :change_number, :description,
              :change_type, :amount, :status)'
        );

        $statement->execute([
            ':contract_id' => $contractId,
            ':change_number' => $changeNumber,
            ':description' => $description,
            ':change_type' => $changeType,
            ':amount' => $amount,
            ':status' => 'draft',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function approvedForContract(int $contractId): array
    {
        if ($contractId < 1) {
            throw new \InvalidArgumentException('Invalid contract');
        }

        $statement = $this->db->prepare(
            'SELECT change_type, amount
             FROM office_project_change_orders
             WHERE contract_id = :contract_id
               AND status = :status'
        );

        $statement->execute([
            ':contract_id' => $contractId,
            ':status' => 'approved',
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
