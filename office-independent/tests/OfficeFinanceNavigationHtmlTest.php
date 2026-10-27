<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeNavigation.php';

use Brebo\Office\Intake\OfficeNavigation;

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: $message\n";
}

function renderNavigation(): string {
    $html = '<!doctype html><html><head><title>Test</title></head>'
        . '<body><main><h1>BREBO Office</h1></main></body></html>';

    return OfficeNavigation::decorate(
        $html,
        '/office-test/',
        [],
        200,
        'text/html; charset=utf-8'
    );
}

putenv('OFFICE_TEST_NAV_V2_ENABLED=enabled');

/*
 * Finance is deliberately disabled.
 * No production database or real session is needed.
 */
putenv('OFFICE_FINANCE_ENABLED=disabled');

$html = renderNavigation();

check(
    substr_count($html, 'aria-label="Hoofdnavigatie"') === 1,
    'Precies een hoofdnavigatie'
);

check(
    str_contains($html, 'office-global-nav-v2'),
    'Office 2.0 navigatie actief'
);

check(
    !str_contains($html, 'href="/office-test/finance"'),
    'Finance overzicht verborgen zonder toegang'
);

check(
    !str_contains($html, 'href="/office-test/finance/betaalcentrum"'),
    'Betaalcentrum verborgen zonder toegang'
);

check(
    !str_contains($html, 'href="/office-test/finance/nieuw"'),
    'Nieuwe inkoopfactuur verborgen zonder toegang'
);

check(
    str_contains($html, 'Bedrijfsvoering'),
    'Bedrijfsvoering blijft onderdeel van navigatie'
);

check(
    str_contains($html, 'Nog niet aangesloten'),
    'Niet beschikbare onderdelen correct weergegeven'
);

echo "OfficeFinanceNavigationHtmlTest OK\n";
