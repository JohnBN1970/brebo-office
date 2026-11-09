<?php
declare(strict_types=1);

/** Pure, narrow transformations of shared Office-test files; never replaces other modules. */
final class OfficeCrmWorkspaceMount
{
    public const ROUTES=[
        '/office-test/marketing/leads'=>'office-crm-opportunities.php',
        '/office-test/marketing/leads/nieuw'=>'office-crm-opportunity-create.php',
        '/office-test/crm/verkoopkansen'=>'office-crm-opportunities.php',
        '/office-test/crm/verkoopkansen/nieuw'=>'office-crm-opportunity-create.php',
        '/office-test/crm/verkoopkansen/detail'=>'office-crm-opportunity-detail.php',
        '/office-test/crm/verkoopkansen/fase'=>'office-crm-opportunity-transition.php',
        '/office-test/crm/verkoopkansen/calculatie-context'=>'office-crm-calculation-context.php',
    ];
    public static function router(string $source):string
    {
        $add=[];
        foreach(self::ROUTES as $route=>$file){
            $line="  '$route' => '$file',";
            if(str_contains($source,"'$route'")){
                if(substr_count($source,"'$route'")!==1||!str_contains($source,$line))throw new RuntimeException('router_conflict');
            }else $add[]=$line;
        }
        if($add===[])return $source;
        $anchor="  '/office-test/crm/relaties' => 'office-crm-relations.php',";
        if(substr_count($source,$anchor)!==1)throw new RuntimeException('router_anchor');
        return str_replace($anchor,$anchor."\n".implode("\n",$add),$source);
    }
    public static function rewrite(string $source):string
    {
        $rule='RewriteRule ^(?:crm/verkoopkansen(?:/(?:nieuw|detail|fase|calculatie-context))?|marketing/leads(?:/nieuw)?)/?$ index.php [END,QSA]';
        if(str_contains($source,$rule))return $source;
        if(substr_count($source,'RewriteEngine On')!==1)throw new RuntimeException('rewrite_anchor');
        return str_replace('RewriteEngine On',"RewriteEngine On\n# Native CRM workspace\n".$rule,$source);
    }
    public static function navigation(string $source):string
    {
        if(!str_contains($source,"'/office-test/marketing/leads'")){
            $anchor="      ['Relaties & kansen','relaties',";
            if(substr_count($source,$anchor)!==1||!str_contains($source,'$crmAvailable'))throw new RuntimeException('navigation_anchor');
            $marketing="      ['Marketing','marketing',\$crmAvailable&&getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')==='enabled'?[['Leads','/office-test/marketing/leads']]:[]],\n";
            $source=str_replace($anchor,$marketing.$anchor,$source);
        }
        if(!str_contains($source,"'/office-test/crm/verkoopkansen'")){
            $old="[['Aanvragen','/office-test/crm/aanvragen']]";
            if(substr_count($source,$old)!==1)throw new RuntimeException('navigation_funnel_anchor');
            $source=str_replace($old,"(getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')==='enabled' ? [['Verkoopkansen','/office-test/crm/verkoopkansen']] : [])",$source);
        }
        if(!str_contains($source,"\$selected='marketing'")){
            $anchor="    if(str_starts_with(\$path,'/office-test/crm/'))";
            if(substr_count($source,$anchor)!==1)throw new RuntimeException('navigation_selection_anchor');
            $source=str_replace($anchor,"    if(str_starts_with(\$path,'/office-test/marketing/'))\$selected='marketing';\n    elseif(str_starts_with(\$path,'/office-test/crm/'))",$source);
        }
        return $source;
    }
}
