<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Crm/OfficeCrmRelationsSchema.php';
require_once __DIR__ . '/../src/Crm/OfficeCrmRelationPolicy.php';
use Brebo\Office\Crm\OfficeCrmRelationsSchema as S;
use Brebo\Office\Crm\OfficeCrmRelationPolicy as P;
$sql = implode("\n", S::statements());
foreach (['office_crm_relations', 'office_crm_relation_roles', 'office_crm_contact_people', 'office_crm_relation_contacts', 'office_crm_external_identities'] as $table) {
    if (!str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table)) throw new RuntimeException('Missing CRM table: ' . $table);
}
$relation = P::validate('organization', ' Example BV ', ['prospect', 'supplier']);
if ($relation['display_name'] !== 'Example BV' || $relation['roles'] !== ['prospect', 'supplier']) throw new RuntimeException('CRM multi-role failed');
foreach ([['person', '', ['customer']], ['organization', 'Company', ['supplier', 'supplier']], ['organization', 'Company', ['unknown']]] as $case) {
    try { P::validate(...$case); throw new RuntimeException('Invalid CRM relation accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "Office CRM relation foundation OK\n";
