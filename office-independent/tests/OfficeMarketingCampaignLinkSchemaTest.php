<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeMarketingCampaignLinkSchema.php';
use Brebo\Office\Crm\OfficeMarketingCampaignLinkSchema;
$statements=OfficeMarketingCampaignLinkSchema::statements();
if (count($statements)!==2) throw new RuntimeException('Expected two additive tables');
$sql=implode("\n",$statements);
foreach (['office_marketing_campaigns','office_marketing_crm_links','office_crm_leads(id)','office_crm_opportunities(id)','campaign_id','source_detail','chk_marketing_crm_link_target'] as $required) {
    if (!str_contains($sql,$required)) throw new RuntimeException('Missing marketing CRM link contract: '.$required);
}
if (preg_match('/\\b(DROP|TRUNCATE|ALTER|DELETE)\\b/i',$sql)) throw new RuntimeException('Non-additive migration');
echo "OFFICE_MARKETING_CRM_CAMPAIGN_LINK_SCHEMA=PASS\n";
