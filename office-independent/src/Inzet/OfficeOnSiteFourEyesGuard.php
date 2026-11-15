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
        $q=$this->db->prepare('SELECT person_id FROM office_operator_people WHERE operator_id=?');
        $q->execute([$reviewerOperatorId]);
        $mappings=$q->fetchAll(PDO::FETCH_COLUMN);
        if (count($mappings)!==1) {
            throw new \DomainException('Four-eyes review requires one verified reviewer identity');
        }
        $reviewerPerson=filter_var($mappings[0],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!is_int($reviewerPerson) || $reviewerPerson===$personId) {
            throw new \DomainException('OnSite self-approval prohibited');
        }
    }
}
