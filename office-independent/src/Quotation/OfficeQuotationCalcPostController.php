<?php
declare(strict_types=1);

namespace Brebo\Office\Quotation;

use DomainException;
use PDO;

/**
 * Server-side POST boundary. The Office router supplies authenticated actor,
 * verified session CSRF and an authorized, versioned Calc snapshot resolver.
 * No browser-provided CRM IDs, prices or totals are accepted.
 */
final class OfficeQuotationCalcPostController
{
    public function __construct(private readonly PDO $db) {}

    public function handle(
        string $method,
        int $operatorId,
        array $post,
        string $expectedCsrf,
        callable $resolveAuthorizedCalculation
    ): string {
        if ($method !== 'POST') throw new DomainException('Quotation generation requires POST');
        if ($operatorId < 1 || !is_string($post['csrf'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $expectedCsrf)
            || !hash_equals($expectedCsrf, $post['csrf'])) {
            throw new DomainException('Invalid Office session or CSRF');
        }
        require_once __DIR__ . '/OfficeQuotationCalcGenerationService.php';
        $id = (new OfficeQuotationCalcGenerationService($this->db))
            ->generate($operatorId, $post, $resolveAuthorizedCalculation);
        return OfficeQuotationCalcGenerationService::editorUrl($id);
    }

    public static function button(int $calculationId, int $version, string $csrf, string $postUrl): string
    {
        if ($calculationId < 1 || $version < 1
            || !preg_match('/^[a-f0-9]{64}$/D', $csrf)
            || !str_starts_with($postUrl, '/office-test/')
            || str_contains($postUrl, '//')) {
            throw new DomainException('Invalid quotation launch form');
        }
        return '<form method="post" action="' . htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="action" value="generate_quotation">'
            . '<input type="hidden" name="calculation_id" value="' . $calculationId . '">'
            . '<input type="hidden" name="calculation_version" value="' . $version . '">'
            . '<input type="hidden" name="csrf" value="' . $csrf . '">'
            . '<button type="submit">Offerte genereren</button></form>';
    }
}
