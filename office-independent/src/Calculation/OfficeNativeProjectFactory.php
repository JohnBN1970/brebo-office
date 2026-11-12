<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/**
 * Native Office project creation callback for the atomic CRM/Calc handoff.
 * The caller owns the transaction. Never creates a project without a managing
 * operator membership. No Drupal project or separate database is involved.
 */
final class OfficeNativeProjectFactory
{
    public static function create(PDO $db, array $project): int
    {
        if (!$db->inTransaction()) {
            throw new DomainException('Native project creation requires caller transaction');
        }
        $code = trim((string)($project['code'] ?? ''));
        $title = trim((string)($project['title'] ?? ''));
        $actor = $project['operator_id'] ?? null;
        if ($code === '' || strlen($code) > 64 || $title === '' || mb_strlen($title) > 255
            || !is_int($actor) || $actor < 1) {
            throw new DomainException('Missing native project identity or owner');
        }
        // Fail closed on schema differences instead of silently omitting required
        // fields in the real Office project table.
        $columns = $db->query(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'office_projects'"
        )->fetchAll(PDO::FETCH_ASSOC);
        $required = [];
        foreach ($columns as $column) {
            if ($column['IS_NULLABLE'] === 'NO' && $column['COLUMN_DEFAULT'] === null
                && !str_contains(strtolower((string)$column['EXTRA']), 'auto_increment')
                && !str_contains(strtolower((string)$column['EXTRA']), 'generated')) {
                $required[] = (string)$column['COLUMN_NAME'];
            }
        }
        $known = array_column($columns, 'COLUMN_NAME');
        foreach (['id','code','title','status'] as $name) {
            if (!in_array($name, $known, true)) throw new DomainException('Unsupported native project schema');
        }
        if (array_diff($required, ['code','title','status']) !== []) {
            throw new DomainException('Native project requires additional mandatory fields');
        }
        $operator = $db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 FOR UPDATE');
        $operator->execute([$actor]);
        if ($operator->fetchColumn() === false) throw new DomainException('Inactive project owner');
        $existing = $db->prepare('SELECT id FROM office_projects WHERE code=? FOR UPDATE');
        $existing->execute([$code]);
        if ($existing->fetchColumn() !== false) throw new DomainException('Project code already exists');
        $insert = $db->prepare('INSERT INTO office_projects (code,title,status) VALUES (?,?,?)');
        $insert->execute([$code,$title,'active']);
        $projectId = (int)$db->lastInsertId();
        if ($projectId < 1) throw new DomainException('Native project ID missing');
        $membership = $db->prepare(
            "INSERT INTO office_project_memberships (project_id,operator_id,permission) VALUES (?,?,'manage')"
        );
        $membership->execute([$projectId,$actor]);
        return $projectId;
    }
}
