<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmSignedLaunch.php';

use Brebo\Office\Calculation\OfficeCalcCrmSignedLaunch;

$secret=str_repeat('a',40);
$ref=str_repeat('b',64);
$now=time();
$token=OfficeCalcCrmSignedLaunch::issue(42,7,$ref,$secret,$now);
$parts=explode('.',$token);
if (count($parts)!==2 || !preg_match('/^[a-f0-9]{64}$/D',$parts[1])) throw new RuntimeException('Malformed v2 HMAC token');
$expected=hash_hmac('sha256',$parts[0],$secret);
if (!hash_equals($expected,$parts[1])) throw new RuntimeException('Invalid v2 signature');
$json=base64_decode(strtr($parts[0],'-_','+/'),true);
if (!is_string($json)) throw new RuntimeException('Invalid v2 payload encoding');
$payload=json_decode($json,true,512,JSON_THROW_ON_ERROR);
if (($payload['v']??null)!==2 || (!array_key_exists('project_id',$payload) || $payload['project_id']!==null)
    || ($payload['actor_id']??null)!=='7' || ($payload['calculation_id']??null)!==42
    || ($payload['grant_ref']??null)!==$ref || ($payload['exp']??null)!==$now+90
    || !preg_match('/^[a-f0-9]{32}$/D',$payload['nonce']??'')) {
    throw new RuntimeException('Invalid independent Calc contract');
}
foreach ([
    [42,0,$ref,$secret,$now],
    [0,7,$ref,$secret,$now],
    [42,7,'invalid',$secret,$now],
    [42,7,$ref,'short',$now],
] as $bad) {
    try {
        OfficeCalcCrmSignedLaunch::issue(...$bad);
        throw new RuntimeException('Invalid identity was signed');
    } catch (InvalidArgumentException) {}
}
echo "OFFICE_CALC_CRM_V2_SIGNED_LAUNCH=PASS\n";
