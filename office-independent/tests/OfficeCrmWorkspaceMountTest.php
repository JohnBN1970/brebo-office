<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeCrmWorkspaceMount.php';
$router="<?php\n\$routes = [\n  '/office-test/crm/relaties' => 'office-crm-relations.php',\n  '/office-test/inkoop' => 'office-governance.php',\n];\n";
$patched=OfficeCrmWorkspaceMount::router($router);
foreach(OfficeCrmWorkspaceMount::ROUTES as $url=>$file)if(!str_contains($patched,"'$url' => '$file'"))throw new RuntimeException('Missing native route');
if(!str_contains($patched,"'/office-test/inkoop' => 'office-governance.php'"))throw new RuntimeException('Unrelated route changed');
if(OfficeCrmWorkspaceMount::router($patched)!==$patched)throw new RuntimeException('Router not idempotent');
try{OfficeCrmWorkspaceMount::router(str_replace("'office-crm-opportunities.php'","'other.php'",$patched));throw new LogicException('conflict not rejected');}catch(RuntimeException $expected){}
$rewrite="RewriteEngine On\nRewriteRule ^finance$ index.php [END,QSA]\n";$out=OfficeCrmWorkspaceMount::rewrite($rewrite);
if(!str_contains($out,'RewriteRule ^finance$')||OfficeCrmWorkspaceMount::rewrite($out)!==$out)throw new RuntimeException('Rewrite preservation');
$nav=file_get_contents(dirname(__DIR__).'/src/Intake/OfficeNavigation.php');
if(OfficeCrmWorkspaceMount::navigation($nav)!==$nav)throw new RuntimeException('Current navigation altered unexpectedly');
$legacy=implode("\n",array_filter(explode("\n",$nav),static fn(string $line):bool=>!str_contains($line,"['Marketing','marketing',")&&!str_contains($line,"if(str_starts_with(\$path,'/office-test/marketing/'))")));
$legacy=str_replace("    elseif(str_starts_with(\$path,'/office-test/crm/'))","    if(str_starts_with(\$path,'/office-test/crm/'))",$legacy);
$legacy=str_replace("(getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')==='enabled' ? [['Verkoopkansen','/office-test/crm/verkoopkansen']] : [])","[['Aanvragen','/office-test/crm/aanvragen']]",$legacy);
$converted=OfficeCrmWorkspaceMount::navigation($legacy);
foreach(['/office-test/marketing/leads','/office-test/crm/verkoopkansen','/office-test/inkoop'] as $route)if(!str_contains($converted,$route))throw new RuntimeException('Legacy navigation transformation');
echo "CRM_WORKSPACE_MOUNT_OK: narrow routes, unchanged existing module, repeat safety and conflict rejection\n";
