<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use PDO;
use Throwable;

/**
 * Transactional acceptance snapshot writer. Caller must supply verified,
 * canonical Calc source via callback using the SAME PDO connection.
 * No route, feature flag, project creation or deployment is enabled here.
 */
final class OfficeCalcAcceptanceSnapshotWriter
{
    public function __construct(private readonly PDO $db) {}

    public function accept(int $opportunityId, int $calculationId, string $revision,
        int $actorId, callable $readCanonicalCalc): void
    {
        $this->write($opportunityId,$calculationId,$revision,$actorId,$readCanonicalCalc,false);
    }

    /** Join the quotation handoff transaction; its caller owns commit/rollback. */
    public function acceptInTransaction(int $opportunityId,int $calculationId,string $revision,
        int $actorId,callable $readCanonicalCalc):void
    {
        $this->write($opportunityId,$calculationId,$revision,$actorId,$readCanonicalCalc,true);
    }

    private function write(int $opportunityId,int $calculationId,string $revision,
        int $actorId,callable $readCanonicalCalc,bool $joined):void
    {
        if ($opportunityId < 1 || $calculationId < 1 || $actorId < 1 || trim($revision) === ''
            || ($joined ? !$this->db->inTransaction() : $this->db->inTransaction())
            || $this->db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new DomainException('Invalid Calc acceptance transaction context');
        }
        if (!$joined) $this->db->beginTransaction();
        try {
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $q = $this->db->prepare('SELECT stage,status FROM office_crm_opportunities WHERE id=?'.$suffix);
            $q->execute([$opportunityId]);
            $opportunity = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($opportunity) || $opportunity['stage'] !== 'won'
                || $opportunity['status'] !== 'closed') {
                throw new DomainException('CRM opportunity must be won and closed');
            }
            $q = $this->db->prepare('SELECT 1 FROM office_crm_calc_acceptances WHERE opportunity_id=?'.$suffix);
            $q->execute([$opportunityId]);
            if ($q->fetchColumn() !== false) {
                throw new DomainException('CRM Calc acceptance already exists');
            }
            // The source adapter must verify actor rights, canonical Calc identity,
            // immutable revision and all source lines under this same transaction.
            $source = $readCanonicalCalc($this->db, $opportunityId, $calculationId, $revision, $actorId);
            if (!$this->db->inTransaction() || !is_array($source)
                || ($source['verified'] ?? null) !== true
                || ($source['calculation_id'] ?? null) !== $calculationId
                || ($source['revision'] ?? null) !== $revision
                || !is_array($source['lines'] ?? null) || $source['lines'] === []) {
                throw new DomainException('Canonical Calc source was not verified');
            }
            require_once __DIR__.'/OfficeCalcWorkBudgetHandoff.php';
            $budget = OfficeCalcWorkBudgetHandoff::prepare([
                'crm_opportunity_id'=>(string)$opportunityId,
                'calculation_id'=>$calculationId,
                'accepted_revision'=>$revision,
                'status'=>'accepted',
            ], $source['lines']);
            $q = $this->db->prepare('INSERT INTO office_crm_calc_acceptances (opportunity_id,calculation_id,accepted_revision,status,accepted_by) VALUES (?,?,?, ?,?)');
            $q->execute([$opportunityId,$calculationId,$revision,'accepted',$actorId]);
            $q = $this->db->prepare('INSERT INTO office_crm_calc_accepted_lines (opportunity_id,source_line_id,cost_type,quantity,unit_cost_cents,activity_ref) VALUES (?,?,?,?,?,?)');
            foreach ($budget['items'] as $line) {
                $q->execute([$opportunityId,$line['source_line_id'],$line['cost_type'],
                    $line['quantity'],$line['unit_cost_cents'],$line['activity_ref']]);
            }
            require_once __DIR__.'/OfficeCalcPersistedAcceptanceVerifier.php';
            $verify = new OfficeCalcPersistedAcceptanceVerifier();
            if (!$this->db->inTransaction() || !$verify($this->db, [
                'crm_opportunity_id'=>(string)$opportunityId,
                'calculation_id'=>$calculationId,
                'accepted_revision'=>$revision,
                'status'=>'accepted',
            ],$source['lines'])) {
                throw new DomainException('Persisted CRM Calc acceptance differs from canonical source');
            }
            if (!$joined) $this->db->commit();
        } catch (Throwable $e) {
            if (!$joined && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
