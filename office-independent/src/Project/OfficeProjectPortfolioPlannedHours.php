<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Read-only, week-bounded planned shift hours across the complete authorized portfolio. */
final class OfficeProjectPortfolioPlannedHours
{
    public static function forWeek(PDO $db, string $operatorId, int $year, int $week): ?int
    {
        if (!ctype_digit($operatorId) || (int)$operatorId < 1 || $year < 2020 || $year > 2100 || $week < 1 || $week > 53) {
            throw new \InvalidArgumentException('Invalid portfolio scope');
        }
        $tz = new DateTimeZone('Europe/Brussels');
        $monday = (new DateTimeImmutable('now', $tz))->setISODate($year, $week, 1)->setTime(0, 0);
        if ((int)$monday->format('o') !== $year || (int)$monday->format('W') !== $week) {
            throw new \InvalidArgumentException('Invalid ISO week');
        }
        $utc = new DateTimeZone('UTC');
        $start = $monday->setTimezone($utc)->format('Y-m-d H:i:s');
        $end = $monday->modify('+7 days')->setTimezone($utc)->format('Y-m-d H:i:s');
        $check = $db->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'office_inzet_shifts'");
        if ((int)$check->fetchColumn() !== 1) return null;
        // Never apply a row LIMIT to a portfolio total; clip shifts crossing week boundaries.
        $sql = "SELECT COUNT(*) AS shift_count,
                SUM(TIMESTAMPDIFF(SECOND,
                    GREATEST(s.starts_at, :start_clip),
                    LEAST(s.ends_at, :end_clip))) AS duration_seconds
            FROM office_inzet_shifts s
            WHERE s.starts_at < :end_filter AND s.ends_at > :start_filter
              AND s.ends_at > s.starts_at
              AND s.status IN ('planned', 'confirmed')
              AND EXISTS (
                SELECT 1 FROM office_project_memberships m
                INNER JOIN office_operator_accounts a ON a.id = m.operator_id
                WHERE m.project_id = s.project_id AND m.operator_id = :operator
                  AND m.permission IN ('read','manage') AND a.active = 1
              )";
        $statement = $db->prepare($sql);
        $statement->execute([
            ':start_clip' => $start, ':end_clip' => $end,
            ':start_filter' => $start, ':end_filter' => $end,
            ':operator' => $operatorId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null;
        // Zero is meaningful only when the source table exists and the query succeeded.
        $seconds = $row['duration_seconds'] === null ? 0 : (int)$row['duration_seconds'];
        if ($seconds < 0) throw new \RuntimeException('Invalid planned duration');
        return $seconds;
    }
}
