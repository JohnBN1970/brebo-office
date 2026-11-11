<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use Brebo\Office\Calculation\OfficeCalcCrmHandoffDecision;
use DomainException;
use PDO;

/**
 * A single CRM stage-change entrypoint. A direct assignment changes only the
 * stage; project creation remains a separate explicit workflow until a native
 * Office project factory is connected. Never manufacture an empty work budget.
 */
final class OfficeCrmWonTransition
{
    public function __construct(private readonly PDO $db) {}

    public function apply(int $opportunityId, string $from, ?array $acceptedCalculation = null): array
    {
        require_once __DIR__ . '/OfficeCrmFunnel.php';
        require_once __DIR__ . '/OfficeCrmOpportunityRepository.php';
        require_once dirname(__DIR__) . '/Calculation/OfficeCalcCrmHandoffDecision.php';

        if ($opportunityId < 1 || !OfficeCrmFunnel::canTransition($from, 'won')) {
            throw new DomainException('Invalid CRM acceptance transition');
        }
        $decision = OfficeCalcCrmHandoffDecision::decide('won', $acceptedCalculation);
        if ($decision === 'transfer_accepted_calculation') {
            // A calculation must be transferred in the same transaction as
            // project creation and persisted acceptance verification. Do not
            // mark the CRM stage won before that atomic workflow is available.
            throw new DomainException('Calculated acceptance requires atomic project handoff');
        }
        $changed = (new OfficeCrmOpportunityRepository($this->db))
            ->transition($opportunityId, $from, 'won');
        if (!$changed) {
            throw new DomainException('CRM opportunity changed concurrently');
        }
        return ['stage' => 'won', 'handoff' => $decision, 'project_created' => false];
    }
}
