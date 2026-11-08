<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchCsrf.php';
use Brebo\Office\Calculation\OfficeCalcLaunchCsrf;

$token = str_repeat('a', 64);
if (!OfficeCalcLaunchCsrf::verify($token, $token)) throw new RuntimeException('Valid CSRF rejected');
foreach ([null, '', str_repeat('a', 63), str_repeat('b', 64), strtoupper($token), ['token' => $token]] as $invalid) {
    if (OfficeCalcLaunchCsrf::verify($invalid, $token)) throw new RuntimeException('Invalid CSRF accepted');
}
if (OfficeCalcLaunchCsrf::verify($token, null)) throw new RuntimeException('Missing cookie accepted');
if (!OfficeCalcLaunchCsrf::validOrigin('https://test.brebobv.nl', 'test.brebobv.nl')) throw new RuntimeException('Valid origin rejected');
foreach (['http://test.brebobv.nl', 'https://evil.test.brebobv.nl', 'https://test.brebobv.nl.evil.example', 'null', ''] as $origin) {
    if (OfficeCalcLaunchCsrf::validOrigin($origin, 'test.brebobv.nl')) throw new RuntimeException('Invalid origin accepted');
}
echo "CALC_LAUNCH_CSRF=PASS\n";
