<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use PDO;
use Throwable;

/**
 * Read-only persisted identity proof for a projectless CRM-first Calc.
 *
 * The CRM draft id is the Office calculation key (office_calculation_id),
 * NOT calculations.id. No draft identity is trusted from a URL alone.
 * The session/CRM edit permission must be verified by the HTTP boundary.
 */
final class OfficeCalcCrmCanonicalLookup
{
    public function __construct(private readonly PDO $db) {}

    public function lookup(int $actorId, int $opportunityId, int $crmRevision): array
    {
        if ($actorId < 1 || $opportunityId < 1 || $crmRevision < 1) {
            throw new DomainException('Invalid CRM Calc lookup');
        }
        try {
            $statement = $this->db->prepare(
                'SELECT d.id AS crm_draft_id, d.opportunity_id, d.crm_revision,
                        c.id AS calc_id, c.office_calculation_id
                   FROM office_calc_crm_drafts d
                   INNER JOIN office_crm_opportunities o ON o.id = d.opportunity_id
                   INNER JOIN calculations c ON c.office_calculation_id = d.id
                  WHERE d.opportunity_id = :opportunity_id
                    AND d.crm_revision = :crm_revision
                    AND d.created_by = :actor_id
                    AND d.project_id IS NULL
                    AND o.revision = d.crm_revision
                    AND o.status = :open_status
                    AND o.stage IN (\'prospect\',\'opportunity\',\'proposal\',\'negotiation\')
                    AND c.office_project_id IS NULL
                    AND c.status = :calc_status
                  LIMIT 2'
            );
            $statement->execute([
                ':opportunity_id'=>$opportunityId,
                ':crm_revision'=>$crmRevision,
                ':actor_id'=>$actorId,
                ':open_status'=>'open',
                ':calc_status'=>'draft',
            ]);
            $rows=$statement->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows)!==1) {
                throw new DomainException('Missing or ambiguous canonical Calc draft');
            }
            $row=$rows[0];
            foreach (['crm_draft_id','opportunity_id','crm_revision','calc_id','office_calculation_id'] as $key) {
                if (!is_numeric($row[$key]??null) || (int)$row[$key]<1) {
                    throw new DomainException('Invalid canonical Calc identity');
                }
            }
            if ((int)$row['crm_draft_id'] !== (int)$row['office_calculation_id']
                || (int)$row['opportunity_id'] !== $opportunityId
                || (int)$row['crm_revision'] !== $crmRevision) {
                throw new DomainException('Canonical Calc mapping mismatch');
            }
            return [
                'crm_draft_id'=>(int)$row['crm_draft_id'],
                'calc_id'=>(int)$row['calc_id'],
                'opportunity_id'=>$opportunityId,
                'crm_revision'=>$crmRevision,
            ];
        } catch (Throwable $error) {
            throw new DomainException('CRM Calc canonical lookup denied',0,$error);
        }
    }
}
