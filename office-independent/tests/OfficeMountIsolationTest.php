<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';

use Brebo\Office\Intake\OfficeMount;

function assertMount(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

putenv('OFFICE_BASE_PATH');
assertMount(OfficeMount::basePath() === '/office', 'Production mount default changed.');
assertMount(OfficeMount::cookieName('session') === 'office_session', 'Production session cookie changed.');
assertMount(OfficeMount::cookieName('login_csrf') === 'office_login_csrf', 'Production CSRF cookie changed.');
assertMount(OfficeMount::cookiePath('session') === '/', 'Production session path changed.');
assertMount(OfficeMount::cookiePath('login_csrf') === '/office', 'Production CSRF path changed.');
assertMount(OfficeMount::route('home') === '/office/', 'Production home route changed.');

putenv('OFFICE_BASE_PATH=/office-test');
assertMount(OfficeMount::basePath() === '/office-test', 'Test mount unavailable.');
assertMount(OfficeMount::cookieName('session') === 'office_test_session', 'Test session cookie not isolated.');
assertMount(OfficeMount::cookieName('login_csrf') === 'office_test_login_csrf', 'Test CSRF cookie not isolated.');
assertMount(OfficeMount::cookiePath('session') === '/office-test', 'Test session path not isolated.');
assertMount(OfficeMount::cookiePath('login_csrf') === '/office-test', 'Test CSRF path not isolated.');
assertMount(OfficeMount::route('inloggen') === '/office-test/inloggen', 'Test login route incorrect.');
assertMount(OfficeMount::route('projecten/detail', ['id' => 123]) === '/office-test/projecten/detail?id=123', 'Test project route incorrect.');

putenv('OFFICE_BASE_PATH=/wrong-path');
try {
  OfficeMount::basePath();
  throw new RuntimeException('Unknown mount accepted.');
}
catch (RuntimeException $e) {
  if ($e->getMessage() === 'Unknown mount accepted.') {
    throw $e;
  }
}
putenv('OFFICE_BASE_PATH');
echo "Office mount isolation checks passed.\n";
