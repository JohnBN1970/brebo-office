<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use DomainException;

/**
 * Builds a review-only handoff to the existing canonical Office intake.
 * No mail content is trusted as a verified relation/building identity.
 * The caller must authenticate the reviewer and verify all linked identities.
 */
final class OfficeMailReviewHandoff
{
    public static function prepare(array $proposal, array $review): array
    {
        if (($proposal['status'] ?? null) !== 'PENDING_HUMAN_REVIEW'
            || ($proposal['canonical_office_only'] ?? null) !== true) {
            throw new DomainException('invalid_mail_proposal');
        }
        if (($review['decision'] ?? null) !== 'approve'
            || !is_int($review['reviewer_id'] ?? null)
            || $review['reviewer_id'] < 1) {
            throw new DomainException('human_approval_required');
        }
        $action = $proposal['suggested_action'] ?? null;
        if (!in_array($action, ['crm_intake_review', 'crm_opportunity_review',
            'acceptance_review', 'direct_order_review', 'project_link_review',
            'finance_review', 'inspection_review', 'manual_review'], true)) {
            throw new DomainException('unknown_review_action');
        }
        $verified = $review['verified_links'] ?? [];
        if (!is_array($verified)) {
            throw new DomainException('invalid_verified_links');
        }
        foreach (['relation_id', 'opportunity_id', 'project_id', 'building_id'] as $key) {
            if (array_key_exists($key, $verified)
                && $verified[$key] !== null
                && (!is_int($verified[$key]) || $verified[$key] < 1)) {
                throw new DomainException('invalid_verified_identity');
            }
        }
        // Never auto-accept an order based solely on a mail or model prediction.
        return [
            'source' => 'email',
            'source_request_id' => $proposal['message_id'],
            'reviewer_id' => $review['reviewer_id'],
            'action' => $action,
            'verified_links' => $verified,
            'state' => 'APPROVED_FOR_AUTHORIZED_HANDLER',
            'requires_separate_business_transition' => in_array(
                $action, ['acceptance_review', 'direct_order_review', 'finance_review'], true
            ),
        ];
    }
}
