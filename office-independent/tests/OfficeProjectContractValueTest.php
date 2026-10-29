<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeProjectContractValue.php';

use Brebo\Office\Project\OfficeProjectContractValue;

$result = OfficeProjectContractValue::calculate(
    '100000.00',
    [
        ['change_type' => 'more', 'amount' => '2500.00'],
        ['change_type' => 'less', 'amount' => '750.00'],
    ]
);

$expected = [
    'original' => '100000.00',
    'approved_more' => '2500.00',
    'approved_less' => '750.00',
    'current' => '101750.00',
];

if ($result !== $expected) {
    throw new RuntimeException('Contractwaardeberekening onjuist');
}

if (
    OfficeProjectContractValue::calculate('100000', [])['current']
    !== '100000.00'
) {
    throw new RuntimeException('Oorspronkelijke waarde onjuist');
}

$blocked = false;

try {
    OfficeProjectContractValue::calculate(
        '100.00',
        [['change_type' => 'less', 'amount' => '101.00']]
    );
} catch (DomainException $e) {
    $blocked = true;
}

if (!$blocked) {
    throw new RuntimeException('Negatieve contractwaarde toegestaan');
}

echo "PASS: actuele contractwaarde\n";
