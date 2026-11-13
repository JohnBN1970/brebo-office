<?php
declare(strict_types=1);
/**
 * Read-only contract for CRM-first Calc Office-test router mount.
 * This script never edits a live router or .htaccess.
 */
$router = getenv('OFFICE_CALC_CRM_ROUTER_FILE') ?: '';
$rewrite = getenv('OFFICE_CALC_CRM_REWRITE_FILE') ?: '';
if ($router === '' || $rewrite === '' || !is_file($router) || !is_file($rewrite)) {
    fwrite(STDERR,"CRM_CALC_ROUTER_INPUT_MISSING\n");
    exit(2);
}
$source=file_get_contents($router);
$rules=file_get_contents($rewrite);
if (!is_string($source)||!is_string($rules)) exit(2);
$expected=[
    "'/office-test/calc/crm/werkbank' => 'office-calc-crm-workbench.php'",
    "'/office-test/calc/crm/start' => 'office-calc-crm-draft-launch.php'",
];
foreach($expected as $route) {
    if (!str_contains($source,$route)) {
        fwrite(STDERR,"CRM_CALC_ROUTE_MISSING: ".$route."\n");
        exit(1);
    }
}
if (!preg_match('~RewriteRule\\s+\\^.*calc/crm.*index\\.php~',$rules)) {
    fwrite(STDERR,"CRM_CALC_REWRITE_MISSING\n");
    exit(1);
}
echo "CRM_CALC_ROUTER_READONLY_CONTRACT=PASS\n";
