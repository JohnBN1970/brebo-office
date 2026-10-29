<?php
declare(strict_types=1);

/**
 * Source-level regression guard for the isolated Office-test sales draft form.
 * This complements, but does not replace, browser/session acceptance testing.
 */
$source = file_get_contents(dirname(__DIR__) . '/public/office-finance-sales-new.php');
if ($source === false) throw new RuntimeException('Sales draft form unavailable');

$checks = [
  'Token generated only on GET' => "if ((" . "\$_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !is_string(\$_SESSION['sales_draft_csrf'] ?? null))",
  'POST rejects missing token' => "if (!isset(\$_SESSION['sales_draft_csrf']) || !is_string(\$_SESSION['sales_draft_csrf']))",
  'POST uses constant-time token comparison' => "hash_equals(\$_SESSION['sales_draft_csrf'], \$_POST['csrf'])",
  'Successful write invalidates token' => "unset(\$_SESSION['sales_draft_csrf']);",
  'Successful write redirects with 303' => "true, 303",
  'Session cookie is HTTP-only' => "'httponly'=>true",
  'Session cookie is secure' => "'secure'=>true",
  'Session cookie uses strict same-site policy' => "'samesite'=>'Strict'",
];
foreach ($checks as $label => $needle) {
  if (!str_contains($source, $needle)) {
    throw new RuntimeException('Sales draft CSRF regression: ' . $label);
  }
}
$create = strpos($source, "->create(\$operator->operatorId, \$input)");
$invalidate = strpos($source, "unset(\$_SESSION['sales_draft_csrf']);");
$redirect = strpos($source, "true, 303");
if ($create === false || $invalidate === false || $redirect === false || !($create < $invalidate && $invalidate < $redirect)) {
  throw new RuntimeException('Sales draft CSRF token must be invalidated after successful create, before redirect');
}
echo "Sales draft CSRF source regression OK\n";
