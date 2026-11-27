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
$evidence = array_fill_keys(['all_orders_and_projects_completed','all_invoices_paid','no_open_leads_or_deals','no_open_actions_or_restpoints','no_open_complaints','no_active_maintenance'], true);
if (!P::canAutoArchive('person',['customer'],$evidence)) throw new RuntimeException('Eligible private customer rejected');
foreach (['organization','company'] as $kind) {
    if (P::canAutoArchive($kind,['customer'],$evidence)) throw new RuntimeException('Business archived automatically');
    if (P::kindLabel($kind) !== 'Bedrijf') throw new RuntimeException('Business label mismatch');
}
if (P::canAutoArchive('person',['prospect'],$evidence)) throw new RuntimeException('Noncustomer archived');
foreach (array_keys($evidence) as $key) {
    $missing = $evidence; unset($missing[$key]);
    if (P::canAutoArchive('person',['customer'],$missing)) throw new RuntimeException('Missing evidence accepted: '.$key);
    $missing[$key] = false;
    if (P::canAutoArchive('person',['customer'],$missing)) throw new RuntimeException('Negative evidence accepted: '.$key);
}
if (P::roleLabel('prospect') !== 'Geïnteresseerde' || P::kindLabel('person') !== 'Particulier') throw new RuntimeException('CRM label mismatch');
echo "Office CRM relation foundation OK\n";
