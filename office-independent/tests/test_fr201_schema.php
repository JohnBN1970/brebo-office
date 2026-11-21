<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeFr201Schema.php';
use Brebo\Office\Process\OfficeFr201Schema as Schema;

$statements = Schema::statements();
if (count($statements) !== 2) throw new RuntimeException('Expected two additive FR-201 tables');
foreach (['office_crm_lead_fr201', 'office_crm_lead_fr201_events'] as $table) {
    if (!str_contains(implode("\n", $statements), $table)) {
        throw new RuntimeException('Missing table: '.$table);
    }
}
if (!str_contains($statements[1], 'UNIQUE KEY uq_crm_lead_fr201_event_revision (lead_id, revision)')) {
    throw new RuntimeException('Missing immutable revision uniqueness');
}
$method = new ReflectionMethod(Schema::class, 'verify');
if (!$method->isPublic() || !$method->isStatic()) {
    throw new RuntimeException('Schema preflight must be public and static');
}
echo "FR-201 schema contract OK\n";
