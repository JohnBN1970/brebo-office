<?php
declare(strict_types=1);

// Standalone regression test: php office-independent/tests/OfficeCalcSessionGrantCompatibilityTest.php
// Uses a stub matching the installed Office-test API, without any database writes.
namespace Brebo\Office\Calculation {
    final class OfficeCalcSessionGrantRepository {
        public static mixed $result = null;
        public static int $calls = 0;
        public function __construct(\PDO $db) {}
        public function findActive(string $reference, int $actorId, int $projectId, int $calculationId, int $now): mixed {
            self::$calls++;
            return self::$result;
        }
    }
}
namespace {
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcSessionGrantCompatibility.php';
    $pdo = (new \ReflectionClass(\PDO::class))->newInstanceWithoutConstructor();
    $adapter = new \Brebo\Office\Calculation\OfficeCalcSessionGrantCompatibility($pdo);
    $validRef = str_repeat('a', 64);
    $active = ['office_session_hash'=>str_repeat('b',64)];
    $cases = [
        ['invalid reference', 'bad', 1, 2, 3, 100, $active, false, 0],
        ['invalid actor', $validRef, 0, 2, 3, 100, $active, false, 0],
        ['invalid project', $validRef, 1, 0, 3, 100, $active, false, 0],
        ['invalid calculation', $validRef, 1, 2, 0, 100, $active, false, 0],
        ['invalid clock', $validRef, 1, 2, 3, 0, $active, false, 0],
        ['null grant denied', $validRef, 1, 2, 3, 100, null, false, 1],
        ['valid one-key hash accepted', $validRef, 1, 2, 3, 100, $active, true, 1],
        ['boolean denied', $validRef, 1, 2, 3, 100, true, false, 1],
        ['empty array denied', $validRef, 1, 2, 3, 100, [], false, 1],
        ['missing key denied', $validRef, 1, 2, 3, 100, ['reference'=>$validRef], false, 1],
        ['additional key denied', $validRef, 1, 2, 3, 100, $active+['actor_id'=>1], false, 1],
        ['empty hash denied', $validRef, 1, 2, 3, 100, ['office_session_hash'=>''], false, 1],
        ['short hash denied', $validRef, 1, 2, 3, 100, ['office_session_hash'=>'abc'], false, 1],
        ['uppercase hash denied', $validRef, 1, 2, 3, 100, ['office_session_hash'=>str_repeat('B',64)], false, 1],
        ['nonhex hash denied', $validRef, 1, 2, 3, 100, ['office_session_hash'=>str_repeat('z',64)], false, 1],
        ['nonstring hash denied', $validRef, 1, 2, 3, 100, ['office_session_hash'=>123], false, 1],
    ];
    foreach ($cases as [$label, $ref, $actor, $project, $calculation, $now, $result, $expected, $calls]) {
        \Brebo\Office\Calculation\OfficeCalcSessionGrantRepository::$result = $result;
        \Brebo\Office\Calculation\OfficeCalcSessionGrantRepository::$calls = 0;
        $actual = $adapter->findActive($ref, $actor, $project, $calculation, $now);
        if ($actual !== $expected || \Brebo\Office\Calculation\OfficeCalcSessionGrantRepository::$calls !== $calls) {
            fwrite(STDERR, "FAIL: $label\n");
            exit(1);
        }
    }
    echo "CALC_COMPAT_TESTS=PASS (16 cases)\n";
}
