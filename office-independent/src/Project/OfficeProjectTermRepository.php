<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;

final class OfficeProjectTermRepository
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
            'SELECT id, contract_id, term_number, description,
                    percentage, amount, status, due_date, created_at
             FROM office_project_terms
             WHERE contract_id = :contract_id
             ORDER BY term_number ASC'
        );

        $statement->execute([':contract_id' => $contractId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(
        int $contractId,
        int $termNumber,
        string $description,
        string $amount,
        ?string $percentage = null
    ): int {
        if ($contractId < 1 || $termNumber < 1) {
            throw new \InvalidArgumentException('Invalid contract or term');
        }

        if (trim($description) === '') {
            throw new \InvalidArgumentException('Description required');
        }

        if (!preg_match('/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/D', $amount)) {
            throw new \InvalidArgumentException('Invalid amount');
        }

        if ($percentage !== null) {
            if (!preg_match('/^(?:100(?:\.0{1,4})?|(?:[0-9]|[1-9][0-9])(?:\.[0-9]{1,4})?)$/D', $percentage)) {
                throw new \InvalidArgumentException('Invalid percentage');
            }
        }

        $statement = $this->db->prepare(
            'INSERT INTO office_project_terms
             (contract_id, term_number, description,
              percentage, amount, status)
             VALUES
             (:contract_id, :term_number, :description,
              :percentage, :amount, :status)'
        );

        $statement->execute([
            ':contract_id' => $contractId,
            ':term_number' => $termNumber,
            ':description' => trim($description),
            ':percentage' => $percentage,
            ':amount' => $amount,
            ':status' => 'planned',
        ]);

        return (int) $this->db->lastInsertId();
    }
}
