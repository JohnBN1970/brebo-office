<?php
declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/src/Finance/bootstrap.php';

$classes = [
    \Brebo\Office\Finance\OfficeFinanceApplication::class,
    \Brebo\Office\Finance\OfficeFinanceView::class,
    \Brebo\Finance\Service\PayablesWorkQueueBuilder::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('Finance dependency missing: ' . $class);
    }
}

$application = file_get_contents(
    $root . '/src/Finance/OfficeFinanceApplication.php'
);
$view = file_get_contents(
    $root . '/src/Finance/OfficeFinanceView.php'
);

if ($application === false || $view === false) {
    throw new RuntimeException('Finance source unavailable');
}

foreach ([
    '$access->draftProjects()',
    '$access->visibleProjects()',
    'finance_unlinked_project',
    'if ($invoiceId !== null) throw new',
] as $required) {
    if (!str_contains($application, $required)) {
        throw new RuntimeException(
            'Finance project guard missing: ' . $required
        );
    }
}

if (!str_contains($view, 'Nog geen financiële bron gekoppeld.')) {
    throw new RuntimeException(
        'Finance unlinked-project warning missing'
    );
}

$mount = file_get_contents(
    $root . '/src/Intake/OfficeMount.php'
);

if ($mount === false) {
    throw new RuntimeException('OfficeMount unavailable');
}

foreach ([
    'finance',
    'finance/betaalcentrum',
    'finance/factuur',
    'finance/nieuw',
    'finance/concept',
] as $route) {
    if (!str_contains($mount, "'" . $route . "' =>")) {
        throw new RuntimeException(
            'Finance OfficeMount route missing: ' . $route
        );
    }
}

echo "Finance integration source regression checks OK\n";
