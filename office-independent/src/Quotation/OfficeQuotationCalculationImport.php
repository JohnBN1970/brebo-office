<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use InvalidArgumentException;

/**
 * Converts a verified, versioned Calc sales-price snapshot to quotation-facing
 * lines. The caller must authenticate, authorize and fetch the snapshot from
 * Office/Calc server-side; never accept browser-submitted cost or margin data.
 * Pure preparation only: no writes and no change to the source calculation.
 */
final class OfficeQuotationCalculationImport
{
    public static function prepare(array $snapshot): array
    {
        $id = filter_var($snapshot['calculation_id'] ?? null, FILTER_VALIDATE_INT);
        $version = filter_var($snapshot['version'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id < 1 || !is_int($version) || $version < 1) {
            throw new InvalidArgumentException('Verified calculation identity and version required');
        }
        $lines = $snapshot['sales_lines'] ?? null;
        if (!is_array($lines) || !$lines || count($lines) > 2000) {
            throw new InvalidArgumentException('Verified sales lines required');
        }
        $items = [];
        $total = 0;
        foreach ($lines as $line) {
            if (!is_array($line)) throw new InvalidArgumentException('Invalid sales line');
            $description = $line['description'] ?? null;
            $quantity = $line['quantity'] ?? null;
            $unit = $line['unit'] ?? null;
            $amount = $line['amount_ex_vat_cents'] ?? null;
            if (!is_string($description) || trim($description) === '' || mb_strlen($description, 'UTF-8') > 500
                || !is_numeric($quantity) || (float)$quantity <= 0 || (float)$quantity > 100000000
                || !is_string($unit) || strlen($unit) > 24
                || !is_int($amount) || $amount < 0 || $amount > 1000000000000) {
                throw new InvalidArgumentException('Invalid quotation-facing sales line');
            }
            if ($total > PHP_INT_MAX - $amount) throw new InvalidArgumentException('Sales total overflow');
            $total += $amount;
            $rubric = $line['rubric'] ?? 'Werkzaamheden';
            if (!is_string($rubric) || trim($rubric) === '' || mb_strlen($rubric, 'UTF-8') > 200) {
                throw new InvalidArgumentException('Invalid sales rubric');
            }
            $items[] = [
                'rubric' => trim($rubric),
                'description' => trim($description),
                'quantity' => (float)$quantity,
                'unit' => $unit,
                'amount_ex_vat_cents' => $amount,
            ];
        }
        return [
            'calculation_id' => $id,
            'calculation_version' => $version,
            'sales_lines' => $items,
            'total_ex_vat_cents' => $total,
            'source_hash' => hash('sha256', json_encode([
                'calculation_id' => $id, 'version' => $version, 'sales_lines' => $items,
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
