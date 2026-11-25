<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use InvalidArgumentException;

/**
 * Pure quotation composition logic. No database, PDF, CRM or Drupal dependency.
 * All commercial values are supplied by the caller; no values are invented.
 */
final class OfficeQuotationComposer
{
    public const TEMPLATES = [
        'project' => ['intro', 'scope', 'method', 'planning', 'price', 'terms', 'references', 'attachments', 'conditions'],
        'mjop' => ['intro', 'scope', 'method', 'deliverables', 'price', 'attachments', 'conditions'],
        'regie' => ['intro', 'scope', 'method', 'rates', 'price', 'conditions'],
        'compact' => ['intro', 'scope', 'price', 'conditions'],
    ];

    public static function sections(string $type): array
    {
        if (!isset(self::TEMPLATES[$type])) {
            throw new InvalidArgumentException('Unknown quotation template');
        }
        return self::TEMPLATES[$type];
    }

    /**
     * @param array<int, array{percentage: numeric-string|int|float, trigger: string}> $terms
     * @return array<int, array{percentage: float, trigger: string, amount_cents: int}>
     */
    public static function instalments(int $totalCents, array $terms): array
    {
        if ($totalCents < 0 || $terms === []) {
            throw new InvalidArgumentException('A nonnegative total and at least one term are required');
        }
        $sumBasisPoints = 0;
        $result = [];
        $allocated = 0;
        foreach ($terms as $index => $term) {
            if (!isset($term['percentage'], $term['trigger']) || !is_numeric($term['percentage']) || trim((string) $term['trigger']) === '') {
                throw new InvalidArgumentException('Each term requires a percentage and trigger');
            }
            $percentage = (float) $term['percentage'];
            if (!is_finite($percentage) || $percentage <= 0 || $percentage > 100) {
                throw new InvalidArgumentException('Invalid instalment percentage');
            }
            $basisPoints = (int) round($percentage * 100);
            if (abs($percentage * 100 - $basisPoints) > 0.00001) {
                throw new InvalidArgumentException('Percentages support at most two decimals');
            }
            $sumBasisPoints += $basisPoints;
            $amount = intdiv($totalCents * $basisPoints + 5000, 10000);
            $allocated += $amount;
            $result[] = ['percentage' => $basisPoints / 100, 'trigger' => trim((string) $term['trigger']), 'amount_cents' => $amount];
        }
        if ($sumBasisPoints !== 10000) {
            throw new InvalidArgumentException('Instalment percentages must total 100');
        }
        // The final term absorbs rounding differences so totals always reconcile.
        $last = count($result) - 1;
        $result[$last]['amount_cents'] += $totalCents - $allocated;
        return $result;
    }

    /**
     * Returns a content-only draft. Caller must explicitly approve any generated
     * narrative, reference media, prices and contractual conditions before release.
     */
    public static function draft(string $type, array $input): array
    {
        $sections = self::sections($type);
        $provided = $input['sections'] ?? [];
        if (!is_array($provided)) {
            throw new InvalidArgumentException('Sections must be an array');
        }
        $document = [];
        foreach ($sections as $key) {
            $value = $provided[$key] ?? '';
            if (!is_string($value)) {
                throw new InvalidArgumentException('Section text must be a string');
            }
            $document[] = ['key' => $key, 'text' => $value, 'requires_review' => true];
        }
        return [
            'type' => $type,
            'status' => 'draft',
            'calculation_id' => $input['calculation_id'] ?? null,
            'calculation_version' => $input['calculation_version'] ?? null,
            'sections' => $document,
            'approved' => false,
        ];
    }
}
