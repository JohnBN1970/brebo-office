<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/**
 * Read-only diagnostics for the legacy sales invoice source.
 * No credentials, customer data, invoice rows or SQL error details are exposed.
 */
final class OfficeSalesInvoicePreflight
{
    /** @return array<string,mixed> */
    public static function inspect(PDO $officeDb, PDO $salesDb): array
    {
        $required = OfficeSalesInvoiceSourceMap::releaseTables();
        $required[] = OfficeSalesInvoiceSourceMap::ISSUED;

        $tables = [];
        foreach ($required as $table) {
            $query = $salesDb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $query->execute([$table]);
            $tables[$table] = ((int) $query->fetchColumn() === 1);
        }

        $officeTables = [
            'office_operator_accounts',
            'office_module_permissions',
            'office_projects',
            'office_project_memberships',
            'office_finance_project_mapping',
        ];
        $office = [];
        foreach ($officeTables as $table) {
            $query = $officeDb->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $query->execute([$table]);
            $office[$table] = ((int) $query->fetchColumn() === 1);
        }

        return [
            'source_tables' => $tables,
            'office_tables' => $office,
            'source_ready' => !in_array(false, $tables, true),
            'office_ready' => !in_array(false, $office, true),
            'sales_read_flag' => getenv('OFFICE_FINANCE_SALES_READ_ENABLED') === 'enabled',
            'read_only_expected' => true,
        ];
    }
}
