<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
require_once __DIR__.'/OfficeCrmRelationPolicy.php';
require_once __DIR__.'/OfficeCrmArchivePaymentEvidence.php';
require_once __DIR__.'/OfficeCrmArchiveProjectEvidence.php';
require_once __DIR__.'/OfficeCrmArchiveQualityEvidence.php';

/** Read-only eligibility preflight; not permission to archive or delete. */
final class OfficeCrmArchiveEligibility
{
    public function __construct(private readonly PDO $db) {}

    public function inspect(int $relationId): array
    {
        if ($relationId<1) throw new \InvalidArgumentException('Invalid relation');
        $q=$this->db->prepare('SELECT relation_kind,status FROM office_crm_relations WHERE id=?');
        $q->execute([$relationId]);
        $relation=$q->fetch(PDO::FETCH_ASSOC);
        if (!$relation) throw new \RuntimeException('Relation unavailable');
        $q=$this->db->prepare('SELECT role_code FROM office_crm_relation_roles WHERE relation_id=?');
        $q->execute([$relationId]);
        $roles=$q->fetchAll(PDO::FETCH_COLUMN);
        $project=new OfficeCrmArchiveProjectEvidence($this->db);
        $payment=new OfficeCrmArchivePaymentEvidence($this->db);
        $quality=new OfficeCrmArchiveQualityEvidence($this->db);
        $evidence=[
            'all_orders_and_projects_completed'=>$project->allOrdersAndProjectsCompleted($relationId),
            'all_invoices_paid'=>$payment->allInvoicesPaid($relationId),
            'no_open_leads_or_deals'=>$project->noOpenCrmWork($relationId),
            'known_quality_and_crm_activities_closed'=>$quality->knownRestpointsAndCrmActivitiesClosed($relationId),
            'no_unapproved_service_forms'=>$quality->noUnapprovedServiceForms($relationId),
            // Partial source coverage is not proof of absence.
            'no_open_actions_or_restpoints'=>false,
            'no_open_complaints'=>false,
            'no_active_maintenance'=>false,
        ];
        return ['eligible'=>$relation['status']==='active' && $evidence['no_unapproved_service_forms'] && $evidence['known_quality_and_crm_activities_closed'] && OfficeCrmRelationPolicy::canAutoArchive($relation['relation_kind'],$roles,$evidence),'evidence'=>$evidence];
    }
}
