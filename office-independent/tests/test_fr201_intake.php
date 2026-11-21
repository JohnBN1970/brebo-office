<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeFr201Intake.php';
use Brebo\Office\Process\OfficeFr201Intake as Intake;

$valid = [];
foreach (Intake::fields() as $key => $field) {
    if ($field['required']) $valid[$key] = 'test';
}
$valid['intake_id'] = 'INT-2026-0001';
$valid['contact_email'] = 'test@example.nl';
$valid['owner_user_id'] = '1';
if (Intake::validate($valid) !== []) {
    throw new RuntimeException('Valid intake rejected: '.json_encode(Intake::validate($valid)));
}
$missing = $valid;
unset($missing['contract_party']);
if (!isset(Intake::validate($missing)['contract_party'])) {
    throw new RuntimeException('Contract party must be required');
}
$invalid = $valid;
$invalid['intake_id'] = 'broken';
$invalid['contact_email'] = 'broken';
$invalid['owner_user_id'] = '0';
$errors = Intake::validate($invalid);
foreach (['intake_id', 'contact_email', 'owner_user_id'] as $field) {
    if (!isset($errors[$field])) throw new RuntimeException('Expected validation for '.$field);
}
echo "FR-201 contract OK\n";
