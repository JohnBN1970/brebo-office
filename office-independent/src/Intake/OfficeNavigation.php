<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/** One information architecture for all Office HTML responses. Never grants access. */
final class OfficeNavigation {
  public const AREAS=[
    'bedrijf'=>['Bedrijfsoverzicht','/'],
    'crm'=>['CRM','/crm/bedrijven'],
    'marketing'=>['Marketing','/marketing'],
    'projecten'=>['Projecten','/projecten'],
    'gebouwen'=>['Gebouwen & inspecties','/gebouwen'],
    'personeel'=>['Personeel','/projecten?view=inzet'],
    'calculatie-offertes'=>['Calculatie & Offertes','/calculatie-offertes'],
    'finance'=>['Finance','/finance'],
    'kwaliteit'=>['Kwaliteit','/kwaliteit'],
    'risicos'=>['Risico’s','/risicos'],
    'inkoop'=>['Inkoop','/inkoop'],
    'informatie'=>['Informatie','/informatie'],
  ];
  public const GOTIK=['geld'=>'Geld','organisatie'=>'Organisatie','tijd'=>'Tijd','informatie'=>'Informatie','kwaliteit'=>'Kwaliteit'];
  private static function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
  public static function current(string $path):string {
    if(str_starts_with($path,'/office-test/calc-')||str_starts_with($path,'/office-test/calculatie')||str_starts_with($path,'/office-test/crm/offertes')||str_starts_with($path,'/office-test/offertes'))return 'calculatie-offertes';
    foreach(['marketing','crm','calculatie-offertes','finance','informatie','kwaliteit','risicos','inkoop']as$area)if($path==='/office-test/'.$area||str_starts_with($path,'/office-test/'.$area.'/'))return $area;
    if(str_starts_with($path,'/office-test/documenten')||str_starts_with($path,'/office-test/email')||str_starts_with($path,'/office-test/mail'))return 'informatie';
    if(str_starts_with($path,'/office-test/gebouwen')||str_starts_with($path,'/office-test/inspectie'))return 'gebouwen';
    if(str_starts_with($path,'/office-test/inzet/')||str_starts_with($path,'/office-test/personeel'))return 'personeel';
    if(str_starts_with($path,'/office-test/projecten'))return 'projecten';
    return 'bedrijf';
  }
  public static function main(string $current):string {
    $html='<nav class="office-global-nav" aria-label="Hoofdnavigatie">';
    foreach(self::AREAS as$key=>[$label,$path]){if(in_array($key,['kwaliteit','risicos','inkoop'],true)&&!self::governanceVisible())continue;$html.='<a'.($key===$current?' aria-current="page"':'').' href="/office-test'.$path.'">'.self::h($label).'</a>';}
    return $html.'</nav>';
  }
  /** Office 2.0 presentation only; routes and ACL remain server-owned. */
  public static function financeVisible():bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    return $cached = self::resolveFinanceVisibility();
  }

  private static function resolveFinanceVisibility():bool {
    if (getenv('OFFICE_FINANCE_ENABLED')!=='enabled'
      || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED')!=='enabled'
      || getenv('OFFICE_CRM_SESSION_MODE')!=='enabled') return false;
    try {
      foreach ([
        'OfficeMount.php',
        'WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php',
        'WebsiteOfficeCrmCookieSessionProvider.php',
      ] as $file) require_once __DIR__.'/'.$file;
      require_once dirname(__DIR__).'/Finance/OfficeFinanceAccess.php';
      $dsn=getenv('OFFICE_INTAKE_PDO_DSN');
      if (!is_string($dsn)||$dsn==='') return false;
      $db=new \PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_EMULATE_PREPARES=>false]);
      $provider=new WebsiteOfficeCrmCookieSessionProvider(
        new WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
      $operator=$provider->currentOperator();
      return $operator!==null
        && (new \Brebo\Office\Finance\OfficeFinanceAccess($db,$operator->operatorId))->allows('read');
    } catch (\Throwable $e) {
      return false;
    }
  }
  public static function governanceVisible():bool {
    return getenv('OFFICE_BASE_PATH')==='/office-test'
      && getenv('OFFICE_TEST_GOVERNANCE_ENABLED')==='enabled'
      && getenv('OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED')==='enabled';
  }
  public static function mainV2(string $path):string {
    // Only advertise modules whose existing test-environment gates are enabled.
    // This is presentation logic, never a substitute for per-request ACL checks.
    $crmAvailable=getenv('OFFICE_TEST_CRM_ENABLED')==='enabled'
      && getenv('OFFICE_CRM_AUTH_MODE')==='session_only';
    $projectsAvailable=getenv('OFFICE_NATIVE_PROJECTS_ENABLED')==='enabled'
      && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')==='enabled';
    $quoteLinks=[];
    foreach (['/office-test/crm/offertes','/office-test/offertes'] as $quotePath) {
      if (self::runtimeRouteExists($quotePath)) {$quoteLinks[]=['Offertestudio',$quotePath];break;}
    }
    $informationLinks=[['Documenten','/office-test/documenten']];
    foreach (['/office-test/email','/office-test/mail','/office-test/communicatie'] as $mailPath) {
      if (self::runtimeRouteExists($mailPath)) {$informationLinks[]=['E-mail',$mailPath];break;}
    }
    if (getenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED') === 'enabled'
        && self::runtimeRouteExists('/office-test/mail/controle')) {
      $informationLinks[] = ['Mailcontrole', '/office-test/mail/controle'];
    }
    $groups=[
      ['Mijn werkplek','werkplek',array_merge([['Startpagina','/office-test/']], (getenv('OFFICE_PERSONAL_DASHBOARD_ENABLED')==='enabled' && getenv('OFFICE_DASHBOARD_PREFERENCES_SCHEMA_VERIFIED')==='enabled') ? [['Mijn dashboard','/office-test/mijn-dashboard'],['Dashboardbouwer','/office-test/mijn-dashboard?view=builder'],['KPI Wallboard','/office-test/mijn-dashboard?view=wallboard']] : [])],
      ['Marketing','marketing',getenv('OFFICE_TEST_MARKETING_ENABLED')==='enabled' ? [['Marketingoverzicht','/office-test/marketing']] : []],
      ['Leads','leads',$crmAvailable && getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED')==='enabled' ? [['Leadoverzicht','/office-test/crm/aanvragen'],['Nieuwe lead','/office-test/crm/aanvragen/nieuw']] : []],
      ['Relaties & kansen','relaties',$crmAvailable?array_merge((getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')==='enabled' ? [['Relaties','/office-test/crm/relaties']] : []), (getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED')==='enabled' ? [['Deals','/office-test/crm/verkoopkansen']] : [])):[]],
      ['Calculatie & offertes','calculatie',array_merge(getenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED')==='enabled'?[['Calculaties','/office-test/calculatie']]:[],$quoteLinks)],
      ['Projecten','projecten',$projectsAvailable?[
        ['Projectoverzicht','/office-test/projecten'],['Projectplanning · Gantt','/office-test/projecten?view=gantt'],['Acties · Kanban','/office-test/projecten?view=kanban']
      ]:[]],
      ['Personeel','personeel',$projectsAvailable?[
        ['Medewerkers','/office-test/personeel/medewerkers'],
        ['Inzet en planning','/office-test/projecten?view=inzet&tab=planning'],
        ['OnSite','/office-test/projecten?view=inzet&tab=onsite'],
        ['Urencontrole','/office-test/projecten?view=inzet&tab=controle'],
      ]:[]],
      ['Gebouwen','gebouwen',$projectsAvailable?array_merge(
        [['Gebouwenoverzicht','/office-test/gebouwen']],
        (getenv('OFFICE_TEST_INSPECTION_UI_ENABLED')==='enabled'
          && getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED')==='enabled')
          ? [['Inspecties','/office-test/inspecties'],['Nieuwe inspectie','/office-test/inspectie']]
          : []
      ):[]],
      ['Bedrijfsvoering','bedrijfsvoering',array_merge(self::financeVisible()?[
        ['Finance overzicht','/office-test/finance'],
        ...(getenv('OFFICE_SALES_ENABLED')==='enabled'?[['Verkoopfacturen','/office-test/finance/verkoop']]:[]),
        ...(getenv('OFFICE_FINANCE_CENTER_ENABLED')==='enabled'?[['Financiële administratie','/office-test/finance/administratie']]:[]),
        ['Betaalcentrum','/office-test/finance/betaalcentrum'],
        ['Nieuwe inkoopfactuur','/office-test/finance/nieuw'],
      ]:[],self::governanceVisible()?[['Inkoop','/office-test/inkoop']]:[])],
      ['Documenten & communicatie','informatie',$informationLinks],
      ['Rapporten','rapporten',getenv('OFFICE_TEST_INSPECTION_UI_ENABLED')==='enabled' && getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED')==='enabled' ? [['Inspectierapporten','/office-test/inspecties']] : []],
      ['Kennis & kwaliteit','kennis',self::governanceVisible()?[['Kwaliteit','/office-test/kwaliteit'],['Risico’s','/office-test/risicos']]:[]],
    ];
    $selected='werkplek';
    if (str_starts_with($path,'/office-test/calculatie') || str_starts_with($path,'/office-test/crm/offertes') || str_starts_with($path,'/office-test/offertes'))$selected='calculatie';
    elseif(str_starts_with($path,'/office-test/documenten') || str_starts_with($path,'/office-test/email') || str_starts_with($path,'/office-test/mail') || str_starts_with($path,'/office-test/communicatie'))$selected='informatie';
    elseif(str_starts_with($path,'/office-test/marketing'))$selected='marketing';
    elseif(str_starts_with($path,'/office-test/crm/aanvragen'))$selected='leads';
    elseif(str_starts_with($path,'/office-test/crm/'))$selected='relaties';
    elseif(str_starts_with($path,'/office-test/inzet/')||str_starts_with($path,'/office-test/personeel')||($path==='/office-test/projecten'&&($_GET['view']??null)==='inzet'))$selected='personeel';
    elseif(str_starts_with($path,'/office-test/projecten'))$selected='projecten';
    elseif(str_starts_with($path,'/office-test/gebouwen')||str_starts_with($path,'/office-test/inspectie'))$selected='gebouwen';
    elseif(str_starts_with($path,'/office-test/inkoop'))$selected='bedrijfsvoering';
    elseif(str_starts_with($path,'/office-test/kwaliteit')||str_starts_with($path,'/office-test/risicos'))$selected='kennis';
    elseif(str_starts_with($path,'/office-test/finance'))$selected='bedrijfsvoering';
    elseif(str_starts_with($path,'/office-test/rapporten'))$selected='rapporten';
    elseif(str_starts_with($path,'/office-test/informatie'))$selected='kennis';
    $html='<nav class="office-global-nav office-global-nav-v2" aria-label="Hoofdnavigatie">';
    foreach($groups as [$label,$key,$links]){
      $open=$selected===$key;
      $html.='<details class="office-nav-group"'.($open?' open':'').'><summary'.($open?' aria-current="page"':'').'>'.self::h($label).'</summary><div class="office-nav-children">';
      if($links===[])$html.='<span class="office-nav-unavailable">Nog niet aangesloten</span>';
      foreach($links as [$name,$url]) {
        $linkPath=parse_url($url,PHP_URL_PATH);$linkQuery=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$linkQuery);
        $active=$path===$linkPath && array_diff_assoc($linkQuery,$_GET)===[]
          && (isset($linkQuery['view']) || !isset($_GET['view']));
        $html.='<a'.($active?' aria-current="page"':'').' href="'.self::h($url).'">'.self::h($name).'</a>';
      }
      $html.='</div></details>';
    }
    return $html.'</nav>';
  }
  /** Inspect only declared routes in the isolated test router; never advertise guessed URLs. */
  private static function runtimeRouteExists(string $route):bool {
    // The VPS deploy uses /srv/brebo/office-test/current rather than
    // Hostinger's /office_calc_test/release-*/private layout.
    // Only advertise mail routes present in the canonical router AND release.
    if (in_array($route, ['/office-test/email', '/office-test/mail/controle'], true)) {
      $router = dirname(__DIR__, 2).'/deployment/hostinger/office-test-index.php';
      $target = $route === '/office-test/email' ? 'office-email.php' : 'office-mail-review.php';
      if (is_file($router) && !is_link($router)
          && is_file(dirname(__DIR__, 2).'/public/'.$target)) {
        $source = file_get_contents($router);
        if (is_string($source)
            && str_contains($source, "'".$route."' => '".$target."'")) return true;
      }
    }
    $private=realpath(getenv('OFFICE_PRIVATE_ROOT')?:'');
    if (!$private || !str_contains($private,'/office_calc_test/release-') || !str_ends_with($private,'/private')) return false;
    $base=dirname($private,2);
    $files=array_merge([dirname($base).'/public_html/office-test/index.php'],glob($private.'/modules/*/routes.php')?:[]);
    foreach ($files as $file) {
      if (is_link($file)||!is_file($file)) continue;
      $source=file_get_contents($file);
      if (!is_string($source)) continue;
      $pattern="~['\"]".preg_quote($route,'~')."['\"]\\s*(?:=>|\\]\\s*=)\\s*['\"]([a-zA-Z0-9_.-]+\\.php)['\"]~";
      if (preg_match($pattern,$source,$match)===1 && is_file($private.'/public/'.$match[1])) return true;
    }
    return false;
  }
  public static function projectId(string $path,array $query):?int {
    if(in_array($path,['/office-test/projecten/detail','/office-test/inzet/projectkaart','/office-test/inzet/aanwezigheid','/office-test/inzet/onsite/uren-beoordelen'],true))$value=$query['id']??null;
    elseif(in_array($path,['/office-test/calculatie-offertes','/office-test/finance','/office-test/informatie'],true))$value=$query['project_id']??null;
    else return null;
    if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,18}$/D',$value))return null;
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return is_int($id)?$id:null;
  }
  public static function scope(int $id,string $area='projecten'):string {
    // Project detail has its own dossier tabs; avoid duplicate area/GOTIK/inspection links.
    if ($area === 'projecten') return '';
    $base='/office-test/projecten/detail?id='.$id;
    $html='<section class="office-project-context" aria-label="Projectnavigatie"><p><a href="/office-test/projecten">Projecten</a> / <a href="'.$base.'">Project '.$id.'</a></p><nav aria-label="Projectonderdelen">';
    foreach(['projecten'=>'Projectoverzicht','calculatie-offertes'=>'Calculatie & Offertes','finance'=>'Finance','informatie'=>'Informatie']as$key=>$label){if($key==='finance'&&!self::financeVisible())continue;$url=$key==='projecten'?$base:'/office-test/'.$key.'?project_id='.$id;$html.='<a'.($key===$area?' aria-current="page"':'').' href="'.$url.'">'.self::h($label).'</a>';}
    $html.='</nav><nav aria-label="GOTIK projectsturing">';
    foreach(self::GOTIK as$key=>$label)$html.='<a href="'.$base.'#gotik-'.$key.'">'.self::h($label).'</a>';
    if(getenv('OFFICE_TEST_INSPECTION_UI_ENABLED')==='enabled' && getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED')==='enabled')$html.='<a href="/office-test/inspectie?project_id='.$id.'">Nieuwe inspectie</a> · <a href="/office-test/inspecties?project_id='.$id.'">Projectinspecties</a>';
    return $html.'</nav></section>';
  }
  public static function steering(?int $project):string {
    $context=$project===null?'Bedrijfssturing':'Projectsturing';
    $html='<section class="office-steering" aria-label="GOTIK"><h2>'.$context.' volgens GOTIK</h2><div class="office-steering-grid">';
    $descriptions=['geld'=>'Begroting, verplichtingen, kosten, prognose en resultaat.','organisatie'=>'Verantwoordelijkheden, projectteam, inzet en OnSite.','tijd'=>'Planning, voortgang en mijlpalen.','informatie'=>'Documenten, correspondentie, afspraken en besluiten.','kwaliteit'=>'Eisen, controles, afwijkingen en oplevering.'];
    foreach(self::GOTIK as$key=>$label){$html.='<section id="gotik-'.$key.'"><h3>'.$label.'</h3><p>'.$descriptions[$key].'</p>';
      $query=$project===null?'':'?project_id='.$project;
      if($key==='geld'){$html.='<a href="/office-test/calculatie-offertes'.$query.'">Calculatie & Offertes</a>';if(self::financeVisible())$html.=' · <a href="/office-test/finance'.$query.'">Finance</a>';}
      elseif($key==='organisatie')$html.=$project===null?'<a href="/office-test/projecten?view=inzet">Organisatie en inzet over projecten</a>':'<a href="/office-test/inzet/projectkaart?id='.$project.'">Projectkaart en zones</a> · <a href="/office-test/inzet/aanwezigheid?id='.$project.'">OnSite</a> · <a href="/office-test/inzet/onsite/uren-beoordelen?id='.$project.'">Uren beoordelen</a>';
      elseif($key==='informatie')$html.='<a href="/office-test/informatie'.$query.'">Informatie openen</a>';
      elseif($key==='tijd'&&$project===null)$html.='<a href="/office-test/projecten?view=inzet&amp;tab=planning">Weekplanning en inzet openen</a><p class="office-unavailable">Projectmijlpalen nog niet aangesloten.</p>';
      else $html.='<p class="office-unavailable">'.($key==='tijd'?'Projectplanning en mijlpalen nog niet aangesloten.':'Kwaliteitscontroles nog niet aangesloten.').'</p>';
      $html.='</section>';
    }
    return $html.'</div></section>';
  }
  private static function wrapStandalonePage(string $html,string $nav):string {
    if (str_contains($html,'class="shell"')) return $html;
    $aside='<aside class="office-sidebar" aria-label="Zijmenu"><a href="/office-test/">BREBO Office</a>'.$nav.'</aside>';
    $html=preg_replace_callback('~<main\\b[^>]*>~i',static fn($m)=>'<div class="office-shell">'.$aside.$m[0],$html,1,$count)??$html;
    if($count)$html=preg_replace('~</main>~i','</main></div>',$html,1)??$html;
    return $html;
  }
  public static function decorate(string $html,string $path,array $query,int $status,string $type):string {
    if($status!==200||!str_starts_with(strtolower($type),'text/html')||!str_starts_with($path,'/office-test')||in_array($path,['/office-test/inloggen','/office-test/uitloggen'],true)||str_contains($path,'/api/')||!preg_match('/<html\b/i',$html))return $html;
    if (str_contains($html,'data-office-theme="brebo-2026"')) return $html;
    $current=self::current($path);$nav=getenv('OFFICE_TEST_NAV_V2_ENABLED')==='enabled'?self::mainV2($path):self::main($current);
    $count=0;$html=preg_replace_callback('~<nav\b[^>]*\baria-label="Hoofdnavigatie"[^>]*>.*?</nav>~si',static fn()=>$nav,$html,1,$count)??$html;
    if($count===0)$html=self::wrapStandalonePage($html,$nav);
    // Keep page-owned Module tabs. Removing them hides Projecten/Inzet navigation.
    $css='<style>.office-global-nav-v2{display:block!important}.office-global-nav-v2 .office-nav-group{margin:4px 0;border-radius:8px}.office-global-nav-v2 summary{cursor:pointer;padding:12px;font-weight:650;list-style-position:inside}.office-global-nav-v2 summary[aria-current=page]{background:#175a91;color:white}.office-global-nav-v2 .office-nav-children{display:grid;gap:4px;padding:4px 8px 8px 16px}.office-global-nav-v2 .office-nav-children a{display:block;margin:0}.office-global-nav-v2 .office-nav-unavailable{font-size:13px;color:#697789;padding:8px}.office-global-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}.office-global-nav a,.office-project-context nav a{display:inline-block;padding:9px 12px;border-radius:7px;text-decoration:none;background:#edf2f7;color:#173c60;font-weight:650}.office-global-nav a[aria-current=page],.office-project-context a[aria-current=page]{background:#175a91;color:white}aside .office-global-nav{display:grid;gap:6px}aside .office-global-nav a{margin:0}.office-project-context{padding:12px 0;border-block:1px solid #dce3eb;margin:16px 0 24px}.office-project-context nav{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.office-steering{margin:24px 0}.office-steering-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.office-steering-grid>section{padding:16px;border:1px solid #dce3eb;border-radius:10px;background:#fff;color:#243244}.office-steering-grid h3{margin:0 0 10px}.office-unavailable{color:#627185;font-size:.9rem}</style>';
    $css.='<style>.office-shell{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}.office-sidebar{background:#14263b;color:#eaf0f7;padding:24px 16px}.office-sidebar>a{display:block;color:white;font-size:22px;font-weight:800;text-decoration:none;margin:0 8px 24px}.office-sidebar .office-global-nav{display:grid;gap:6px;margin:0}.office-sidebar .office-global-nav a{color:#eaf0f7;background:transparent;display:block}.office-sidebar .office-global-nav a:hover{background:#29445f}.office-sidebar .office-nav-group summary{color:#eaf0f7}.office-sidebar .office-nav-group summary[aria-current=page]{background:#175a91}.office-sidebar .office-nav-unavailable{color:#b7c7d8}.office-shell>main{width:100%;min-width:0;max-width:1180px;margin:0 auto;padding:32px 24px}@media(max-width:760px){.office-shell{grid-template-columns:minmax(0,1fr)}.office-sidebar{padding:12px 16px}.office-shell>main{padding:20px 16px}}</style>';
    require_once __DIR__.'/OfficeTheme.php';
    $css.=OfficeTheme::styles().'<script>'.OfficeTheme::script().'</script>';
    $html=preg_replace_callback('~<body\\b([^>]*)>~i',static function($m):string {
      $attrs=$m[1];
      if (preg_match('/\\bclass="([^"]*)"/i',$attrs)) $attrs=preg_replace('/\\bclass="([^"]*)"/i','class="$1 office-ui"',$attrs,1);
      else $attrs.=' class="office-ui"';
      return '<body'.$attrs.'>';
    },$html,1)??$html;
    $module=$current==='bedrijf'?'Mijn werkplek':(self::AREAS[$current][0]??'Mijn werkplek');
    // Test-only role preview: presentation context, never an authorization identity.
    // The real Office operator and project ACL continue to govern every request.
    $previewRoles=[
      'actual'=>'Eigen account', 'medewerker'=>'Medewerker',
      'RL-001'=>'Directie', 'RL-002'=>'Senior Projectleider',
      'RL-003'=>'Senior Uitvoerder', 'RL-004'=>'Senior Werkvoorbereider',
      'RL-005'=>'Calculator / Inkoper', 'RL-006'=>'KAM / Kwaliteitsmanager',
      'RL-007'=>'Controller', 'RL-008'=>'Administrateur',
      'RL-009'=>'Directiesecretaresse', 'RL-010'=>'Planner / Capaciteitscoördinator',
      'RL-011'=>'Contract- en Documentcontroller', 'RL-012'=>'Service- en Garantiecoördinator',
      'RL-013'=>'Omgevings- en Bewonerscoördinator', 'RL-014'=>'Kennis- en Procesbeheerder',
      'RL-015'=>'BREBO Virtuele Staf', 'RL-016'=>'Bedrijfsjurist / Juridisch Adviseur',
      'admin'=>'Systeembeheerder / Admin',
    ];
    $roleOptions='';
    foreach($previewRoles as $code=>$label)$roleOptions.='<option value="'.self::h($code).'">'.self::h($label).'</option>';
    $rolePreview='<label class="office-role-preview">Bekijk als <select id="office-role-preview" aria-label="Bekijk Office als functie">'.$roleOptions.'</select></label><span id="office-role-preview-status" role="status" aria-live="polite"></span>';
    $css.='<style>.office-role-preview{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:650;color:inherit}.office-role-preview select{max-width:220px;min-width:120px;border:1px solid #7890a8;border-radius:7px;padding:7px;background:var(--office-surface,#172234);color:var(--office-text,#fff)}#office-role-preview-status{font-size:11px;color:inherit;opacity:.85}@media(max-width:700px){.office-role-preview select{max-width:145px}}</style>';
    $bar='<header class="office-app-bar"><div><a href="/office-test/">BREBO Office</a><strong>'.self::h($module).'</strong></div><div class="office-app-controls"><span class="office-env-badge">TESTOMGEVING</span>'.$rolePreview.OfficeTheme::controls().'</div></header>';
    $html=preg_replace_callback('~<main\\b[^>]*>~i',static fn($m)=>$m[0].$bar,$html,1)??$html;
    $html=preg_replace_callback('~</head>~i',static fn()=>$css.'</head>',$html,1,$styled)??$html;
    if(!$styled)$html=preg_replace_callback('~<main\b[^>]*>~i',static fn($m)=>$css.$m[0],$html,1)??$html;
    $project=self::projectId($path,$query);
    $scope=$project===null?'':self::scope($project,$current);
    $root=in_array($path,['/office-test','/office-test/'],true);
    if($root && getenv('OFFICE_TEST_NAV_V2_ENABLED')!=='enabled')$html=str_replace(['Mijn werkplek','Goedemorgen, welkom in Office'],['Bedrijfsoverzicht','Bedrijfsoverzicht'],$html);
    // V3 owns project context and tabs; do not inject legacy GOTIK or scope.
    $isV3Detail=$path==='/office-test/projecten/detail' && str_contains($html,'class="office-project-detail-v3"');
    if($isV3Detail)$scope='';
    // Project V3 has its own dashboard; never inject legacy GOTIK steering cards.
    $steering='';
    if($root){
      $links='<details class="office-steering-links"><summary>Onderdelen per GOTIK</summary>'.self::steering(null).'</details>';
      if(str_contains($html,'<!--office-overview-navigation-->'))$html=str_replace('<!--office-overview-navigation-->',$links,$html);
      else $html=preg_replace_callback('~</main>~i',static fn()=>$links.'</main>',$html,1)??$html;
    }
    if($scope!==''||$steering!=='')$html=preg_replace_callback('~</h1>~i',static fn() => '</h1>'.$scope.$steering,$html,1)??$html;
    return $html;
  }
  public static function register():void {
    if(getenv('OFFICE_BASE_PATH')!=='/office-test'||($_SERVER['REQUEST_METHOD']??'')!=='GET')return;
    $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);if(!is_string($path))return;
    ob_start(static function(string $html)use($path):string{$type='';foreach(headers_list()as$header)if(str_starts_with(strtolower($header),'content-type:'))$type=trim(substr($header,13));$result=self::decorate($html,$path,$_GET,http_response_code()?:200,$type);
      if(str_contains($result,'data-office-theme="brebo-2026"')&&!headers_sent()) {
        foreach(headers_list() as $header) if(str_starts_with(strtolower($header),'content-security-policy:')) header('Content-Security-Policy: '.OfficeTheme::policy(trim(substr($header,24))));
      }
      return $result;});
  }
}
