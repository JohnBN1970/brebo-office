<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../public/office-finance.php');
if ($source === false) {
    throw new RuntimeException('Finance entrypoint not readable');
}
$flag = "getenv('OFFICE_FINANCE_PRESENTATION_MODE') === 'enabled'";
$auth = '$operator === null';
$presentation = strpos($source, $flag);
$authentication = strpos($source, $auth);
$financeRead = strpos($source, '$method =');
if ($authentication === false || $presentation === false || $financeRead === false
    || $presentation <= $authentication || $presentation >= $financeRead) {
    throw new RuntimeException('Presentation intercept must follow authentication and precede finance reads');
}
$block = substr($source, $presentation, $financeRead - $presentation);
foreach ([
    "!== 'GET'",
    "http_response_code(405)",
    "OfficeFinancePresentationView::render()",
    "Cache-Control: no-store",
    "exit;"
] as $required) {
    if (!str_contains($block, $required)) {
        throw new RuntimeException('Presentation interception missing: ' . $required);
    }
}
echo "OfficeFinancePresentationRoutingTest OK\n";
