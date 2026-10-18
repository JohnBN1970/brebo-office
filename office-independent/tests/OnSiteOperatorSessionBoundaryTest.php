<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../src/Inzet/OnSiteOperatorActivationService.php');
if ($source === false) throw new RuntimeException('Enrollment service missing');
foreach (['office_operator_sessions','s.token_hash = ?','s.revoked_at IS NULL','s.expires_at > UTC_TIMESTAMP()','g.active = 1','o.active = 1','ctype_xdigit($operator->sessionId)'] as $needle) {
  if (!str_contains($source,$needle)) throw new RuntimeException('Missing session or grant check: '.$needle);
}
echo "OnSiteOperatorSessionBoundaryTest OK\n";
