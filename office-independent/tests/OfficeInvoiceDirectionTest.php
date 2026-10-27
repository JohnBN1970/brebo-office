<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceDirection.php';

use Brebo\Office\Finance\OfficeInvoiceDirection as Direction;

function assertSameDirection(mixed $expected, mixed $actual, string $case): void {
  if ($expected !== $actual) {
    throw new RuntimeException($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

assertSameDirection('all', Direction::filter(null), 'missing filter');
assertSameDirection('all', Direction::filter(''), 'empty filter');
assertSameDirection('all', Direction::filter('unknown'), 'invalid filter');
assertSameDirection('incoming', Direction::filter(' INCOMING '), 'incoming filter');
assertSameDirection('outgoing', Direction::filter('outgoing'), 'outgoing filter');
assertSameDirection('Alle', Direction::label(Direction::ALL), 'all label');
assertSameDirection('Inkomend', Direction::label(Direction::INCOMING), 'incoming label');
assertSameDirection('Uitgaand', Direction::label(Direction::OUTGOING), 'outgoing label');
assertSameDirection(true, Direction::isValidInvoiceDirection('incoming'), 'incoming valid');
assertSameDirection(true, Direction::isValidInvoiceDirection('outgoing'), 'outgoing valid');
assertSameDirection(false, Direction::isValidInvoiceDirection('all'), 'all is not an invoice direction');
assertSameDirection(false, Direction::isValidInvoiceDirection('unknown'), 'unknown direction');

echo "OfficeInvoiceDirectionTest OK\n";
