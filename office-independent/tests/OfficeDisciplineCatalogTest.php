<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeDisciplineCatalog.php';

use Brebo\Office\Intake\OfficeDisciplineCatalog as Catalog;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

check(count(Catalog::active()) === 6, 'Six active disciplines');
check(!in_array(Catalog::MJOP, Catalog::active(), true), 'MJOP must be inactive');
check(Catalog::normalize(' Montage en levering ') === Catalog::DELIVERY_INSTALLATION, 'Alias normalization');
check(Catalog::validate(['bouw', 'bouw', 'advies']) === ['bouw', 'advies'], 'Deduplicate disciplines');
check(Catalog::validate(['expertise', 'levering']) === ['expertise', 'levering'], 'Multiple disciplines');
foreach (['marketing', 'finance', 'mjop', 'onbekend'] as $invalid) {
    try {
        Catalog::validate([$invalid]);
        throw new RuntimeException('Invalid discipline accepted: ' . $invalid);
    } catch (InvalidArgumentException $exception) {
        check($exception->getMessage() === 'invalid_or_inactive_discipline', 'Unexpected validation error');
    }
}
check(Catalog::validate(['mjop'], true) === ['mjop'], 'Future discipline is reserved');
echo "OfficeDisciplineCatalogTest OK\n";
