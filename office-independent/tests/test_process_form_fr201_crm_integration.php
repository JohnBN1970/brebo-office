<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/public/crm-new-requests.php');
foreach ([
    "is_scalar(\$leadOwner)",
    "hash_equals((string)\$leadOwner, (string)\$operator->operatorId)",
    "is_scalar(\$item['owner_operator_id'])",
    "hash_equals((string)\$item['owner_operator_id'], (string)\$operator->operatorId)",
    "OFFICE_TEST_FR201_WRITE_ENABLED",
    "crm_lead_board_csrf",
    "OfficeFr201Repository(\$db)",
] as $fragment) {
    if (!str_contains($source,$fragment)) throw new RuntimeException('CRM FR-201 authorization contract missing: '.$fragment);
}
echo "CRM FR-201 integration guards OK\n";
