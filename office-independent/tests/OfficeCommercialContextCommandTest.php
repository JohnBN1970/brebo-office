<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextWriter.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextCommand.php';
use Brebo\Office\Demo\OfficeCommercialContextCommand as C;
use Brebo\Office\Intake\WebsiteCrmOperatorContext as O;
use Brebo\Office\Demo\OfficeCommercialContextWriter as W;
$secret = str_repeat('x', 40);
$operator = new O('1', 'session-1', true);
$token = C::csrfToken($operator->sessionId, $secret);
if (strlen($token) !== 64 || $token === C::csrfToken('session-2', $secret)) throw new RuntimeException('CSRF binding failed');
$writer = new W(new PDO('sqlite::memory:'));
foreach ([[new O('1', 'session-1', false), $token], [$operator, str_repeat('0', 64)]] as $case) {
    try {
        C::handle($case[0], $case[1], $secret, ['source_type'=>'direct_order','source_id'=>'1','commercial_position'=>'particulier','work_categories'=>['glas']], $writer);
        throw new RuntimeException('Unauthorized command accepted');
    } catch (DomainException $expected) {}
}
echo "Commercial context command security OK\n";
