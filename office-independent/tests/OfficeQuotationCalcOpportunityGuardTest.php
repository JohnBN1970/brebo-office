<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/src/Quotation/OfficeQuotationCalcGenerationService.php');
if (!is_string($source)) throw new RuntimeException('Missing quotation generation service');
foreach ([
    "is_int(\$opportunityId)",
    'office_crm_opportunities WHERE id = ? AND relation_id = ?',
    'CRM opportunity does not belong to calculation relation',
    'OfficeQuotationCalcDraftFactory',
] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing Calc CRM opportunity guard: ' . $required);
}
if (strpos($source, 'CRM opportunity does not belong to calculation relation') >
    strpos($source, 'OfficeQuotationCalcDraftFactory($this->db)')) {
    throw new RuntimeException('Opportunity must be checked before draft creation');
}
echo "QUOTATION_CALC_OPPORTUNITY_GUARD=PASS\n";
