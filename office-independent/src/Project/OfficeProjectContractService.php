<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use Brebo\Office\Finance\OfficeFinanceAccess;
use PDO;

final class OfficeProjectContractService
{
    private OfficeProjectContractRepository $repository;

    public function __construct(private readonly PDO $db)
    {
        $this->repository = new OfficeProjectContractRepository($db);
    }

    public function listForProject(
        string $operatorId,
        int $projectId
    ): array {
        $this->requireFinanceProjectAccess($operatorId, $projectId);

        return $this->repository->forProject($projectId);
    }

    public function createDraft(
        string $operatorId,
        int $projectId,
        ?string $contractNumber,
        ?string $clientName,
        string $amount
    ): int {
        $this->requireFinanceProjectAccess($operatorId, $projectId);

        if (!ctype_digit($operatorId)
            || !(new OfficeNativeProjectAccess($this->db))
                ->canManage((int) $operatorId, $projectId)) {
            throw new \DomainException('Project management access required');
        }

        return $this->repository->create(
            $projectId,
            $contractNumber,
            $clientName,
            $amount
        );
    }

    private function requireFinanceProjectAccess(
        string $operatorId,
        int $projectId
    ): void {
        if ($projectId < 1) {
            throw new \InvalidArgumentException('Invalid project');
        }

        $finance = new OfficeFinanceAccess($this->db, $operatorId);

        if (!$finance->allows('read')) {
            throw new \DomainException('Finance access required');
        }

        foreach ($finance->draftProjects() as $project) {
            if ((int) $project['office_project_id'] === $projectId) {
                return;
            }
        }

        throw new \DomainException('Project access required');
    }
}
