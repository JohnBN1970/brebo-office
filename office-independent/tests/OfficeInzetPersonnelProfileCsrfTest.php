<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';
use Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf as ProfileCsrf;
use Brebo\Office\Inzet\OfficeInzetSessionCsrf as ShiftCsrf;
$session = str_repeat('a', 64);
$secret = str_repeat('z', 48);
$token = ProfileCsrf::token($session, $secret);
if (!ProfileCsrf::verify($token, $session, $secret)) throw new RuntimeException('Profile token rejected');
if (ProfileCsrf::verify($token, str_repeat('b', 64), $secret)) throw new RuntimeException('Token valid for other session');
if (ProfileCsrf::verify($token, $session, str_repeat('q', 48))) throw new RuntimeException('Token valid for other secret');
if (ProfileCsrf::verify(ShiftCsrf::token($session, $secret), $session, $secret)) throw new RuntimeException('Shift token accepted for profile');
if (ProfileCsrf::verify(['bad'], $session, $secret)) throw new RuntimeException('Array token accepted');
try {
  ProfileCsrf::token('unverified', $secret);
  throw new RuntimeException('Invalid session accepted');
} catch (InvalidArgumentException $expected) {}
echo "Personnel profile CSRF isolation OK\n";
