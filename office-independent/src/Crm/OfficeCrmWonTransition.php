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

    public function apply(int $opportunityId, string $from): array
    {
        require_once __DIR__ . '/OfficeCrmFunnel.php';
        require_once __DIR__ . '/OfficeCrmOpportunityRepository.php';
        require_once dirname(__DIR__) . '/Calculation/OfficeCalcCrmHandoffDecision.php';

        if ($opportunityId < 1 || !OfficeCrmFunnel::canTransition($from, 'won')) {
            throw new DomainException('Invalid CRM acceptance transition');
        }
        // The browser must never decide whether a calculation was accepted.
        // Read the persisted acceptance under the same transaction as the stage update.
        if ($this->db->inTransaction()) {
            throw new DomainException('CRM acceptance requires its own transaction');
        }
        $this->db->beginTransaction();
        try {
            // Serialize acceptance with concurrent CRM stage changes before
            // inspecting the persisted calculation acceptance.
            $opportunity = $this->db->prepare(
                'SELECT stage, status FROM office_crm_opportunities WHERE id = ? FOR UPDATE'
            );
            $opportunity->execute([$opportunityId]);
            $current = $opportunity->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current) || $current['stage'] !== $from || $current['status'] !== 'open') {
                throw new DomainException('CRM opportunity changed concurrently');
            }
            $stmt = $this->db->prepare(
                'SELECT calculation_id, accepted_revision, status FROM office_crm_calculation_acceptances WHERE opportunity_id = ? FOR UPDATE'
            );
            $stmt->execute([$opportunityId]);
            $persisted = $stmt->fetch(PDO::FETCH_ASSOC);
            $acceptedCalculation = is_array($persisted) ? [
                'calculation_id' => (int)$persisted['calculation_id'],
                'accepted_revision' => (string)$persisted['accepted_revision'],
                'status' => (string)$persisted['status'],
            ] : null;
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
            $this->db->commit();
            return ['stage' => 'won', 'handoff' => $decision, 'project_created' => false];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
