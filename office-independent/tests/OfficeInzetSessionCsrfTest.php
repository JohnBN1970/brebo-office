<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';

use Brebo\Office\Inzet\OfficeInzetSessionCsrf as Csrf;

$session = hash('sha256', 'verified-session-one');
$other = hash('sha256', 'verified-session-two');
$secret = str_repeat('k', 40);
$token = Csrf::token($session, $secret);
if (!Csrf::verify($token, $session, $secret)) throw new RuntimeException('Valid session token rejected');
if (Csrf::verify($token, $other, $secret)) throw new RuntimeException('Token accepted for different session');
if (Csrf::verify($token, $session, str_repeat('x', 40))) throw new RuntimeException('Token accepted for different secret');
if (Csrf::verify(str_repeat('0',64), $session, $secret)) throw new RuntimeException('Forged token accepted');
if (Csrf::verify($token, 'unverified-session', $secret)) throw new RuntimeException('Unverified session accepted');
if (Csrf::verify($token, $session, 'short')) throw new RuntimeException('Weak secret accepted');
if ($token !== Csrf::token($session, $secret)) throw new RuntimeException('Token not stable within session');
echo "Office session-bound CSRF validation OK\n";
