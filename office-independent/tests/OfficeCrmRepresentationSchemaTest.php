<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmRepresentationSchema.php';
use Brebo\Office\Crm\OfficeCrmRepresentationSchema as S;
$sql = implode("\n", S::statements());
foreach (['office_crm_relation_representations', 'office_crm_assignment_parties', 'principal_relation_id', 'representative_relation_id', 'invoice_debtor'] as $needle) {
    if (!str_contains($sql, $needle) && $needle !== 'invoice_debtor') throw new RuntimeException('Missing representation schema: ' . $needle);
}
S::validateRepresentation(1, 2, 'vve_manager');
foreach ([[1, 1, 'vve_manager'], [1, 2, 'invalid']] as $args) {
    try { S::validateRepresentation(...$args); throw new RuntimeException('Invalid representation accepted'); }
    catch (InvalidArgumentException $expected) {}
}
foreach (['beneficiary', 'contracting_party', 'invoice_debtor', 'ordering_party', 'communication_contact'] as $role) S::validateAssignmentRole($role);
echo "VvE representation contract OK\n";
