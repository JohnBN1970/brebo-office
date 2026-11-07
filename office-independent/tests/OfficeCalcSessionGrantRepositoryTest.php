<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcSessionGrantRepository.php';

use Brebo\Office\Calculation\OfficeCalcSessionGrantRepository;

$reflection = new ReflectionClass(OfficeCalcSessionGrantRepository::class);
foreach (['issue', 'matchesActiveSession', 'revoke'] as $method) {
    if (!$reflection->hasMethod($method)) {
        throw new RuntimeException('Missing Calc grant operation: ' . $method);
    }
}
$db = new PDO('sqlite::memory:');
$grants = new OfficeCalcSessionGrantRepository($db);
foreach ([
    static fn() => $grants->issue('invalid', 1, 2, 3, time()),
    static fn() => $grants->issue(str_repeat('a', 64), 0, 2, 3, time()),
    static fn() => $grants->issue(str_repeat('a', 64), 1, 2, 3, time(), 28801),
    static fn() => $grants->revoke('invalid'),
] as $invalid) {
    try {
        $invalid();
        throw new RuntimeException('Invalid grant input accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
if ($grants->matchesActiveSession('invalid', 1, 2, 3, time())) {
    throw new RuntimeException('Invalid grant accepted');
}
echo "CALC_GRANT_INPUT_CONTRACT=PASS\n";
