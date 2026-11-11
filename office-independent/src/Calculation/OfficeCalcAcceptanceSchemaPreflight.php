<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/** Read-only preflight: never creates or alters a database table. */
final class OfficeCalcAcceptanceSchemaPreflight
{
    public static function check(PDO $db): array
    {
        $required = [
            'id', 'opportunity_id', 'calculation_id', 'accepted_revision',
            'status', 'accepted_at', 'accepted_by',
        ];
        $stmt = $db->query('SHOW COLUMNS FROM office_crm_calculation_acceptances');
        $found = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $found[] = (string)$column['Field'];
        }
        $missing = array_values(array_diff($required, $found));
        return ['ready' => $missing === [], 'missing_columns' => $missing];
    }
}
