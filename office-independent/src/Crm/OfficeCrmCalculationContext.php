<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Consumer contract for the separate Calc workbench. Never creates a project or grants Calc access. */
final class OfficeCrmCalculationContext
{
    public static function fromOpportunity(array $opportunity): array
    {
        if(!in_array($opportunity['stage']??null,['prospect','opportunity','proposal','negotiation'],true)
            ||($opportunity['status']??null)!=='open')throw new \DomainException('Kwalificeer eerst de lead; een afgesloten kans is niet bewerkbaar.');
        return ['schema_version'=>1,'source'=>'office_crm_opportunities','context_type'=>'crm_opportunity',
            'opportunity_id'=>OfficeCrmOpportunityPolicy::id($opportunity['id']??null),
            'revision'=>OfficeCrmOpportunityPolicy::id($opportunity['revision']??null),
            'relation_id'=>OfficeCrmOpportunityPolicy::id($opportunity['relation_id']??null),
            'contact_id'=>empty($opportunity['contact_id'])?null:OfficeCrmOpportunityPolicy::id($opportunity['contact_id']),
            'title'=>(string)$opportunity['title'],'scope'=>(string)($opportunity['description']??''),
            'service_type'=>$opportunity['service_type'],'currency'=>'EUR'];
    }
}
