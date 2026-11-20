<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunitySchema.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
use Brebo\Office\Crm\OfficeCrmOpportunitySchema;
use Brebo\Office\Crm\OfficeCrmOpportunityRepository;
use Brebo\Office\Crm\OfficeCrmFunnel;
$statements=OfficeCrmOpportunitySchema::statements();
if (count($statements)!==2) throw new RuntimeException('Expected opportunity and activity tables');
foreach (['office_crm_opportunities','office_crm_opportunity_activities','office_crm_relations','office_crm_contact_people','next_action_at','opname_required'] as $marker) {
    if (!str_contains(implode("\n",$statements),$marker)) throw new RuntimeException('Missing '.$marker);
}
if (!OfficeCrmFunnel::canTransition('prospect','opportunity')) throw new RuntimeException('Prospect to opportunity must be allowed');
if (OfficeCrmFunnel::canTransition('lead','won')) throw new RuntimeException('Invalid shortcut');
$ref = new ReflectionClass(OfficeCrmOpportunityRepository::class);
foreach (['listForViews','create','transition'] as $method) if (!$ref->hasMethod($method)) throw new RuntimeException('Missing '.$method);
$repositorySource=file_get_contents(dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php');
if (!str_contains($repositorySource,"INSERT INTO office_crm_opportunities (relation_id,contact_id,title,stage,currency) VALUES (?,?,?,'prospect','EUR')")) throw new RuntimeException('Deal creation must use canonical opportunity schema');
if (!str_contains($repositorySource,'new OfficeCrmProjectHandoff($this->db)')) throw new RuntimeException('Unified verified project handoff must remain intact');
if (OfficeCrmFunnel::label('opportunity')!=='Deal') throw new RuntimeException('Commercial stage must be labelled Deal');
echo "CRM_OPPORTUNITY_CONTRACT_OK\n";
