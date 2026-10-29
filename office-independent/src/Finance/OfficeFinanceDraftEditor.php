<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use DateTimeImmutable;
use PDO;

final class OfficeFinanceDraftEditor
{
    public function __construct(private readonly PDO $db) {}

    public function saveHeader(
        string $operatorId,
        int $invoiceId,
        array $input
    ): void {
        $access = new OfficeFinanceAccess($this->db, $operatorId);

        if (!$access->allows('read') || !$access->allows('draft_edit')) {
            throw new \DomainException('Geen toestemming om conceptfacturen te wijzigen');
        }

        $supplier = trim((string)($input['supplier_name'] ?? ''));
        $number = trim((string)($input['invoice_number'] ?? ''));
        $date = trim((string)($input['invoice_date'] ?? ''));
        $due = trim((string)($input['due_date'] ?? ''));

        if ($supplier === '' || mb_strlen($supplier) > 255 ||
            $number === '' || mb_strlen($number) > 128) {
            throw new \InvalidArgumentException('Leverancier of factuurnummer ongeldig');
        }

        foreach ([$date, $due] as $value) {
            if ($value === '') {
                continue;
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$parsed || $parsed->format('Y-m-d') !== $value) {
                throw new \InvalidArgumentException('Ongeldige datum');
            }
        }

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "SELECT * FROM office_finance_invoices
                 WHERE id = ? AND status = 'draft' FOR UPDATE"
            );
            $statement->execute([$invoiceId]);
            $old = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new \OutOfBoundsException('Conceptfactuur niet beschikbaar');
            }

            $statement = $this->db->prepare(
                'SELECT office_project_id
                 FROM office_finance_invoice_lines
                 WHERE invoice_id = ? FOR UPDATE'
            );
            $statement->execute([$invoiceId]);
            $projects = $statement->fetchAll(PDO::FETCH_COLUMN);

            $allowed = array_fill_keys(
                array_map(
                    static fn(array $p): int => (int)$p['office_project_id'],
                    $access->draftProjects()
                ),
                true
            );

            if ($projects === []) {
                throw new \OutOfBoundsException('Factuur zonder projectregels');
            }

            foreach ($projects as $projectId) {
                if ($projectId === null || !isset($allowed[(int)$projectId])) {
                    throw new \OutOfBoundsException('Project niet toegankelijk');
                }
            }

            $new = [
                'supplier_name' => $supplier,
                'invoice_number' => $number,
                'invoice_date' => $date === '' ? null : $date,
                'due_date' => $due === '' ? null : $due,
            ];

            $before = array_intersect_key($old, $new);

            if ($before !== $new) {
                $statement = $this->db->prepare(
                    'UPDATE office_finance_invoices
                     SET supplier_name=?, invoice_number=?,
                         invoice_date=?, due_date=?
                     WHERE id=? AND status=?'
                );
                $statement->execute([
                    $new['supplier_name'],
                    $new['invoice_number'],
                    $new['invoice_date'],
                    $new['due_date'],
                    $invoiceId,
                    'draft'
                ]);

                $statement = $this->db->prepare(
                    'INSERT INTO office_finance_invoice_events
                     (invoice_id,event_type,operator_id,event_data)
                     VALUES (?,?,?,?)'
                );
                $statement->execute([
                    $invoiceId,
                    'draft_header_updated',
                    $operatorId,
                    json_encode(
                        ['before' => $before, 'after' => $new],
                        JSON_THROW_ON_ERROR
                    )
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
