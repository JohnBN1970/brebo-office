<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeMarketingCampaignLinkRepository.php';
use Brebo\Office\Crm\OfficeMarketingCampaignLinkRepository;
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE office_marketing_campaigns (id INTEGER PRIMARY KEY,code TEXT UNIQUE,name TEXT,source_code TEXT)');
$pdo->exec('CREATE TABLE office_marketing_crm_links (id INTEGER PRIMARY KEY,campaign_id INTEGER,lead_id INTEGER,opportunity_id INTEGER,source_detail TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$repo=new OfficeMarketingCampaignLinkRepository($pdo);
$id=$repo->createCampaign('website_2026','Website 2026','website');
if($id!==1) throw new RuntimeException('Campaign create failed');
$repo->link($id,'lead',10,'contactformulier');
$repo->link($id,'opportunity',20,'CRM-conversie');
$lead=$repo->forCrmRecord('lead',10);
$opportunity=$repo->forCrmRecord('opportunity',20);
if(count($lead)!==1 || $lead[0]['campaign_code']!=='website_2026' || count($opportunity)!==1 || count($repo->forCampaign($id))!==2) throw new RuntimeException('Attribution lookup failed');
foreach([['lead',0],['invalid',10]] as [$type,$target]) {
    try {$repo->link($id,$type,$target);throw new RuntimeException('Invalid link accepted');}
    catch (InvalidArgumentException $e) {}
}
echo "OFFICE_MARKETING_CRM_CAMPAIGN_REPOSITORY=PASS\n";
