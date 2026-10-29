<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

final class OfficeFinanceDraftLineEditor
{
    public function __construct(private readonly PDO $db) {}

    private static function decimal(mixed $value, int $scale, string $field): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException("$field ongeldig");
        }

        $value = trim((string)$value);

        if (!preg_match('/^\d{1,10}(?:\.\d{1,3})?$/D', $value)) {
            throw new \InvalidArgumentException("$field ongeldig");
        }

        if (bccomp($value, '0', 3) < 0) {
            throw new \InvalidArgumentException("$field negatief");
        }

        if ($scale === 2 && preg_match('/\.\d{3}$/D', $value)) {
            throw new \InvalidArgumentException("$field heeft te veel decimalen");
        }

        $normalized = bcadd($value, '0', $scale);
        if (bccomp($normalized, '999999999.999', 3) > 0) {
            throw new \InvalidArgumentException('Invoer overschrijdt maximum');
        }
        return $normalized;
    }

    private static function moneyRound(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }


    public function saveLines(string $operatorId, int $invoiceId, array $input): void
    {
        $access = new OfficeFinanceAccess($this->db, $operatorId);
        if (!$access->allows('read') || !$access->allows('draft_edit')) {
            throw new \DomainException('Geen bewerkrecht');
        }

        $submitted = $input['lines'] ?? [];
        $added = $input['new_lines'] ?? [];
        $deleted = $input['delete_lines'] ?? [];

        if (!is_array($submitted) || !is_array($added) || !is_array($deleted) ||
            count($submitted) + count($added) > 500) {
            throw new \InvalidArgumentException('Factuurregels ongeldig');
        }

        $allowedProjects = array_fill_keys(
            array_map(
                static fn(array $p): int => (int)$p['office_project_id'],
                $access->draftProjects()
            ),
            true
        );

        $normalize = function (array $row) use ($allowedProjects): array {
            $description = trim((string)($row['description'] ?? ''));
            $unit = trim((string)($row['unit'] ?? ''));

            if ($description === '' || mb_strlen($description) > 2000 ||
                mb_strlen($unit) > 20) {
                throw new \InvalidArgumentException('Omschrijving of eenheid ongeldig');
            }

            $quantity = self::decimal($row['quantity'] ?? null, 3, 'Aantal');
            $price = self::decimal($row['unit_price'] ?? null, 2, 'Eenheidsprijs');
            $vat = self::decimal($row['vat_rate'] ?? null, 3, 'Btw');

            if (bccomp($vat, '100', 3) > 0) {
                throw new \InvalidArgumentException('Btw-percentage ongeldig');
            }

            $projectId = filter_var(
                $row['office_project_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($projectId === false || $projectId === null ||
                !isset($allowedProjects[$projectId])) {
                throw new \DomainException('Project niet toegankelijk');
            }

            $net = self::moneyRound(bcmul($quantity, $price, 5));
            $vatAmount = self::moneyRound(
                bcdiv(bcmul($net, $vat, 5), '100', 5)
            );

            foreach ([$net, $vatAmount] as $amount) {
                if (bccomp($amount, '9999999999999.99', 2) > 0) {
                    throw new \InvalidArgumentException('Regelbedrag overschrijdt maximum');
                }
            }

            return [
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $price,
                'net_amount' => $net,
                'vat_rate' => $vat,
                'vat_amount' => $vatAmount,
                'office_project_id' => $projectId,
            ];
        };

        $this->db->beginTransaction();

        try {
            $q = $this->db->prepare(
                "SELECT id FROM office_finance_invoices
                 WHERE id=? AND status='draft' FOR UPDATE"
            );
            $q->execute([$invoiceId]);
            if (!$q->fetchColumn()) {
                throw new \OutOfBoundsException('Conceptfactuur niet beschikbaar');
            }

            $q = $this->db->prepare(
                'SELECT * FROM office_finance_invoice_lines
                 WHERE invoice_id=? ORDER BY line_number,id FOR UPDATE'
            );
            $q->execute([$invoiceId]);
            $existing = $q->fetchAll(PDO::FETCH_ASSOC);

            $known = [];
            $updates = [];
            $removals = [];
            $insertions = [];
            $subtotal = '0.00';
            $vatTotal = '0.00';
            $maxLine = 0;

            foreach ($existing as $old) {
                $id = (int)$old['id'];
                $key = (string)$id;
                $known[$key] = true;
                $maxLine = max($maxLine, (int)$old['line_number']);

                $originalProject = $old['office_project_id'];
                if ($originalProject === null ||
                    !isset($allowedProjects[(int)$originalProject])) {
                    throw new \DomainException('Oorspronkelijk project niet toegankelijk');
                }

                if (array_key_exists($key, $deleted)) {
                    $removals[] = $old;
                    continue;
                }

                if (!isset($submitted[$key]) || !is_array($submitted[$key])) {
                    throw new \InvalidArgumentException('Bestaande factuurregel ontbreekt');
                }

                $new = $normalize($submitted[$key]);
                $before = array_intersect_key($old, $new);

                if (array_map('strval', $before) !== array_map('strval', $new)) {
                    $updates[] = ['id' => $id, 'before' => $before, 'after' => $new];
                }

                $subtotal = bcadd($subtotal, $new['net_amount'], 2);
                $vatTotal = bcadd($vatTotal, $new['vat_amount'], 2);
            }

            foreach ($submitted as $key => $_) {
                if (!isset($known[(string)$key])) {
                    throw new \InvalidArgumentException('Onbekende factuurregel');
                }
            }

            foreach ($deleted as $key => $_) {
                if (!isset($known[(string)$key])) {
                    throw new \InvalidArgumentException('Onbekende te verwijderen regel');
                }
            }

            foreach ($added as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('Nieuwe factuurregel ongeldig');
                }
                $new = $normalize($row);
                $maxLine++;
                $insertions[] = ['line_number' => $maxLine, 'values' => $new];
                $subtotal = bcadd($subtotal, $new['net_amount'], 2);
                $vatTotal = bcadd($vatTotal, $new['vat_amount'], 2);
            }

            $total = bcadd($subtotal, $vatTotal, 2);
            foreach ([$subtotal, $vatTotal, $total] as $amount) {
                if (bccomp($amount, '9999999999999.99', 2) > 0) {
                    throw new \InvalidArgumentException('Factuurtotaal overschrijdt maximum');
                }
            }

            if ($updates !== [] || $removals !== [] || $insertions !== []) {
                $q = $this->db->prepare(
                    'UPDATE office_finance_invoice_lines SET
                     description=?,unit=?,quantity=?,unit_price=?,
                     net_amount=?,vat_rate=?,vat_amount=?,office_project_id=?
                     WHERE id=? AND invoice_id=?'
                );
                foreach ($updates as $change) {
                    $r = $change['after'];
                    $q->execute([
                        $r['description'], $r['unit'], $r['quantity'],
                        $r['unit_price'], $r['net_amount'], $r['vat_rate'],
                        $r['vat_amount'], $r['office_project_id'],
                        $change['id'], $invoiceId
                    ]);
                }

                $q = $this->db->prepare(
                    'DELETE FROM office_finance_invoice_lines WHERE id=? AND invoice_id=?'
                );
                foreach ($removals as $old) {
                    $q->execute([(int)$old['id'], $invoiceId]);
                }

                $q = $this->db->prepare(
                    'INSERT INTO office_finance_invoice_lines
                     (invoice_id,line_number,description,quantity,unit,unit_price,
                      net_amount,vat_rate,vat_amount,office_project_id)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                foreach ($insertions as $item) {
                    $r = $item['values'];
                    $q->execute([
                        $invoiceId, $item['line_number'], $r['description'],
                        $r['quantity'], $r['unit'], $r['unit_price'],
                        $r['net_amount'], $r['vat_rate'], $r['vat_amount'],
                        $r['office_project_id']
                    ]);
                }

                $q = $this->db->prepare(
                    "UPDATE office_finance_invoices
                     SET subtotal=?,vat_total=?,total=?
                     WHERE id=? AND status='draft'"
                );
                $q->execute([$subtotal, $vatTotal, $total, $invoiceId]);

                $q = $this->db->prepare(
                    'INSERT INTO office_finance_invoice_events
                     (invoice_id,event_type,operator_id,event_data)
                     VALUES (?,?,?,?)'
                );
                $q->execute([
                    $invoiceId, 'draft_lines_updated', $operatorId,
                    json_encode([
                        'updated' => $updates,
                        'deleted' => $removals,
                        'added' => $insertions,
                        'subtotal' => $subtotal,
                        'vat_total' => $vatTotal,
                        'total' => $total,
                    ], JSON_THROW_ON_ERROR)
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
