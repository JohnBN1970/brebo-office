<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

final class OfficeFinanceDraftRegister
{
    public function __construct(
        private readonly PDO $db
    ) {}


    public function findVisible(string $operatorId, int $invoiceId): array
    {
        if ($invoiceId < 1) {
            throw new \OutOfBoundsException('Factuur niet beschikbaar');
        }

        $access = new OfficeFinanceAccess($this->db, $operatorId);
        if (!$access->allows('read')) {
            throw new \DomainException('Geen Finance-toegang');
        }

        $allowed = array_fill_keys(
            array_map(
                static fn(array $project): int => (int)$project['office_project_id'],
                $access->draftProjects()
            ),
            true
        );

        $statement = $this->db->prepare(
            "SELECT i.*, l.office_project_id
             FROM office_finance_invoices i
             LEFT JOIN office_finance_invoice_lines l ON l.invoice_id = i.id
             WHERE i.id = ? AND i.status = 'draft'"
        );
        $statement->execute([$invoiceId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new \OutOfBoundsException('Factuur niet beschikbaar');
        }

        $projects = [];
        foreach ($rows as $row) {
            $projectId = $row['office_project_id'];
            if ($projectId === null || !isset($allowed[(int)$projectId])) {
                throw new \OutOfBoundsException('Factuur niet beschikbaar');
            }
            $projects[(int)$projectId] = true;
        }

        $invoice = $rows[0];
        unset($invoice['office_project_id']);
        $invoice['project_ids'] = array_keys($projects);

        $queries = [
            'lines' => 'SELECT * FROM office_finance_invoice_lines WHERE invoice_id = ? ORDER BY line_number',
            'documents' => 'SELECT * FROM office_finance_invoice_documents WHERE invoice_id = ?',
            'events' => 'SELECT * FROM office_finance_invoice_events WHERE invoice_id = ?',
        ];

        $result = ['invoice' => $invoice];

        foreach ($queries as $key => $sql) {
            $statement = $this->db->prepare($sql);
            $statement->execute([$invoiceId]);
            $result[$key] = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        return $result;
    }

    public function visible(
        string $operatorId,
        ?int $projectId = null
    ): array {
        $access = new OfficeFinanceAccess($this->db, $operatorId);

        if (!$access->allows('read')) {
            throw new \DomainException('Geen Finance-toegang');
        }

        $allowed = array_fill_keys(
            array_map(
                static fn(array $p): int => (int)$p['office_project_id'],
                $access->draftProjects()
            ),
            true
        );

        if ($projectId !== null && !isset($allowed[$projectId])) {
            throw new \OutOfBoundsException('Project niet toegankelijk');
        }

        $sql = "
            SELECT
                i.id,
                i.supplier_name,
                i.invoice_number,
                i.invoice_date,
                i.due_date,
                i.total,
                i.currency,
                i.status,
                COUNT(l.invoice_id) AS line_count,
                COUNT(DISTINCT l.office_project_id) AS project_count,
                SUM(
                    CASE
                        WHEN l.office_project_id IS NULL THEN 1
                        ELSE 0
                    END
                ) AS unassigned_lines,
                GROUP_CONCAT(
                    DISTINCT l.office_project_id
                    ORDER BY l.office_project_id
                    SEPARATOR ','
                ) AS project_ids
            FROM office_finance_invoices i
            LEFT JOIN office_finance_invoice_lines l
                ON l.invoice_id = i.id
            WHERE i.status = 'draft'
            GROUP BY
                i.id,
                i.supplier_name,
                i.invoice_number,
                i.invoice_date,
                i.due_date,
                i.total,
                i.currency,
                i.status
            ORDER BY i.id DESC
            LIMIT 500
        ";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $visible = [];

        foreach ($rows as $row) {
            if ((int)$row['line_count'] < 1
                || (int)$row['unassigned_lines'] > 0
                || (int)$row['project_count'] < 1) {
                continue;
            }

            $projects = array_map(
                'intval',
                explode(',', (string)$row['project_ids'])
            );

            foreach ($projects as $id) {
                if ($id < 1 || !isset($allowed[$id])) {
                    continue 2;
                }
            }

            if ($projectId !== null
                && !in_array($projectId, $projects, true)) {
                continue;
            }

            $row['project_ids'] = $projects;
            $visible[] = $row;
        }

        return $visible;
    }
}
