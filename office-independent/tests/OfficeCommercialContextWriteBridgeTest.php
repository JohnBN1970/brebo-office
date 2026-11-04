<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextWriter.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextIntegrity.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextCommand.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextWriteBridge.php';
use Brebo\Office\Demo\OfficeCommercialContextWriteBridge as Bridge;
use Brebo\Office\Demo\OfficeCommercialContextWriter as Writer;
use Brebo\Office\Demo\OfficeCommercialContextIntegrity as Integrity;
use Brebo\Office\Demo\OfficeCommercialContextCommand as Command;
use Brebo\Office\Intake\WebsiteCrmOperatorContext as Operator;
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE office_commercial_profiles (id INTEGER PRIMARY KEY, position_code TEXT, status TEXT)');
$db->exec("INSERT INTO office_commercial_profiles VALUES (1, 'particulier', 'active')");
$bridge = new Bridge(new Writer($db), new Integrity($db));
$operator = new Operator('1', 'session-1', true);
$token = Command::csrfToken($operator->sessionId, str_repeat('s', 40));
$input = ['source_type'=>'direct_order','source_id'=>'order-1','commercial_position'=>'particulier','work_categories'=>['glas'],'profile_id'=>1];
try {
    $bridge->save($operator, $token, str_repeat('s', 40), $input, static fn() => false);
    throw new RuntimeException('Unauthorized source accepted');
} catch (DomainException $expected) {}
$input['profile_id'] = 2;
try {
    $bridge->save($operator, $token, str_repeat('s', 40), $input, static fn() => true);
    throw new RuntimeException('Unknown profile accepted');
} catch (DomainException $expected) {}
echo "Commercial write bridge guard contract OK\n";
