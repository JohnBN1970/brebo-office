<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use Brebo\Office\Finance\OfficeFinanceAccess;
use PDO;

final class OfficeProjectTermService
{
    private OfficeProjectTermRepository $terms;
    private OfficeProjectChangeOrderRepository $changes;

    public function __construct(private readonly PDO $db)
    {
        $this->terms = new OfficeProjectTermRepository($db);
        $this->changes = new OfficeProjectChangeOrderRepository($db);
    }

    public function listForContract(
        string $operatorId,
        int $projectId,
        int $contractId
    ): array {
        $this->requireAccess($operatorId, $projectId, $contractId, false);

        return $this->terms->forContract($contractId);
    }

    public function createPlanned(
        string $operatorId,
        int $projectId,
        int $contractId,
        int $termNumber,
        string $description,
        string $amount,
        ?string $percentage = null
    ): int {
        $this->requireAccess($operatorId, $projectId, $contractId, true);

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

            if (!$row) {
                throw new \DomainException('Contract not found');
            }

            if ($row['status'] !== 'draft') {
                throw new \DomainException('Contract not editable');
            }

            $existing = $this->terms->forContract($contractId);

            $totalCents = 0;
            $totalPercentage = 0;

            foreach ($existing as $term) {
                $totalCents += self::toCents((string) $term['amount']);

                if ($term['percentage'] !== null) {
                    $totalPercentage += (int) round(
                        (float) $term['percentage'] * 10000
                    );
                }
            }

            $newCents = self::toCents($amount);
            $contractValue = OfficeProjectContractValue::calculate(
                (string) $row['contract_amount'],
                $this->changes->approvedForContract($contractId)
            );

            $contractCents = self::toCents($contractValue['current']);

            if ($totalCents + $newCents > $contractCents) {
                throw new \DomainException('Contract amount exceeded');
            }

            if ($percentage !== null) {
                $totalPercentage += (int) round((float) $percentage * 10000);

                if ($totalPercentage > 1000000) {
                    throw new \DomainException('Percentage exceeded');
                }
            }

            $id = $this->terms->create(
                $contractId,
                $termNumber,
                $description,
                $amount,
                $percentage
            );

            $this->db->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private static function toCents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D', $amount)) {
            throw new \InvalidArgumentException('Invalid monetary amount');
        }

        $parts = explode('.', $amount);
        $euros = $parts[0];
        $cents = str_pad($parts[1] ?? '0', 2, '0');

        return ((int) $euros * 100) + (int) $cents;
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
            if ((int)$project['office_project_id'] === $projectId) {
                $projectAllowed = true;
                break;
            }
        }

        if (!$projectAllowed) {
            throw new \DomainException('Project access required');
        }

        $statement = $this->db->prepare(
            'SELECT 1 FROM office_project_contracts
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
                    ->canManage((int)$operatorId, $projectId)) {
                throw new \DomainException('Project management access required');
            }
        }
    }
}
