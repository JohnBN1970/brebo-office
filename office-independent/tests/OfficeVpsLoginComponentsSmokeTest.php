<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Intake/OfficeMount.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginCsrf.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginFormRenderer.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeOperatorAuthenticator.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginController.php';
require_once __DIR__.'/../src/Intake/WebsiteOfficeLoginThrottle.php';
use Brebo\Office\Intake\WebsiteOfficeLoginCsrf;
use Brebo\Office\Intake\WebsiteOfficeLoginFormRenderer;
putenv('OFFICE_BASE_PATH=/office-test');
$token=WebsiteOfficeLoginCsrf::issue();
if(!WebsiteOfficeLoginCsrf::verify($token,$token)||WebsiteOfficeLoginCsrf::verify($token,str_repeat('0',64)))throw new RuntimeException('CSRF failure');
$form=(new WebsiteOfficeLoginFormRenderer())->render($token,true);
if(!str_contains($form,'csrf_token')||!str_contains($form,'type="password"')||!str_contains($form,'Ongeldige aanmeldgegevens'))throw new RuntimeException('Login form failure');
echo "PASS login component smoke\n";
