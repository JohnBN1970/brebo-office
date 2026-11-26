<?php
declare(strict_types=1);

/**
 * Aggregate, read-only OnSite rollout diagnostics. Never return identities,
 * rates, amounts or source events to CI logs.
 */
final class OfficeOnSiteRolloutReadiness
{
    /** Do not silently weaken an already enabled four-eyes policy. */
    public static function enableFourEyesForTest(array $report, bool $alreadyEnabled): bool
    {
        return $alreadyEnabled || (
            ($report['four_eyes_readiness'] ?? '') === 'READY_FOR_CONTROLLED_TRIAL'
            && (int)($report['onsite_real_arrivals'] ?? 0) > 0
            && (int)($report['reviewer_project_assignments'] ?? 0) > 0
            && (int)($report['reviewer_assignments_without_unique_active_mapping'] ?? -1) === 0
        );
    }

    public static function inspect(PDO $db): array
    {
        // Only active reviewers for non-CI projects with actual OnSite events.
        // A reviewer needs exactly one mapping to an active employee. An
        // employee's own sessions are always blocked by the per-review guard.
        $reviewerSql = "SELECT m.project_id,m.operator_id,
                COUNT(op.person_id) AS mapping_count,
                COALESCE(SUM(CASE WHEN people.id IS NOT NULL THEN 1 ELSE 0 END),0) AS valid_count
            FROM office_project_memberships m
            JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            JOIN office_projects p ON p.id=m.project_id
            LEFT JOIN office_account_personnel_links op ON op.operator_id=m.operator_id
            LEFT JOIN office_inzet_people people ON people.id=op.person_id AND people.active=1
            WHERE m.permission='manage' AND p.code NOT LIKE 'BREBO-CI-%'
              AND EXISTS (SELECT 1 FROM office_onsite_event_inbox e WHERE e.project_id=m.project_id)
            GROUP BY m.project_id,m.operator_id";
        $reviewers=$db->query($reviewerSql)->fetchAll(PDO::FETCH_ASSOC);
        $invalidReviewers=0;
        foreach ($reviewers as $reviewer) {
            if ((int)$reviewer['mapping_count']!==1 || (int)$reviewer['valid_count']!==1) ++$invalidReviewers;
        }

        // Only unposted approved-hours rows can need a new rate. An authorized
        // rate must be unique for the approval's project/person/date.
        $rateSql = "SELECT ah.id, COUNT(r.id) AS matching_rates
            FROM office_inzet_approved_hours ah
            JOIN office_projects p ON p.id=ah.project_id
            LEFT JOIN office_inzet_actual_labour_costs cost ON cost.approved_hours_id=ah.id
            LEFT JOIN office_inzet_actual_labour_rates r
              ON r.project_id=ah.project_id AND r.person_id=ah.person_id
              AND r.valid_from<=ah.work_date
              AND (r.valid_until IS NULL OR r.valid_until>=ah.work_date)
              AND r.revoked_at IS NULL AND r.hourly_rate_cents>0
              AND r.authorized_by<>''
            WHERE ah.source_kind='onsite_review' AND ah.approved_seconds>0
              AND p.code NOT LIKE 'BREBO-CI-%' AND cost.id IS NULL
            GROUP BY ah.id";
        $approvals=$db->query($rateSql)->fetchAll(PDO::FETCH_ASSOC);
        $invalidRates=0;
        foreach ($approvals as $approval) {
            if ((int)$approval['matching_rates']!==1) ++$invalidRates;
        }
        $eventCount=(int)$db->query("SELECT COUNT(*) FROM office_onsite_event_inbox e
            JOIN office_projects p ON p.id=e.project_id
            WHERE p.code NOT LIKE 'BREBO-CI-%' AND e.event_kind='in'")->fetchColumn();
        $postedCount=(int)$db->query("SELECT COUNT(*) FROM office_inzet_actual_labour_costs cost
            JOIN office_projects p ON p.id=cost.project_id
            WHERE p.code NOT LIKE 'BREBO-CI-%'")->fetchColumn();
        return [
            'onsite_real_arrivals'=>$eventCount,
            'reviewer_project_assignments'=>count($reviewers),
            'reviewer_assignments_without_unique_active_mapping'=>$invalidReviewers,
            'unposted_approved_hours_entries'=>count($approvals),
            'unposted_entries_without_unique_authorized_rate'=>$invalidRates,
            'already_posted_actual_labour_entries'=>$postedCount,
            'four_eyes_readiness'=>$eventCount===0?'NO_REAL_EVENTS':
                (count($reviewers)===0 || $invalidReviewers!==0?'BLOCKED':'READY_FOR_CONTROLLED_TRIAL'),
            'actual_labour_readiness'=>count($approvals)===0?'NO_UNPOSTED_APPROVALS':
                ($invalidRates!==0?'BLOCKED':'READY_FOR_CONTROLLED_TRIAL'),
        ];
    }
}
