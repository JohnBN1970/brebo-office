<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

require_once __DIR__.'/OfficeLabourPlannedShiftResolver.php';
require_once __DIR__.'/OfficeLabourShiftAggregatePolicy.php';

/**
 * Transactional aggregate posting prototype. Caller owns the transaction.
 * This service is intentionally not wired to the live approval path yet.
 * Locks the shift before linking approvals, ensuring one debit per shift.
 */
final class OfficeLabourShiftAggregatePostingService
{
    public function __construct(private PDO $db) {}

    public function postInsideTransaction(int $approvedHoursId): int
    {
        if ($approvedHoursId < 1 || !$this->db->inTransaction()) {
            throw new \LogicException('Caller-owned transaction and approved booking required');
        }
        $q=$this->db->prepare('SELECT id,project_id,person_id,work_date,approved_seconds,source_sha256
            FROM office_inzet_approved_hours WHERE id=? FOR UPDATE');
        $q->execute([$approvedHoursId]);
        $approval=$q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($approval)) throw new \DomainException('Approved booking missing');
        $shift=(new OfficeLabourPlannedShiftResolver($this->db))->resolve(
            (int)$approval['project_id'],(int)$approval['person_id'],(string)$approval['work_date']);
        $shiftId=$shift['shift_id'];
        // Serialize all postings for this shift, including competing transactions.
        $lock=$this->db->prepare('SELECT id FROM office_inzet_shifts WHERE id=? FOR UPDATE');
        $lock->execute([$shiftId]);
        if ((int)$lock->fetchColumn()!==$shiftId) throw new \DomainException('Planned shift missing');

        $linked=$this->db->prepare('SELECT shift_id,approved_seconds,source_sha256
            FROM office_inzet_labour_shift_approval_links WHERE approved_hours_id=? FOR UPDATE');
        $linked->execute([$approvedHoursId]);
        $prior=$linked->fetch(PDO::FETCH_ASSOC);
        if (is_array($prior)) {
            if ((int)$prior['shift_id']!==$shiftId
                || (int)$prior['approved_seconds']!==(int)$approval['approved_seconds']
                || !hash_equals((string)$prior['source_sha256'],(string)$approval['source_sha256'])) {
                throw new \DomainException('Linked approval evidence changed');
            }
        } else {
            $add=$this->db->prepare('INSERT INTO office_inzet_labour_shift_approval_links
                (shift_id,approved_hours_id,approved_seconds,source_sha256) VALUES (?,?,?,?)');
            $add->execute([$shiftId,$approvedHoursId,(int)$approval['approved_seconds'],(string)$approval['source_sha256']]);
        }

        $all=$this->db->prepare('SELECT l.approved_hours_id,l.approved_seconds,l.source_sha256,
                a.approved_seconds AS ledger_seconds,a.source_sha256 AS ledger_sha,
                a.project_id,a.person_id,a.work_date
            FROM office_inzet_labour_shift_approval_links l
            JOIN office_inzet_approved_hours a ON a.id=l.approved_hours_id
            WHERE l.shift_id=? ORDER BY l.approved_hours_id FOR UPDATE');
        $all->execute([$shiftId]);
        $items=[];
        foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((int)$row['approved_seconds']!==(int)$row['ledger_seconds']
                || !hash_equals((string)$row['source_sha256'],(string)$row['ledger_sha'])
                || (int)$row['project_id']!==(int)$approval['project_id']
                || (int)$row['person_id']!==(int)$approval['person_id']
                || (string)$row['work_date']!==(string)$approval['work_date']) {
                throw new \DomainException('Shift approval evidence mismatch');
            }
            $items[]=['approval_id'=>(int)$row['approved_hours_id'],
                'approved_seconds'=>(int)$row['approved_seconds']];
        }
        $totals=OfficeLabourShiftAggregatePolicy::calculate($shift['planned_seconds'],$items);
        $q=$this->db->prepare('SELECT * FROM office_inzet_labour_budget_debits WHERE shift_id=? FOR UPDATE');
        $q->execute([$shiftId]);
        $debit=$q->fetch(PDO::FETCH_ASSOC);
        if (is_array($debit)) {
            // Existing one-to-one debits are not silently adopted: require
            // anchor approval to be in the immutable link ledger.
            $anchors=array_column($items,'approval_id');
            if (!in_array((int)$debit['approved_hours_id'],$anchors,true)
                || (int)$debit['project_id']!==(int)$approval['project_id']
                || (int)$debit['person_id']!==(int)$approval['person_id']
                || (string)$debit['work_date']!==(string)$approval['work_date']
                || (int)$debit['planned_seconds']!==$shift['planned_seconds']) {
                throw new \DomainException('Existing shift debit cannot be safely aggregated');
            }
            $anchor=$this->db->prepare('SELECT source_sha256 FROM office_inzet_approved_hours WHERE id=?');
            $anchor->execute([(int)$debit['approved_hours_id']]);
            if (!hash_equals((string)$debit['source_sha256'],(string)$anchor->fetchColumn())) {
                throw new \DomainException('Existing debit anchor evidence changed');
            }
            $update=$this->db->prepare('UPDATE office_inzet_labour_budget_debits
                SET approved_seconds=?,variance_seconds=? WHERE id=?');
            $update->execute([$totals['approved_seconds'],$totals['variance_seconds'],(int)$debit['id']]);
            return (int)$debit['id'];
        }
        $insert=$this->db->prepare('INSERT INTO office_inzet_labour_budget_debits
            (approved_hours_id,shift_id,project_id,person_id,work_date,approved_seconds,
             planned_seconds,budget_debit_seconds,variance_seconds,source_sha256)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$approvedHoursId,$shiftId,(int)$approval['project_id'],
            (int)$approval['person_id'],(string)$approval['work_date'],
            $totals['approved_seconds'],$shift['planned_seconds'],$totals['budget_debit_seconds'],
            $totals['variance_seconds'],(string)$approval['source_sha256']]);
        return (int)$this->db->lastInsertId();
    }
}
