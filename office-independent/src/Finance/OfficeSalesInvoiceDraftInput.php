<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * Validation boundary for Office-native sales invoice drafts.
 * No persistence, numbering or external integrations.
 */
final class OfficeSalesInvoiceDraftInput
{
    /**
     * @param array<string,mixed> $data
     * @return array{debtor_id:int,project_id:?int,invoice_date:string,due_date:string,description:string}
     */
    public static function validate(array $data): array
    {
        $debtorId = filter_var($data['debtor_id'] ?? null, FILTER_VALIDATE_INT);
        if ($debtorId === false || $debtorId === null || $debtorId < 1) {
            throw new \InvalidArgumentException('Debiteur is verplicht');
        }

        $projectId = null;
        if (isset($data['project_id']) && $data['project_id'] !== '') {
            $projectId = filter_var($data['project_id'], FILTER_VALIDATE_INT);
            if ($projectId === false || $projectId < 1) {
                throw new \InvalidArgumentException('Ongeldig Office-project');
            }
        }

        $invoiceDate = self::date($data['invoice_date'] ?? null);
        $dueDate = self::date($data['due_date'] ?? null);
        if ($dueDate < $invoiceDate) {
            throw new \InvalidArgumentException('Vervaldatum ligt voor factuurdatum');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description, 'UTF-8') > 255) {
            throw new \InvalidArgumentException('Omschrijving te lang');
        }

        return [
            'debtor_id' => (int) $debtorId,
            'project_id' => $projectId === null ? null : (int) $projectId,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'description' => $description,
        ];
    }

    private static function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $value)) {
            throw new \InvalidArgumentException('Datum moet JJJJ-MM-DD zijn');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Ongeldige kalenderdatum');
        }

        return $value;
    }
}
