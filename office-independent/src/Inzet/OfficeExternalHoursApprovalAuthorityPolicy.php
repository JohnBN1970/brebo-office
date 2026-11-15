<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Authority decision only. Caller must derive verified role and permission
 * from the Office session/authorization repository, never from request input.
 * Database schema currently requires two distinct reviewers; single approval
 * must not be persisted until an audited schema migration is installed.
 */
final class OfficeExternalHoursApprovalAuthorityPolicy
{
    public static function determine(
        string $actorId,
        bool $verifiedHighestRole,
        bool $hasProjectApprovalPermission,
        ?string $secondReviewerId,
        string $reason
    ): array {
        $actorId=trim($actorId);
        $secondReviewerId=$secondReviewerId===null ? null : trim($secondReviewerId);
        $reason=trim($reason);
        if ($actorId==='' || strlen($actorId)>128 || !$hasProjectApprovalPermission) {
            throw new \DomainException('Approval permission required');
        }
        if (strlen($reason)<10 || strlen($reason)>2000) {
            throw new \DomainException('Documented approval reason required');
        }
        if ($secondReviewerId!==null && $secondReviewerId!=='') {
            if (strlen($secondReviewerId)>128 || hash_equals($actorId,$secondReviewerId)) {
                throw new \DomainException('Independent second reviewer required');
            }
            return ['mode'=>'four_eyes','first_reviewer'=>$actorId,
                'second_reviewer'=>$secondReviewerId,'reason'=>$reason];
        }
        if (!$verifiedHighestRole) {
            throw new \DomainException('Second reviewer required for non-highest role');
        }
        return ['mode'=>'highest_role_single','first_reviewer'=>$actorId,
            'second_reviewer'=>null,'reason'=>$reason];
    }
}
