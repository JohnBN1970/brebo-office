<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use Brebo\Office\Finance\OfficeFinanceAccess;
use PDO;

final class OfficeProjectFinanceOverview
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function forProject(string $operatorId, int $projectId): array
    {
        if ($projectId < 1 || !ctype_digit($operatorId)) {
            throw new \DomainException('Invalid project or operator');
        }

        $access = new OfficeFinanceAccess($this->db, $operatorId);

        if (!$access->allows('read')) {
            throw new \DomainException('Finance access required');
        }

        $authorized = false;

        foreach ($access->draftProjects() as $project) {
            if ((int) $project['office_project_id'] === $projectId) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            throw new \DomainException('Project access required');
        }

        $contracts = (new OfficeProjectContractRepository($this->db))
            ->forProject($projectId);

        $changeRepository = new OfficeProjectChangeOrderRepository($this->db);
        $termRepository = new OfficeProjectTermRepository($this->db);

        $result = [];

        foreach ($contracts as $contract) {
            $contractId = (int) $contract['id'];

            $changes = $changeRepository->forContract($contractId);
            $terms = $termRepository->forContract($contractId);

            $value = OfficeProjectContractValue::calculate(
                (string) $contract['contract_amount'],
                $changeRepository->approvedForContract($contractId)
            );

            $plannedCents = 0;
            foreach ($terms as $term) {
                $plannedCents += self::toCents((string) $term['amount']);
            }

            $result[] = [
                'contract' => $contract,
                'value' => $value,
                'changes' => $changes,
                'terms' => $terms,
                'planned_terms_total' => self::fromCents($plannedCents),
                'term_schedule_exceeded' =>
                    $plannedCents > self::toCents($value['current']),
            ];
        }

        return $result;
    }

    private static function toCents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/D', $amount)) {
            throw new \DomainException('Invalid monetary amount');
        }

        [$euros, $cents] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $euros * 100)
            + (int) str_pad($cents, 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
