<?php
declare(strict_types=1);
namespace Brebo\Office\Project\Workspace;
require_once __DIR__.'/View.php';
require_once __DIR__.'/TaskViews.php';
final class Integration {
  public static function augment(\PDO $db,object $operator,int $project,array $sections):array{
    if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_PROJECT_WORKSPACE_ENABLED')!=='enabled')return $sections;
    (new Controls($db,(string)$operator->operatorId))->authorize($project);
    try{
      $today=(new \DateTimeImmutable('now',new \DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');$r=(new Sources($db,(string)$operator->operatorId))->read($project,$today);$token='';if($r['can_manage'])$token=Controls::token((string)$operator->sessionId,(string)getenv('OFFICE_PROJECT_WORKSPACE_CSRF_SECRET'));
      $overview=View::overview($r);$overview=str_replace('</div><div class="pw-grid">','</div>'.TaskViews::signals($r).'<div class="pw-grid">',$overview);
      $sections['overzicht']=View::styles().TaskViews::styles().$overview;
      $sections['taken']=View::styles().TaskViews::render($r,$token,$_GET);
      $sections['arbeid']=View::styles().TaskViews::labor($r);
      $sections['werkvoorbereiding']=View::styles().View::preparation($r,$token).($sections['werkvoorbereiding']??'');
      $old=$sections['uitvoering']??'';if($old==='<section><h2>Uitvoering</h2><p>Uitvoeringsgegevens worden vanuit de gekoppelde projectmodules ontsloten.</p></section>')$old='';$sections['uitvoering']=View::styles().View::execution($r,$token).$old;
      $sections['financieel']=View::styles().View::financial($r,$token).($sections['financieel']??'');return $sections;
    }catch(\Throwable $e){error_log('Office project workspace unavailable: '.get_class($e));$sections['overzicht']='<section role="status"><h2>Projectgegevens tijdelijk niet beschikbaar</h2><p>Het projectdossier kan de aanvullende bronnen nu niet ophalen. De bestaande projectonderdelen blijven bereikbaar.</p></section>'.($sections['overzicht']??'');return $sections;}
  }
}
