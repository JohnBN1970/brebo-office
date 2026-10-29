<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;
use RuntimeException;

final class OfficeFinanceDraftImporter
{
    public function __construct(private PDO $db)
    {
    }

    public function import(array $record, string $storageRoot): int
    {
        $id = (string)($record['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new RuntimeException('Ongeldige concept-ID');
        }

        if (($record['status'] ?? '') !== 'manual_review_complete') {
            throw new RuntimeException('Concept is nog niet volledig gecontroleerd');
        }

        $review = $record['review'] ?? null;
        $lines = $record['review_lines'] ?? null;

        if (!is_array($review) || !is_array($lines) || $lines === []) {
            throw new RuntimeException('Conceptgegevens ontbreken');
        }

        $supplier = trim((string)($review['supplier_name'] ?? ''));
        $number = trim((string)($review['invoice_number'] ?? ''));
        $date = trim((string)($review['invoice_date'] ?? ''));
        $operator = trim((string)($record['operator_id'] ?? ''));

        if ($supplier === '' || $number === '' || $operator === '') {
            throw new RuntimeException('Verplichte gegevens ontbreken');
        }

        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            throw new RuntimeException('Ongeldige factuurdatum');
        }

        $filename = (string)($record['file'] ?? '');
        $sha = (string)($record['sha256'] ?? '');
        $mime = (string)($record['mime'] ?? '');

        if (!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png)$/D', $filename)
            || !preg_match('/^[a-f0-9]{64}$/D', $sha)
            || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Ongeldige documentverwijzing');
        }

        $root = realpath($storageRoot);
        if ($root === false || is_link($storageRoot)) {
            throw new RuntimeException('Ongeldige opslaglocatie');
        }

        $document = $root . '/' . $filename;
        if (!is_file($document) || is_link($document)
            || !hash_equals($sha, hash_file('sha256', $document) ?: '')) {
            throw new RuntimeException('Documentcontrole mislukt');
        }

        $money = static function (mixed $value): string {
            $value = str_replace(',', '.', trim((string)$value));
            if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
                throw new RuntimeException('Ongeldig geldbedrag');
            }
            return number_format((float)$value, 2, '.', '');
        };

        $total = $money($review['amount_inc_vat'] ?? '');
        $vat = $money($review['vat_amount'] ?? '');
        $subtotal = number_format((float)$total - (float)$vat, 2, '.', '');

        if ((float)$subtotal < 0) {
            throw new RuntimeException('Ongeldige factuurtotalisatie');
        }

        $projectId = $record['office_project_id'] ?? null;
        if ($projectId !== null && (!is_int($projectId) || $projectId < 1)) {
            throw new RuntimeException('Ongeldige projectkoppeling');
        }

        $preparedLines = [];
        $netCents = 0;
        $vatCents = 0;

        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw new RuntimeException('Ongeldige factuurregel');
            }

            $description = trim((string)($line['description'] ?? ''));
            $unit = trim((string)($line['unit'] ?? ''));
            $quantity = str_replace(',', '.', trim((string)($line['quantity'] ?? '')));
            $price = $money($line['unit_price'] ?? '');
            $net = $money($line['line_total'] ?? '');
            $rate = str_replace(',', '.', trim((string)($line['vat_rate'] ?? '')));

            if ($description === '' || strlen($unit) > 20
                || !preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $quantity)
                || !preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D', $rate)
                || (float)$rate > 100) {
                throw new RuntimeException('Onvolledige factuurregel');
            }

            $lineNet = (int)round((float)$net * 100);
            $lineVat = (int)round($lineNet * (float)$rate / 100);

            if (abs((float)$quantity * (float)$price * 100 - $lineNet) > 1) {
                throw new RuntimeException('Regeltotaal wijkt af');
            }

            $netCents += $lineNet;
            $vatCents += $lineVat;

            $preparedLines[] = [
                $index + 1,
                $description,
                $quantity,
                $unit,
                $price,
                $net,
                $rate,
                number_format($lineVat / 100, 2, '.', ''),
                $projectId
            ];
        }

        if (abs($netCents - (int)round((float)$subtotal * 100)) > 1
            || abs($vatCents - (int)round((float)$vat * 100)) > 1) {
            throw new RuntimeException('Factuurtotalen sluiten niet aan');
        }

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $find = $this->db->prepare(
                'SELECT id FROM office_finance_invoices WHERE source_draft_id=?'
            );
            $find->execute([$id]);
            $existing = $find->fetchColumn();

            if ($existing !== false) {
                if ($ownsTransaction) {
                    $this->db->commit();
                }
                return (int)$existing;
            }

            $insert = $this->db->prepare(
                'INSERT INTO office_finance_invoices
                (supplier_name,invoice_number,invoice_date,due_date,
                 currency,subtotal,vat_total,total,status,created_by,source_draft_id)
                VALUES (?,?,?,NULL,?,?,?,?,?,?,?)'
            );

            $insert->execute([
                $supplier, $number, $date,
                'EUR', $subtotal, $vat, $total,
                'draft', $operator, $id
            ]);

            $invoiceId = (int)$this->db->lastInsertId();

            $lineInsert = $this->db->prepare(
                'INSERT INTO office_finance_invoice_lines
                (invoice_id,line_number,description,quantity,unit,
                 unit_price,net_amount,vat_rate,vat_amount,office_project_id)
                VALUES (?,?,?,?,?,?,?,?,?,?)'
            );

            foreach ($preparedLines as $line) {
                $lineInsert->execute(array_merge([$invoiceId], $line));
            }

            $documentInsert = $this->db->prepare(
                'INSERT INTO office_finance_invoice_documents
                (invoice_id,storage_reference,sha256,mime_type)
                VALUES (?,?,?,?)'
            );

            $documentInsert->execute([
                $invoiceId, $filename, $sha, $mime
            ]);

            $eventInsert = $this->db->prepare(
                'INSERT INTO office_finance_invoice_events
                (invoice_id,event_type,operator_id,event_data)
                VALUES (?,?,?,?)'
            );

            $eventInsert->execute([
                $invoiceId,
                'draft_imported',
                $operator,
                json_encode([
                    'source_draft_id' => $id,
                    'status' => 'draft',
                    'original_status' => $record['status'] ?? null
                ], JSON_THROW_ON_ERROR)
            ]);

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $invoiceId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
