<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Append-only approved hours. The caller must authorize the reviewer and
 * supply an independently verified approval decision and source digest.
 */
final class OfficeApprovedHoursBooking
{
    public function __construct(private PDO $db) {}

    /** @param array<string,mixed> $entry */
    public function append(array $entry): int
    {
        foreach (['project_id','person_id','approved_seconds'] as $key) {
            if (!isset($entry[$key]) || !is_int($entry[$key])) {
                throw new \InvalidArgumentException('Invalid approved hours identity or duration');
            }
        }
        if ($entry['project_id'] < 1 || $entry['person_id'] < 1
            || $entry['approved_seconds'] < 0 || $entry['approved_seconds'] > 86400) {
            throw new \InvalidArgumentException('Invalid approved hours range');
        }
        $date = $entry['work_date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
            throw new \InvalidArgumentException('Invalid work date');
        }
        foreach (['source_kind','source_reference','approved_by','approval_reference','approved_at','source_sha256'] as $key) {
            if (!isset($entry[$key]) || !is_string($entry[$key]) || trim($entry[$key]) === '') {
                throw new \InvalidArgumentException('Missing verified approval evidence');
            }
        }
        if (!in_array($entry['source_kind'], ['onsite_review','supplier_week_review'], true)
            || strlen($entry['source_reference']) > 128
            || strlen($entry['approved_by']) > 128
            || strlen($entry['approval_reference']) > 128
            || !preg_match('/^[a-f0-9]{64}$/D', $entry['source_sha256'])
            || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $entry['approved_at'])) {
            throw new \InvalidArgumentException('Invalid approval evidence');
        }
        $authority = $this->db->prepare("SELECT 1
            FROM office_project_memberships m
            INNER JOIN office_operator_accounts a ON a.id = m.operator_id
            WHERE m.project_id = :project_id AND m.operator_id = :reviewer
              AND m.permission = 'manage' AND a.active = 1 LIMIT 1");
        $authority->execute([
            ':project_id' => $entry['project_id'],
            ':reviewer' => $entry['approved_by'],
        ]);
        if ($authority->fetchColumn() === false) {
            throw new \DomainException('Reviewer is not authorized to approve hours for this project');
        }
        // A source identifier is unique: retries cannot double-book approved hours.
        $stmt = $this->db->prepare('INSERT INTO office_inzet_approved_hours
            (project_id,person_id,work_date,approved_seconds,source_kind,source_reference,
             source_sha256,approved_by,approved_at,approval_reference)
            VALUES (:project_id,:person_id,:work_date,:approved_seconds,:source_kind,:source_reference,
             :source_sha256,:approved_by,:approved_at,:approval_reference)');
        $stmt->execute([
            ':project_id'=>$entry['project_id'], ':person_id'=>$entry['person_id'],
            ':work_date'=>$date, ':approved_seconds'=>$entry['approved_seconds'],
            ':source_kind'=>$entry['source_kind'], ':source_reference'=>$entry['source_reference'],
            ':source_sha256'=>$entry['source_sha256'], ':approved_by'=>$entry['approved_by'],
            ':approved_at'=>$entry['approved_at'], ':approval_reference'=>$entry['approval_reference'],
        ]);
        return (int)$this->db->lastInsertId();
    }
}
