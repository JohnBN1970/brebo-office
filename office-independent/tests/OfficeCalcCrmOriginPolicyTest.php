<?php
declare(strict_types=1);
// Contract test: do not relax cross-origin POST checks; permit only explicit
// browser same-origin signals when Origin is omitted. CSRF must remain required.
$file = file_get_contents(dirname(__DIR__).'/public/office-calc-crm-start.php');
foreach ([
  "HTTP_ORIGIN",
  "HTTP_SEC_FETCH_SITE",
  "HTTP_REFERER",
  "same-origin",
  "hash_equals(\$expectedOrigin, \$origin)",
  "str_starts_with(\$referer, \$expectedOrigin . '/')",
  "CSRF_MISMATCH",
  "office_project_csrf",
  "hash_equals(\$csrf, \$submitted)",
] as $required) {
  if (!is_string($file) || !str_contains($file,$required)) {
    throw new RuntimeException('Origin or CSRF contract absent: '.$required);
  }
}
echo "CRM_CALC_POST_ORIGIN_POLICY=PASS\n";
