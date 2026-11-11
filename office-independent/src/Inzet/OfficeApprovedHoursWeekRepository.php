<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Project\OfficeProjectPortfolioPlannedHours;
use PDO;

/** Read-only approved hours; access is enforced inside SQL. */
final class OfficeApprovedHoursWeekRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Returns null when the ledger has not been installed.
     * Returns integer seconds (including zero) only from approved ledger entries.
     */
    public function forOperatorWeek(string $operatorId, int $year, int $week): ?int
    {
        if ($operatorId === '' || strlen($operatorId) > 128) {
            throw new \InvalidArgumentException('Invalid operator');
        }
        $bounds = OfficeProjectPortfolioPlannedHours::utcWeekBounds($year, $week);
        $check = $this->db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'office_inzet_approved_hours'");
        if ((int)$check->fetchColumn() !== 1) return null;
        $startDate = (new \DateTimeImmutable($bounds['start'], new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('Y-m-d');
        $endDate = (new \DateTimeImmutable($bounds['end'], new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Europe/Brussels'))->format('Y-m-d');
        $stmt = $this->db->prepare("SELECT COUNT(*) AS entry_count, SUM(h.approved_seconds) AS total_seconds
            FROM office_inzet_approved_hours h
            WHERE h.work_date >= :start_date AND h.work_date < :end_date
              AND EXISTS (
                SELECT 1 FROM office_project_memberships m
                INNER JOIN office_operator_accounts a ON a.id = m.operator_id
                WHERE m.project_id = h.project_id AND m.operator_id = :operator
                  AND m.permission IN ('read','manage') AND a.active = 1
              )");
        $stmt->execute([':start_date'=>$startDate, ':end_date'=>$endDate, ':operator'=>$operatorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (int)$row['entry_count'] === 0) return null;
        $value = $row['total_seconds'];
        if ($value === false || !is_numeric($value) || (float)$value > PHP_INT_MAX || (float)$value < 0) {
            throw new \RuntimeException('Invalid approved hours total');
        }
        return (int)$value;
    }
}
