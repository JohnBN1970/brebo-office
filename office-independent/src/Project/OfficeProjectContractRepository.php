<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

final class OfficeProjectContractRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function forProject(int $projectId): array
    {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project');
        }

        $statement = $this->db->prepare(
            'SELECT id, office_project_id, contract_number,
                    client_name, contract_amount, currency,
                    status, signed_at, created_at
             FROM office_project_contracts
             WHERE office_project_id = :project_id
             ORDER BY id DESC'
        );

        $statement->execute([':project_id' => $projectId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $projectId,
        ?string $contractNumber,
        ?string $clientName,
        string $amount
    ): int {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project');
        }

        if (!preg_match('/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $amount)) {
            throw new \InvalidArgumentException('Invalid contract amount');
        }

        $statement = $this->db->prepare(
            'INSERT INTO office_project_contracts
             (office_project_id, contract_number, client_name,
              contract_amount, currency, status)
             VALUES
             (:project_id, :contract_number, :client_name,
              :amount, :currency, :status)'
        );

        $statement->execute([
            ':project_id' => $projectId,
            ':contract_number' => $contractNumber,
            ':client_name' => $clientName,
            ':amount' => $amount,
            ':currency' => 'EUR',
            ':status' => 'draft',
        ]);

        return (int) $this->db->lastInsertId();
    }
}
