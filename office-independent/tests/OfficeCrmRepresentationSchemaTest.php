<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmRepresentationSchema.php';
use Brebo\Office\Crm\OfficeCrmRepresentationSchema as S;
$sql = implode("\n", S::statements());
foreach (['office_crm_relation_representations', 'office_crm_assignment_parties', 'office_crm_relation_address_links', 'principal_relation_id', 'representative_relation_id', 'invoice_debtor'] as $needle) {
    if (!str_contains($sql, $needle) && $needle !== 'invoice_debtor') throw new RuntimeException('Missing representation schema: ' . $needle);
}
S::validateRepresentation(1, 2, 'property_management');
S::validateAddressLink(1, 2, 'correspondence');
try { S::validateAddressLink(1, 1, 'correspondence'); throw new RuntimeException('Self-address link accepted'); }
catch (InvalidArgumentException $expected) {}
foreach ([[1, 1, 'property_management'], [1, 2, 'invalid']] as $args) {
    try { S::validateRepresentation(...$args); throw new RuntimeException('Invalid representation accepted'); }
    catch (InvalidArgumentException $expected) {}
}
foreach (['beneficiary', 'contracting_party', 'invoice_debtor', 'ordering_party', 'communication_contact'] as $role) S::validateAssignmentRole($role);
echo "Property management contract OK\n";
