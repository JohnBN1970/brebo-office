<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

/**
 * Fail-closed permission boundary for Office communication review.
 * Callers must resolve these grants from the authenticated Office session.
 */
final class OfficeCommunicationReviewAccess
{
    /** @param list<string> $grantedPermissions */
    public static function canReview(bool $authenticated, array $grantedPermissions): bool
    {
        return $authenticated && in_array('communication.review', $grantedPermissions, true);
    }

    /** @param list<string> $grantedPermissions */
    public static function canRead(bool $authenticated, array $grantedPermissions): bool
    {
        return $authenticated && (
            in_array('communication.read', $grantedPermissions, true)
            || in_array('communication.review', $grantedPermissions, true)
        );
    }
}
