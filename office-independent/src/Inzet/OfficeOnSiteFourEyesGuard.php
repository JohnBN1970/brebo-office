<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Fail-closed reviewer/person separation for the new aggregate posting path.
 * An operator without exactly one verified person mapping cannot approve.
 */
final class OfficeOnSiteFourEyesGuard
{
    public function __construct(private PDO $db) {}

    public function assertIndependent(string $reviewerOperatorId, int $personId): void
    {
        if ($reviewerOperatorId === '' || $personId < 1) {
            throw new \InvalidArgumentException('Reviewer and employee required');
        }
        // A stale mapping to an inactive person is not a verified reviewer.
        // Query all mappings first so a duplicate inactive mapping cannot be hidden.
        $q=$this->db->prepare('SELECT op.person_id,p.active FROM office_account_personnel_links op
            LEFT JOIN office_inzet_people p ON p.id=op.person_id
            JOIN office_operator_accounts a ON a.id=op.operator_id AND a.active=1 WHERE op.operator_id=?');
        $q->execute([$reviewerOperatorId]);
        $mappings=$q->fetchAll(PDO::FETCH_ASSOC);
        if (count($mappings)!==1) {
            throw new \DomainException('Four-eyes review requires one verified reviewer identity');
        }
        $reviewerPerson=filter_var($mappings[0]['person_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!is_int($reviewerPerson) || (int)$mappings[0]['active']!==1 || $reviewerPerson===$personId) {
            throw new \DomainException('OnSite self-approval prohibited');
        }
    }
}
