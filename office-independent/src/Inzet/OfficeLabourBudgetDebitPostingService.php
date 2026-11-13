<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

require_once __DIR__ . '/OfficeLabourBudgetDebitPolicy.php';
require_once __DIR__ . '/OfficeLabourPlannedShiftResolver.php';

/**
 * Posts budget consumption only for an already verified approved-hours row.
 * A shift can be consumed once; no partial/multiple approvals are silently
 * converted into multiple full-day debits.
 */
final class OfficeLabourBudgetDebitPostingService
{
    public function __construct(private PDO $db) {}

    public function post(int $approvedHoursId): int
    {
        if ($approvedHoursId < 1) throw new \InvalidArgumentException('Invalid approval booking');
        if ($this->db->inTransaction()) throw new \LogicException('Budget posting owns its transaction');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id,project_id,person_id,work_date,approved_seconds,source_sha256
                FROM office_inzet_approved_hours WHERE id = ? FOR UPDATE');
            $stmt->execute([$approvedHoursId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($entry)) throw new \DomainException('Approved hours booking not found');
            $existing = $this->db->prepare('SELECT id,source_sha256 FROM office_inzet_labour_budget_debits
                WHERE approved_hours_id = ? FOR UPDATE');
            $existing->execute([$approvedHoursId]);
            $previous = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($previous)) {
                if (!hash_equals((string)$previous['source_sha256'], (string)$entry['source_sha256'])) {
                    throw new \DomainException('Existing budget debit evidence changed');
                }
                $this->db->commit();
                return (int)$previous['id'];
            }
            $shift = (new OfficeLabourPlannedShiftResolver($this->db))->resolve(
                (int)$entry['project_id'], (int)$entry['person_id'], (string)$entry['work_date']
            );
            $amounts = OfficeLabourBudgetDebitPolicy::calculate(
                (int)$entry['approved_seconds'], $shift['planned_seconds']
            );
            $insert = $this->db->prepare('INSERT INTO office_inzet_labour_budget_debits
                (approved_hours_id,shift_id,project_id,person_id,work_date,approved_seconds,
                 planned_seconds,budget_debit_seconds,variance_seconds,source_sha256)
                VALUES (?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([
                $approvedHoursId, $shift['shift_id'], (int)$entry['project_id'],
                (int)$entry['person_id'], (string)$entry['work_date'],
                $amounts['approved_seconds'], $shift['planned_seconds'],
                $amounts['budget_debit_seconds'], $amounts['variance_seconds'],
                (string)$entry['source_sha256'],
            ]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
