<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourShiftAggregatePostingService.php';
use Brebo\Office\Inzet\OfficeLabourShiftAggregatePostingService;
if (!method_exists(OfficeLabourShiftAggregatePostingService::class,'postInsideTransaction')) {
    throw new RuntimeException('Transactional posting API missing');
}
$source=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeLabourShiftAggregatePostingService.php');
foreach (['FOR UPDATE','OfficeLabourShiftAggregatePolicy::calculate',
    'office_inzet_labour_shift_approval_links','hash_equals','approved_seconds=?,variance_seconds=?',
    'Caller-owned transaction'] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('Posting invariant missing: '.$required);
}
echo "LABOUR_AGGREGATE_POSTING_CONTRACT_OK\n";
