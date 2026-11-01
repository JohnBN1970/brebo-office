<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/** One information architecture for all Office HTML responses. Never grants access. */
final class OfficeNavigation {
  public const AREAS=[
    'bedrijf'=>['Bedrijfsoverzicht','/'],
    'crm'=>['CRM','/crm/bedrijven'],
    'projecten'=>['Projecten','/projecten'],
    'personeel'=>['Personeel','/projecten?view=inzet'],
    'calculatie-offertes'=>['Calculatie & Offertes','/calculatie-offertes'],
    'finance'=>['Finance','/finance'],
    'informatie'=>['Informatie','/informatie'],
  ];
  public const GOTIK=['geld'=>'Geld','organisatie'=>'Organisatie','tijd'=>'Tijd','informatie'=>'Informatie','kwaliteit'=>'Kwaliteit'];
  private static function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
  public static function current(string $path):string {
    if(str_starts_with($path,'/office-test/calc-')||str_starts_with($path,'/office-test/offertes'))return 'calculatie-offertes';
    foreach(['crm','calculatie-offertes','finance','informatie']as$area)if($path==='/office-test/'.$area||str_starts_with($path,'/office-test/'.$area.'/'))return $area;
    if(str_starts_with($path,'/office-test/inzet/')||str_starts_with($path,'/office-test/personeel'))return 'personeel';
    if(str_starts_with($path,'/office-test/projecten'))return 'projecten';
    return 'bedrijf';
  }
  public static function main(string $current):string {
    $html='<nav class="office-global-nav" aria-label="Hoofdnavigatie">';
    foreach(self::AREAS as$key=>[$label,$path])$html.='<a'.($key===$current?' aria-current="page"':'').' href="/office-test'.$path.'">'.self::h($label).'</a>';
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
  public static function mainV2(string $path):string {
    // Only advertise modules whose existing test-environment gates are enabled.
    // This is presentation logic, never a substitute for per-request ACL checks.
    $crmAvailable=getenv('OFFICE_TEST_CRM_ENABLED')==='enabled'
      && getenv('OFFICE_CRM_AUTH_MODE')==='session_only';
    $projectsAvailable=getenv('OFFICE_NATIVE_PROJECTS_ENABLED')==='enabled'
      && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED')==='enabled';
    $groups=[
      ['Mijn werkplek','werkplek',[['Startpagina','/office-test/']]],
      ['Relaties & kansen','relaties',$crmAvailable?[['Aanvragen','/office-test/crm/aanvragen']]:[]],
      ['Projecten','projecten',$projectsAvailable?[['Projectoverzicht','/office-test/projecten']]:[]],
      ['Personeel','personeel',$projectsAvailable?[
        ['Medewerkers','/office-test/personeel/medewerkers'],
        ['Inzet en planning','/office-test/projecten?view=inzet&tab=planning'],
        ['OnSite','/office-test/projecten?view=inzet&tab=onsite'],
        ['Urencontrole','/office-test/projecten?view=inzet&tab=controle'],
      ]:[]],
      ['Gebouwen','gebouwen',$projectsAvailable?[[ 'Gebouwenoverzicht','/office-test/gebouwen' ]]:[]],
      ['Bedrijfsvoering','bedrijfsvoering',self::financeVisible()?[
        ['Finance overzicht','/office-test/finance'],
        ['Betaalcentrum','/office-test/finance/betaalcentrum'],
        ['Nieuwe inkoopfactuur','/office-test/finance/nieuw'],
      ]:[]],
      ['Kennis & kwaliteit','kennis',[]],
    ];
    $selected='werkplek';
    if(str_starts_with($path,'/office-test/crm/'))$selected='relaties';
    elseif(str_starts_with($path,'/office-test/inzet/')||str_starts_with($path,'/office-test/personeel')||($path==='/office-test/projecten'&&($_GET['view']??null)==='inzet'))$selected='personeel';
    elseif(str_starts_with($path,'/office-test/projecten'))$selected='projecten';
    elseif(str_starts_with($path,'/office-test/gebouwen'))$selected='gebouwen';
    elseif(str_starts_with($path,'/office-test/finance'))$selected='bedrijfsvoering';
    elseif(str_starts_with($path,'/office-test/informatie'))$selected='kennis';
    $html='<nav class="office-global-nav office-global-nav-v2" aria-label="Hoofdnavigatie">';
    foreach($groups as [$label,$key,$links]){
      $open=$selected===$key;
      $html.='<details class="office-nav-group"'.($open?' open':'').'><summary'.($open?' aria-current="page"':'').'>'.self::h($label).'</summary><div class="office-nav-children">';
      if($links===[])$html.='<span class="office-nav-unavailable">Nog niet aangesloten</span>';
      foreach($links as [$name,$url])$html.='<a href="'.self::h($url).'">'.self::h($name).'</a>';
      $html.='</div></details>';
    }
    return $html.'</nav>';
  }
  public static function projectId(string $path,array $query):?int {
    if(in_array($path,['/office-test/projecten/detail','/office-test/inzet/projectkaart','/office-test/inzet/aanwezigheid','/office-test/inzet/onsite/uren-beoordelen'],true))$value=$query['id']??null;
    elseif(in_array($path,['/office-test/calculatie-offertes','/office-test/finance','/office-test/informatie'],true))$value=$query['project_id']??null;
    else return null;
    if(!is_string($value)||!preg_match('/^[1-9][0-9]{0,18}$/D',$value))return null;
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return is_int($id)?$id:null;
  }
  public static function scope(int $id,string $area='projecten'):string {
    $base='/office-test/projecten/detail?id='.$id;
    $html='<section class="office-project-context" aria-label="Projectnavigatie"><p><a href="/office-test/projecten">Projecten</a> / <a href="'.$base.'">Project '.$id.'</a></p><nav aria-label="Projectonderdelen">';
    foreach(['projecten'=>'Projectoverzicht','calculatie-offertes'=>'Calculatie & Offertes','finance'=>'Finance','informatie'=>'Informatie']as$key=>$label){if($key==='finance'&&!self::financeVisible())continue;$url=$key==='projecten'?$base:'/office-test/'.$key.'?project_id='.$id;$html.='<a'.($key===$area?' aria-current="page"':'').' href="'.$url.'">'.self::h($label).'</a>';}
    $html.='</nav><nav aria-label="GOTIK projectsturing">';
    foreach(self::GOTIK as$key=>$label)$html.='<a href="'.$base.'#gotik-'.$key.'">'.self::h($label).'</a>';
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
    $current=self::current($path);$nav=getenv('OFFICE_TEST_NAV_V2_ENABLED')==='enabled'?self::mainV2($path):self::main($current);
    $count=0;$html=preg_replace_callback('~<nav\b[^>]*\baria-label="Hoofdnavigatie"[^>]*>.*?</nav>~si',static fn()=>$nav,$html,1,$count)??$html;
    if($count===0)$html=self::wrapStandalonePage($html,$nav);
    // Keep page-owned Module tabs. Removing them hides Projecten/Inzet navigation.
    $css='<style>.office-global-nav-v2{display:block!important}.office-global-nav-v2 .office-nav-group{margin:4px 0;border-radius:8px}.office-global-nav-v2 summary{cursor:pointer;padding:12px;font-weight:650;list-style-position:inside}.office-global-nav-v2 summary[aria-current=page]{background:#175a91;color:white}.office-global-nav-v2 .office-nav-children{display:grid;gap:4px;padding:4px 8px 8px 16px}.office-global-nav-v2 .office-nav-children a{display:block;margin:0}.office-global-nav-v2 .office-nav-unavailable{font-size:13px;color:#697789;padding:8px}.office-global-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}.office-global-nav a,.office-project-context nav a{display:inline-block;padding:9px 12px;border-radius:7px;text-decoration:none;background:#edf2f7;color:#173c60;font-weight:650}.office-global-nav a[aria-current=page],.office-project-context a[aria-current=page]{background:#175a91;color:white}aside .office-global-nav{display:grid;gap:6px}aside .office-global-nav a{margin:0}.office-project-context{padding:12px 0;border-block:1px solid #dce3eb;margin:16px 0 24px}.office-project-context nav{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.office-steering{margin:24px 0}.office-steering-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.office-steering-grid>section{padding:16px;border:1px solid #dce3eb;border-radius:10px;background:#fff;color:#243244}.office-steering-grid h3{margin:0 0 10px}.office-unavailable{color:#627185;font-size:.9rem}</style>';
    $css.='<style>.office-shell{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}.office-sidebar{background:#14263b;color:#eaf0f7;padding:24px 16px}.office-sidebar>a{display:block;color:white;font-size:22px;font-weight:800;text-decoration:none;margin:0 8px 24px}.office-sidebar .office-global-nav{display:grid;gap:6px;margin:0}.office-sidebar .office-global-nav a{color:#eaf0f7;background:transparent;display:block}.office-sidebar .office-global-nav a:hover{background:#29445f}.office-sidebar .office-nav-group summary{color:#eaf0f7}.office-sidebar .office-nav-group summary[aria-current=page]{background:#175a91}.office-sidebar .office-nav-unavailable{color:#b7c7d8}.office-shell>main{width:100%;min-width:0;max-width:1180px;margin:0 auto;padding:32px 24px}@media(max-width:760px){.office-shell{grid-template-columns:minmax(0,1fr)}.office-sidebar{padding:12px 16px}.office-shell>main{padding:20px 16px}}</style>';
    $html=preg_replace_callback('~</head>~i',static fn()=>$css.'</head>',$html,1,$styled)??$html;
    if(!$styled)$html=preg_replace_callback('~<main\b[^>]*>~i',static fn($m)=>$css.$m[0],$html,1)??$html;
    $project=self::projectId($path,$query);
    $scope=$project===null?'':self::scope($project,$current);
    $root=in_array($path,['/office-test','/office-test/'],true);
    if($root && getenv('OFFICE_TEST_NAV_V2_ENABLED')!=='enabled')$html=str_replace(['Mijn werkplek','Goedemorgen, welkom in Office'],['Bedrijfsoverzicht','Bedrijfsoverzicht'],$html);
    $steering=$path==='/office-test/projecten/detail'?self::steering($project):'';
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
    ob_start(static function(string $html)use($path):string{$type='';foreach(headers_list()as$header)if(str_starts_with(strtolower($header),'content-type:'))$type=trim(substr($header,13));return self::decorate($html,$path,$_GET,http_response_code()?:200,$type);});
  }
}
