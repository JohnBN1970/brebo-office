<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Resolve exactly one confirmed/planned Office shift for an approved work date.
 * Read-only. Never guesses a default 8-hour shift or combines unrelated shifts.
 */
final class OfficeLabourPlannedShiftResolver
{
    public function __construct(private PDO $db) {}

    public function resolve(int $projectId, int $personId, string $workDate): array
    {
        if ($projectId < 1 || $personId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $workDate)) {
            throw new \InvalidArgumentException('Invalid planned shift identity');
        }
        $tz = new DateTimeZone('Europe/Brussels');
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $workDate, $tz);
        if (!$day || $day->format('Y-m-d') !== $workDate) {
            throw new \InvalidArgumentException('Invalid work date');
        }
        $utc = new DateTimeZone('UTC');
        $start = $day->setTimezone($utc)->format('Y-m-d H:i:s');
        $end = $day->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare("SELECT s.id, s.starts_at, s.ends_at, s.status
            FROM office_inzet_shifts s
            WHERE s.project_id = :project AND s.person_id = :person
              AND s.status IN ('planned','confirmed')
              AND s.starts_at < :end_at AND s.ends_at > :start_at
              AND s.ends_at > s.starts_at
            ORDER BY s.id LIMIT 2");
        $stmt->execute([
            ':project'=>$projectId, ':person'=>$personId,
            ':start_at'=>$start, ':end_at'=>$end,
        ]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($shifts) !== 1) {
            throw new \DomainException('Exactly one verified planned shift required');
        }
        $shift = $shifts[0];
        $shiftStart = max(strtotime((string)$shift['starts_at'].' UTC'), strtotime($start.' UTC'));
        $shiftEnd = min(strtotime((string)$shift['ends_at'].' UTC'), strtotime($end.' UTC'));
        $seconds = $shiftEnd - $shiftStart;
        if ($seconds < 1 || $seconds > 86400) {
            throw new \DomainException('Invalid planned shift duration');
        }
        return ['shift_id'=>(int)$shift['id'], 'planned_seconds'=>$seconds];
    }
}
