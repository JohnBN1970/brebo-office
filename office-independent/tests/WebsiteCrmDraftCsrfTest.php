<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftCsrf;

require_once __DIR__ . '/../src/Intake/WebsiteCrmDraftCsrf.php';

$csrf = new WebsiteCrmDraftCsrf(str_repeat('s', 64));
$token = $csrf->issue('operator-session-A', 42, 100000);
if (!$csrf->verify($token, 'operator-session-A', 42, 100001)
  || $csrf->verify($token, 'operator-session-B', 42, 100001)
  || $csrf->verify($token, 'operator-session-A', 43, 100001)
  || $csrf->verify($token, 'operator-session-A', 42, 100901)
  || $csrf->verify($token, 'operator-session-A', 42, 99999)) {
  throw new RuntimeException('CRM draft CSRF session, opportunity or expiration checks failed.');
}
echo "CRM draft CSRF checks passed.\n";
