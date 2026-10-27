<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/** One information architecture for all Office HTML responses. Never grants access. */
final class OfficeNavigation {
  public const AREAS=[
    'bedrijf'=>['Bedrijfsoverzicht','/'],
    'crm'=>['CRM','/crm/bedrijven'],
    'projecten'=>['Projecten','/projecten'],
    'calculatie-offertes'=>['Calculatie & Offertes','/calculatie-offertes'],
    'finance'=>['Finance','/finance'],
    'informatie'=>['Informatie','/informatie'],
  ];
  public const GOTIK=['geld'=>'Geld','organisatie'=>'Organisatie','tijd'=>'Tijd','informatie'=>'Informatie','kwaliteit'=>'Kwaliteit'];
  private static function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
  public static function current(string $path):string {
    if(str_starts_with($path,'/office-test/calc-')||str_starts_with($path,'/office-test/offertes'))return 'calculatie-offertes';
    foreach(['crm','calculatie-offertes','finance','informatie']as$area)if($path==='/office-test/'.$area||str_starts_with($path,'/office-test/'.$area.'/'))return $area;
    if(str_starts_with($path,'/office-test/projecten')||str_starts_with($path,'/office-test/inzet/'))return 'projecten';
    return 'bedrijf';
  }
  public static function main(string $current):string {
    $html='<nav class="office-global-nav" aria-label="Hoofdnavigatie">';
    foreach(self::AREAS as$key=>[$label,$path])$html.='<a'.($key===$current?' aria-current="page"':'').' href="/office-test'.$path.'">'.self::h($label).'</a>';
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
    foreach(['projecten'=>'Projectoverzicht','calculatie-offertes'=>'Calculatie & Offertes','finance'=>'Finance','informatie'=>'Informatie']as$key=>$label){$url=$key==='projecten'?$base:'/office-test/'.$key.'?project_id='.$id;$html.='<a'.($key===$area?' aria-current="page"':'').' href="'.$url.'">'.self::h($label).'</a>';}
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
      if($key==='geld')$html.='<a href="/office-test/calculatie-offertes'.$query.'">Calculatie & Offertes</a> · <a href="/office-test/finance'.$query.'">Finance</a>';
      elseif($key==='organisatie')$html.=$project===null?'<a href="/office-test/projecten?view=inzet">Organisatie en inzet over projecten</a>':'<a href="/office-test/inzet/projectkaart?id='.$project.'">Projectkaart en zones</a> · <a href="/office-test/inzet/aanwezigheid?id='.$project.'">OnSite</a> · <a href="/office-test/inzet/onsite/uren-beoordelen?id='.$project.'">Uren beoordelen</a>';
      elseif($key==='informatie')$html.='<a href="/office-test/informatie'.$query.'">Informatie openen</a>';
      elseif($key==='tijd'&&$project===null)$html.='<a href="/office-test/projecten?view=inzet&amp;tab=planning">Weekplanning en inzet openen</a><p class="office-unavailable">Projectmijlpalen nog niet aangesloten.</p>';
      else $html.='<p class="office-unavailable">'.($key==='tijd'?'Projectplanning en mijlpalen nog niet aangesloten.':'Kwaliteitscontroles nog niet aangesloten.').'</p>';
      $html.='</section>';
    }
    return $html.'</div></section>';
  }
  public static function decorate(string $html,string $path,array $query,int $status,string $type):string {
    if($status!==200||!str_starts_with(strtolower($type),'text/html')||!str_starts_with($path,'/office-test')||in_array($path,['/office-test/inloggen','/office-test/uitloggen'],true)||str_contains($path,'/api/')||!preg_match('/<html\b/i',$html))return $html;
    $current=self::current($path);$nav=self::main($current);
    $count=0;$html=preg_replace_callback('~<nav\b[^>]*\baria-label="Hoofdnavigatie"[^>]*>.*?</nav>~si',static fn()=>$nav,$html,1,$count)??$html;
    if($count===0)$html=preg_replace_callback('~<main\b[^>]*>~i',static fn($m)=>$m[0].$nav,$html,1)??$html;
    // Keep page-owned Module tabs. Removing them hides Projecten/Inzet navigation.
    $css='<style>.office-global-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 22px}.office-global-nav a,.office-project-context nav a{display:inline-block;padding:9px 12px;border-radius:7px;text-decoration:none;background:#edf2f7;color:#173c60;font-weight:650}.office-global-nav a[aria-current=page],.office-project-context a[aria-current=page]{background:#175a91;color:white}aside .office-global-nav{display:grid;gap:6px}aside .office-global-nav a{margin:0}.office-project-context{padding:12px 0;border-block:1px solid #dce3eb;margin:16px 0 24px}.office-project-context nav{display:flex;flex-wrap:wrap;gap:7px;margin:10px 0}.office-steering{margin:24px 0}.office-steering-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.office-steering-grid>section{padding:16px;border:1px solid #dce3eb;border-radius:10px;background:#fff;color:#243244}.office-steering-grid h3{margin:0 0 10px}.office-unavailable{color:#627185;font-size:.9rem}</style>';
    $html=preg_replace_callback('~</head>~i',static fn()=>$css.'</head>',$html,1,$styled)??$html;
    if(!$styled)$html=preg_replace_callback('~<main\b[^>]*>~i',static fn($m)=>$css.$m[0],$html,1)??$html;
    $project=self::projectId($path,$query);
    $scope=$project===null?'':self::scope($project,$current);
    $root=in_array($path,['/office-test','/office-test/'],true);
    if($root)$html=str_replace(['Mijn werkplek','Goedemorgen, welkom in Office'],['Bedrijfsoverzicht','Bedrijfsoverzicht'],$html);
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
