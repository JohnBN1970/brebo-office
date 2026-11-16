<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/**
 * HTML view for read-only sales-invoice draft results.
 * The caller must supply rows from OfficeSalesInvoiceDraftList only.
 */
final class OfficeSalesInvoiceDraftView
{
    /** @param list<array<string,mixed>> $rows */
    public static function render(array $rows): string
    {
        $escape = static fn (mixed $value): string =>
            htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<section aria-labelledby="sales-drafts-heading">'
            . '<h2 id="sales-drafts-heading">Verkoopfacturen · concepten</h2>'
            . '<p>Alleen lezen. Vrijgeven en verzenden zijn hier uitgeschakeld.</p>';

        if ($rows === []) {
            return $html . '<p>Geen toegankelijke verkoopfactuurconcepten gevonden.</p></section>';
        }

        $html .= '<div class="table"><table><thead><tr>'
            . '<th scope="col">Concept</th><th scope="col">Project</th>'
            . '<th scope="col">Datum</th><th scope="col">Status</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr><td>' . $escape($row['draft_number'] ?? $row['id'] ?? '')
                . '</td><td>' . $escape($row['office_project_id'] ?? '')
                . '</td><td>' . $escape($row['invoice_date'] ?? '')
                . '</td><td>' . $escape($row['status'] ?? '')
                . '</td></tr>';
        }

        return $html . '</tbody></table></div></section>';
    }
}
