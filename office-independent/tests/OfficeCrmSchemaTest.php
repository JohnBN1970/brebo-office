<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchema.php';
$statements = \Brebo\Office\Crm\OfficeCrmSchema::statements();
if (count($statements) !== 10) throw new RuntimeException('Expected ten CRM tables');
$ddl = implode("\n", $statements);
foreach (['office_crm_companies','office_crm_contacts','office_crm_company_contacts','office_crm_opportunities','office_crm_opportunity_companies','office_crm_opportunity_contacts','office_crm_company_buildings','office_crm_contact_buildings','office_crm_company_projects','office_crm_contact_projects','FOREIGN KEY','valid_from','valid_until'] as $needle) {
  if (!str_contains($ddl, $needle)) throw new RuntimeException('Missing CRM schema component: '.$needle);
}
if (str_contains($ddl, 'DROP TABLE') || str_contains($ddl, 'DELETE FROM')) throw new RuntimeException('Destructive schema operation');
echo "OfficeCrmSchema OK\n";
