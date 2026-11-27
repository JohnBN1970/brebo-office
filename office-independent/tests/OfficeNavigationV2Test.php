<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/OfficeNavigation.php';
use Brebo\Office\Intake\OfficeNavigation;
putenv('OFFICE_NATIVE_PROJECTS_ENABLED=enabled');
putenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED=enabled');
$nav=OfficeNavigation::mainV2('/office-test/projecten');
foreach(['Mijn werkplek','Marketing','Sales','Relaties','Projecten','Resources','Finance','Documenten &amp; communicatie'] as $name) {
  if(!str_contains($nav,$name)){fwrite(STDERR,"Missing group: ".$name.PHP_EOL);exit(1);}
}
if(substr_count($nav,'<details ')!==10){fwrite(STDERR,"Expected ten collapsible groups".PHP_EOL);exit(1);}
if(substr_count($nav,' open')!==1){fwrite(STDERR,"Expected one open group".PHP_EOL);exit(1);}
if(!str_contains($nav,'/office-test/projecten?view=inzet')){fwrite(STDERR,"Missing Inzet link".PHP_EOL);exit(1);}
if(!str_contains($nav,'href="/office-test/gebouwen"')){fwrite(STDERR,"Buildings overview link missing".PHP_EOL);exit(1);}
if(!str_contains(OfficeNavigation::mainV2('/office-test/gebouwen'),'aria-current="page"')){fwrite(STDERR,"Buildings navigation not selected".PHP_EOL);exit(1);}
if (!str_contains($nav, 'Personeel') || !str_contains($nav, 'Inzet en planning')) { fwrite(STDERR, "Personnel links missing\n"); exit(1); }
foreach (['/office-test/personeel/medewerkers','tab=planning','tab=onsite','tab=controle'] as $tab) { if (!str_contains($nav, $tab)) { fwrite(STDERR, "Missing personnel tab: $tab\n"); exit(1); } }
putenv('OFFICE_TEST_INSPECTION_UI_ENABLED=enabled');
putenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED=enabled');
$inspectionNav=OfficeNavigation::mainV2('/office-test/inspectie');
foreach (['/office-test/inspecties','/office-test/inspectie'] as $url) {
  if (!str_contains($inspectionNav,'href="'.$url.'"')) { fwrite(STDERR,"Missing inspection link: $url\n"); exit(1); }
}
if (!str_contains($inspectionNav,'<summary aria-current="page">Resources</summary>')) {
  fwrite(STDERR,"Inspection must select Buildings navigation\n"); exit(1);
}
putenv('OFFICE_TEST_INSPECTION_UI_ENABLED=disabled');
if (str_contains(OfficeNavigation::mainV2('/office-test/gebouwen'),'href="/office-test/inspectie')) {
  fwrite(STDERR,"Inspection links exposed with UI disabled\n"); exit(1);
}
putenv('OFFICE_TEST_INSPECTION_UI_ENABLED=enabled');
putenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED=disabled');
if (str_contains(OfficeNavigation::mainV2('/office-test/gebouwen'),'href="/office-test/inspectie')) {
  fwrite(STDERR,"Inspection links exposed with schema disabled\n"); exit(1);
}
putenv('OFFICE_TEST_INSPECTION_UI_ENABLED=disabled');
putenv('OFFICE_NATIVE_PROJECTS_ENABLED=disabled');
$unavailable=OfficeNavigation::mainV2('/office-test/projecten');
if(str_contains($unavailable,'href="/office-test/projecten"')||str_contains($unavailable,'view=inzet')||str_contains($unavailable,'href="/office-test/gebouwen"')){fwrite(STDERR,"Disabled projects must not be linked".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_CRM_ENABLED=enabled');
putenv('OFFICE_CRM_AUTH_MODE=session_only');
putenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED=enabled');
$leadsNav=OfficeNavigation::mainV2('/office-test/crm/aanvragen');
if (!str_contains($leadsNav, 'href="/office-test/crm/aanvragen"') || !str_contains($leadsNav, 'Leads') || !str_contains($leadsNav, '<summary aria-current="page">Sales</summary>')) { fwrite(STDERR, "CRM Leads link missing with schema enabled\\n"); exit(1); }
putenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED=disabled');
if (str_contains(OfficeNavigation::mainV2('/office-test/crm/aanvragen'), 'href="/office-test/crm/aanvragen"')) { fwrite(STDERR, "CRM Leads link exposed with schema disabled\\n"); exit(1); }
putenv('OFFICE_TEST_CRM_ENABLED=disabled');
if(str_contains(OfficeNavigation::mainV2('/office-test/crm/aanvragen'),'href="/office-test/crm/aanvragen"')){fwrite(STDERR,"Disabled CRM must not be linked".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_MARKETING_ENABLED=enabled');
$marketingNav=OfficeNavigation::mainV2('/office-test/marketing');
if (!str_contains($marketingNav, 'href="/office-test/marketing"') || !str_contains($marketingNav, '<summary aria-current="page">Marketing</summary>')) {
  fwrite(STDERR, "Marketing navigation missing or not selected".PHP_EOL); exit(1);
}
putenv('OFFICE_TEST_MARKETING_ENABLED=disabled');
if (str_contains(OfficeNavigation::mainV2('/office-test/marketing'), 'href="/office-test/marketing"')) {
  fwrite(STDERR, "Marketing link exposed when disabled".PHP_EOL); exit(1);
}
putenv('OFFICE_TEST_NAV_V2_ENABLED=enabled');
$html='<html><head></head><body><aside><nav aria-label="Hoofdnavigatie">Old</nav></aside><main><h1>Projecten</h1><nav aria-label="Module"><a href="/office-test/projecten?view=inzet">Inzet</a></nav></main></body></html>';
$html=str_replace('<body>','<body class="existing-page">',$html);
$html=str_replace('</main>','<form method="post"><input name="csrf_token" value="preserved"><button>Opslaan</button></form></main>',$html);
$out=OfficeNavigation::decorate($html,'/office-test/projecten',[],200,'text/html');
if(substr_count($out,'aria-label="Hoofdnavigatie"')!==1||!str_contains($out,'aria-label="Module"')){fwrite(STDERR,"Navigation regression".PHP_EOL);exit(1);}
if (!str_contains($out,'class="existing-page office-ui"') || !str_contains($out,'data-office-theme="brebo-2026"') || substr_count($out,'class="office-app-bar"')!==1 || !str_contains($out,'name="csrf_token" value="preserved"')) throw new RuntimeException('Theme must preserve body, forms and one app bar');
if (OfficeNavigation::decorate($out,'/office-test/projecten',[],200,'text/html')!==$out) throw new RuntimeException('Decoration must be idempotent');

$previewScript=file_get_contents(dirname(__DIR__).'/resources/office-theme.js');
if (!is_string($previewScript) || !str_contains($previewScript, "brebo-office-role-preview")
    || !str_contains($previewScript, "localStorage.setItem(key, picker.value)")
    || !str_contains($previewScript, "window.addEventListener('pageshow', restore)")
    || !str_contains($previewScript, "window.addEventListener('storage'")
    || !str_contains($previewScript, 'sessionStorage.getItem(key)')
    || !str_contains($previewScript, "picker.addEventListener('change'")
    || !str_contains($previewScript, 'Voorbeeld: ')
    || !str_contains($out, 'id="office-role-preview"')
    || !str_contains($out, 'id="office-role-preview-status"')) {
    throw new RuntimeException('Role preview selection is not wired to visible Office-test UI');
}
if (str_contains($out, '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var picker=')) {
    throw new RuntimeException('CSP-blocked inline role preview must not be injected');
}
require_once dirname(__DIR__).'/src/Intake/OfficeTheme.php';
$policy=\Brebo\Office\Intake\OfficeTheme::policy("default-src 'none'; script-src 'none'");
$hash="'sha256-".base64_encode(hash('sha256',$previewScript,true))."'";
if (!str_contains($policy,$hash)) throw new RuntimeException('CSP must authorize shared role preview script');

if (OfficeNavigation::decorate($html,'/office-test/projecten',[],403,'text/html')!==$html) throw new RuntimeException('Error responses must be preserved');
$home='<html><head></head><body><nav aria-label="Hoofdnavigatie">Old</nav><main><h1>Goedemorgen, welkom in Office</h1><p>Mijn werkplek</p></main></body></html>';
$homeV2=OfficeNavigation::decorate($home,'/office-test/',[],200,'text/html');
if(!str_contains($homeV2,'<h1>Goedemorgen, welkom in Office</h1>')){fwrite(STDERR,"V2 workspace title lost".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_NAV_V2_ENABLED=disabled');
$homeLegacy=OfficeNavigation::decorate($home,'/office-test/',[],200,'text/html');
if(!str_contains($homeLegacy,'<h1>Bedrijfsoverzicht</h1>')){fwrite(STDERR,"Legacy dashboard title changed".PHP_EOL);exit(1);}
$old=OfficeNavigation::decorate($html,'/office-test/projecten',[],200,'text/html');
if(str_contains($old,'<details class="office-nav-group"')){fwrite(STDERR,"Flag disabled but v2 active".PHP_EOL);exit(1);}

// Finance must never be advertised when the feature is disabled.
putenv('OFFICE_FINANCE_ENABLED=disabled');
putenv('OFFICE_FINANCE_SCHEMA_VERIFIED=disabled');

$financeNav = OfficeNavigation::mainV2('/office-test/finance');
foreach ([
  '/office-test/finance',
  '/office-test/finance/betaalcentrum',
  '/office-test/finance/nieuw',
] as $financeUrl) {
  if (str_contains($financeNav, 'href="' . $financeUrl . '"')) {
    fwrite(STDERR, "Disabled Finance link exposed: " . $financeUrl . PHP_EOL);
    exit(1);
  }
}

$scope = OfficeNavigation::scope(1);
if (str_contains($scope, 'href="/office-test/finance')) {
  fwrite(STDERR, "Finance link exposed in project navigation" . PHP_EOL);
  exit(1);
}

$steering = OfficeNavigation::steering(1);
if (str_contains($steering, 'href="/office-test/finance')) {
  fwrite(STDERR, "Finance link exposed in GOTIK" . PHP_EOL);
  exit(1);
}


// Finance enabled without an authenticated Office session must fail closed.
putenv('OFFICE_FINANCE_ENABLED=enabled');
putenv('OFFICE_FINANCE_SCHEMA_VERIFIED=enabled');
putenv('OFFICE_CRM_SESSION_MODE=enabled');

require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
unset($_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]);

$unauthorizedNav = OfficeNavigation::mainV2('/office-test/finance');
if (str_contains($unauthorizedNav, 'href="/office-test/finance')) {
  fwrite(STDERR, "Finance exposed without authenticated session".PHP_EOL);
  exit(1);
}

if (str_contains(OfficeNavigation::scope(1), 'href="/office-test/finance')
  || str_contains(OfficeNavigation::steering(1), 'href="/office-test/finance')) {
  fwrite(STDERR, "Finance project links exposed without session".PHP_EOL);
  exit(1);
}

// The actual VPS entrypoint must dispatch Mail, not merely advertise it.
$vpsRouter = file_get_contents(dirname(__DIR__).'/public/index.php');
foreach ([
  "'/office-test/email' => 'office-email.php'",
  "'/office-test/mail/controle' => 'office-mail-review.php'",
] as $mapping) {
  if (!is_string($vpsRouter) || !str_contains($vpsRouter, $mapping)) {
    fwrite(STDERR, "VPS router missing Mail mapping: ".$mapping.PHP_EOL); exit(1);
  }
}
// Native Mail is a canonical VPS route and must not disappear from the menu.
putenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED=disabled');
$mailNav = OfficeNavigation::mainV2('/office-test/email');
if (!str_contains($mailNav, 'href="/office-test/email"')) {
  fwrite(STDERR, "Native VPS Mail navigation missing".PHP_EOL); exit(1);
}
if (str_contains($mailNav, 'href="/office-test/mail/controle"')) {
  fwrite(STDERR, "Mail review link exposed while disabled".PHP_EOL); exit(1);
}
putenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED=enabled');
if (!str_contains(OfficeNavigation::mainV2('/office-test/email'), 'href="/office-test/mail/controle"')) {
  fwrite(STDERR, "Verified Mail review navigation missing".PHP_EOL); exit(1);
}
putenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED=disabled');

// Process-oriented navigation: modules stay behind their existing server gates.
putenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED=enabled');
putenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED=enabled');
putenv('OFFICE_TEST_CRM_ENABLED=enabled');
putenv('OFFICE_CRM_AUTH_MODE=session_only');
$dealNav=OfficeNavigation::mainV2('/office-test/crm/verkoopkansen');
if (!str_contains($dealNav,'<summary aria-current="page">Sales</summary>')
  || !str_contains($dealNav,'href="/office-test/crm/verkoopkansen"')) throw new RuntimeException('Deals must belong to Sales');
$relationNav=OfficeNavigation::mainV2('/office-test/crm/relaties');
if (!str_contains($relationNav,'<summary aria-current="page">Relaties</summary>')
  || !str_contains($relationNav,'Klanten')
  || !str_contains($relationNav,'Leveranciers')) throw new RuntimeException('Relations must have dedicated group');
$projectNav=OfficeNavigation::mainV2('/office-test/projecten');
if (!str_contains($projectNav,'Inzet en planning')
  || !str_contains($projectNav,'<summary aria-current="page">Projecten</summary>')) throw new RuntimeException('Inzet must belong to Projecten');
$resourceNav=OfficeNavigation::mainV2('/office-test/personeel/medewerkers');
if (!str_contains($resourceNav,'<summary aria-current="page">Resources</summary>')) throw new RuntimeException('Personnel must belong to Resources');
if (str_contains($projectNav,'<summary>Personeel</summary>')
  || str_contains($projectNav,'<summary>Gebouwen</summary>')
  || str_contains($projectNav,'<summary>Bedrijfsvoering</summary>')) throw new RuntimeException('Legacy menu headings still present');
echo "OfficeNavigationV2Test OK".PHP_EOL;
