<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcOpportunityLaunchInput.php';

use Brebo\Office\Calculation\OfficeCalcOpportunityLaunchInput;

$valid = OfficeCalcOpportunityLaunchInput::parse([
    'opportunity_id' => '42', 'revision' => '3', 'csrf' => str_repeat('a', 64),
]);
if ($valid !== ['opportunity_id' => 42, 'revision' => 3]) {
    throw new RuntimeException('Valid opportunity-scoped launch input rejected');
}
$invalid = [
    ['opportunity_id' => '42'],
    ['revision' => '1'],
    ['opportunity_id' => '0', 'revision' => '1'],
    ['opportunity_id' => '-1', 'revision' => '1'],
    ['opportunity_id' => '01', 'revision' => '1'],
    ['opportunity_id' => '1', 'revision' => '0'],
    ['opportunity_id' => 1, 'revision' => '1'],
    ['opportunity_id' => ['1'], 'revision' => '1'],
    ['opportunity_id' => '1', 'revision' => '1', 'project_id' => '2'],
    ['opportunity_id' => '1', 'revision' => '1', 'calculation_id' => '2'],
    ['opportunity_id' => '1', 'revision' => '1', 'operator_id' => '2'],
    ['opportunity_id' => '1', 'revision' => '1', 'unknown' => '2'],
    ['opportunity_id' => '99999999999999999999999999', 'revision' => '1'],
];
foreach ($invalid as $post) {
    if (OfficeCalcOpportunityLaunchInput::parse($post) !== null) {
        throw new RuntimeException('Unsafe CRM-first launch input accepted');
    }
}
echo "CALC_OPPORTUNITY_LAUNCH_INPUT=PASS\n";
