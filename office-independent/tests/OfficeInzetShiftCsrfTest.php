<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCsrf.php';

use Brebo\Office\Inzet\OfficeInzetShiftCsrf as Csrf;

$token = Csrf::issue();
if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new RuntimeException('CSRF token format invalid');
if (!Csrf::verify($token,$token)) throw new RuntimeException('Valid CSRF token rejected');
foreach ([$token.'a',strtoupper($token),null,'',str_repeat('0',64)] as $invalid) {
  if (Csrf::verify($invalid,$token)) throw new RuntimeException('Invalid CSRF token accepted');
}
if (Csrf::verify($token,null)) throw new RuntimeException('Missing session token accepted');
if ($token === Csrf::issue()) throw new RuntimeException('CSRF token not random');
echo "Office shift CSRF validation OK\n";
