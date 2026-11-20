<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmLeadSchema.php';
use Brebo\Office\Crm\OfficeCrmLeadSchema;
$statements = OfficeCrmLeadSchema::statements();
if (count($statements) !== 3) throw new RuntimeException('Lead schema table count changed.');
foreach (['office_crm_lead_sources', 'office_crm_leads', 'office_crm_lead_events'] as $table) {
    if (!str_contains(implode("\n", $statements), 'CREATE TABLE IF NOT EXISTS '.$table.' ')) {
        throw new RuntimeException('Missing additive table '.$table);
    }
}
$schema = implode("\n", $statements);
foreach (['source_id', 'converted_opportunity_id', 'source_detail', 'campaign', 'received_at', 'actor_operator_id'] as $column) {
    if (!str_contains($schema, $column)) throw new RuntimeException('Missing field '.$column);
}
if (count(OfficeCrmLeadSchema::sourceCodes()) < 10) throw new RuntimeException('Missing configured lead sources.');
echo "CRM lead schema contract OK\n";
