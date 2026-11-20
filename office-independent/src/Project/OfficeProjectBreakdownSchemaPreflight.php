<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;

/** Read-only safety gate for the Office-test WBS/RBS additive migration. */
final class OfficeProjectBreakdownSchemaPreflight
{
    private const PARENTS = [
        'office_projects' => 'id',
        'office_risks' => 'id',
    ];

    public static function verify(PDO $db): array
    {
        $columns = $db->query(
            "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('office_projects','office_risks') " .
            "AND COLUMN_NAME = 'id'"
        )->fetchAll(PDO::FETCH_ASSOC);

        $found = [];
        foreach ($columns as $column) {
            $found[$column['TABLE_NAME']][$column['COLUMN_NAME']] = strtolower($column['COLUMN_TYPE']);
        }
        $errors = [];
        foreach (self::PARENTS as $table => $column) {
            if (($found[$table][$column] ?? null) !== 'bigint unsigned') {
                $errors[] = $table . '.' . $column . ': expected bigint unsigned, found ' .
                    ($found[$table][$column] ?? 'missing');
            }
        }
        return ['verified' => $errors === [], 'errors' => $errors];
    }

    public static function assertReady(PDO $db): void
    {
        $result = self::verify($db);
        if (!$result['verified']) {
            throw new RuntimeException('WBS_RBS_PARENT_SCHEMA_UNVERIFIED: ' . implode('; ', $result['errors']));
        }
    }
}
