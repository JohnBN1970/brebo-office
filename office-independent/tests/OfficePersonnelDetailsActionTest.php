<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficePersonnelDetailsRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficePersonnelDetailsAction.php';

use Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf;
use Brebo\Office\Inzet\OfficePersonnelDetailsAction;
use Brebo\Office\Inzet\OfficePersonnelDetailsRepository;

$secret = str_repeat('s', 48);
$session = str_repeat('a', 64);
$action = new OfficePersonnelDetailsAction(new OfficePersonnelDetailsRepository(new PDO('sqlite::memory:')));
$failed = false;
try {
  $action->update(['project_id'=>1,'person_id'=>1,'csrf_token'=>'invalid'], 'operator', $session, $secret);
} catch (DomainException $e) { $failed = true; }
if (!$failed) throw new RuntimeException('Invalid CSRF must fail before database access');

$failed = false;
try {
  $action->update(['project_id'=>'invalid','person_id'=>1,'csrf_token'=>OfficeInzetPersonnelProfileCsrf::token($session, $secret)], 'operator', $session, $secret);
} catch (InvalidArgumentException $e) { $failed = true; }
if (!$failed) throw new RuntimeException('Invalid project must fail before database access');

$failed = false;
try {
  $action->update(['project_id'=>1,'person_id'=>0,'csrf_token'=>OfficeInzetPersonnelProfileCsrf::token($session, $secret)], 'operator', $session, $secret);
} catch (InvalidArgumentException $e) { $failed = true; }
if (!$failed) throw new RuntimeException('Invalid person must fail before database access');

echo "Personnel details action validation OK\n";
