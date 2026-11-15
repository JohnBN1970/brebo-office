<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Pure approval policy; persistence, authentication and ACL must be supplied
 * by the existing Office project services. Never infer authority from a role
 * label or a user-supplied request payload.
 */
final class OfficeProjectKickstartPolicy
{
    public const REQUIRED_ITEMS = [
        'bouwplaatsdocumenten',
        'werkinstructie',
        'projectteam',
        'logistiek_deelleveringen',
        'kickstart_verslag',
        'open_punten_beoordeeld',
    ];

    public static function missingItems(array $items): array
    {
        return array_values(array_filter(
            self::REQUIRED_ITEMS,
            static fn (string $key): bool => ($items[$key] ?? false) !== true
        ));
    }

    /**
     * The caller must load both identities, current dossier revision and
     * approval records from trusted storage, never from submitted form fields.
     *
     * @param array<string, mixed> $dossier
     * @param array<string, mixed>|null $firstApproval
     * @return array{allowed:bool,reasons:list<string>}
     */
    public static function evaluateSecondApproval(
        array $dossier,
        ?array $firstApproval,
        int $secondApproverId,
        bool $secondApproverAuthorized
    ): array {
        $reasons = [];
        if ($secondApproverId <= 0 || !$secondApproverAuthorized) {
            $reasons[] = 'second_approver_not_authorized';
        }
        if (($dossier['status'] ?? null) !== 'awaiting_second_approval') {
            $reasons[] = 'invalid_dossier_status';
        }
        if (!is_int($dossier['revision'] ?? null) || $dossier['revision'] < 1) {
            $reasons[] = 'invalid_dossier_revision';
        }
        if (!empty(self::missingItems(is_array($dossier['items'] ?? null) ? $dossier['items'] : []))) {
            $reasons[] = 'incomplete_start_dossier';
        }
        if (($dossier['critical_open_points'] ?? null) !== 0) {
            $reasons[] = 'critical_open_points';
        }
        if ($firstApproval === null || ($firstApproval['decision'] ?? null) !== 'approved') {
            $reasons[] = 'first_approval_missing';
        } else {
            if (!is_int($firstApproval['approver_id'] ?? null) || $firstApproval['approver_id'] <= 0) {
                $reasons[] = 'invalid_first_approver';
            } elseif ($firstApproval['approver_id'] === $secondApproverId) {
                $reasons[] = 'four_eyes_violation';
            }
            if (($firstApproval['dossier_revision'] ?? null) !== ($dossier['revision'] ?? null)) {
                $reasons[] = 'approval_revision_mismatch';
            }
        }
        if (($dossier['first_approver_role'] ?? null) !== 'projectleider') {
            $reasons[] = 'first_approval_role_invalid';
        }
        return ['allowed' => $reasons === [], 'reasons' => $reasons];
    }

    public static function needsNewApproval(string $changeType): bool
    {
        return in_array($changeType, [
            'werkinstructie', 'veiligheid', 'bouwplaatsdocumenten',
            'projectteam_kritiek', 'logistiek_deelleveringen', 'scope',
            'kickstart_besluit',
        ], true);
    }
}
