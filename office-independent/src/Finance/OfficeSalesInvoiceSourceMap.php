<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Existing sales-invoice persistence contract.
 *
 * These names describe the legacy source; they do not authorize Office to
 * write to that source or imply that the tables exist in the Office database.
 */
final class OfficeSalesInvoiceSourceMap
{
    public const DRAFT = 'brebo_finance_sales_invoice_draft';
    public const DRAFT_LINE = 'brebo_finance_sales_invoice_draft_line';
    public const OUTBOX = 'brebo_finance_sales_invoice_outbox';
    public const ISSUED = 'brebo_finance_sales_invoice';

    /** @return list<string> */
    public static function draftTables(): array
    {
        return [self::DRAFT, self::DRAFT_LINE];
    }

    /** @return list<string> */
    public static function releaseTables(): array
    {
        return [self::DRAFT, self::DRAFT_LINE, self::OUTBOX];
    }

    /**
     * No HTTP-triggered schema creation. Returns missing table names only.
     *
     * @return list<string>
     */
    public static function missingTables(\PDO $connection, bool $release = false): array
    {
        $required = $release ? self::releaseTables() : self::draftTables();
        $missing = [];
        foreach ($required as $table) {
            $statement = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.tables '
                . 'WHERE table_schema = DATABASE() AND table_name = ?'
            );
            $statement->execute([$table]);
            if ((int) $statement->fetchColumn() !== 1) {
                $missing[] = $table;
            }
        }
        return $missing;
    }
}
