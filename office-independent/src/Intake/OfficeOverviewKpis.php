<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
use PDO;
use DateTimeImmutable;
use DateTimeZone;
/** Read-only operational KPIs. Null is unknown, never a fabricated zero. */
final class OfficeOverviewKpis {
  public static function hours(PDO $db,string $actor,DateTimeImmutable $now,bool $enabled):array {
    $local=$now->setTimezone(new DateTimeZone('Europe/Amsterdam'));
    $start=$local->setTime(0,0)->modify('-'.((int)$local->format('N')-1).' days');$end=$start->modify('+7 days');
    $result=['pending'=>null,'open'=>null,'approved_seconds'=>null,'period'=>$start->format('d-m').' t/m '.$end->modify('-1 day')->format('d-m'),'scope'=>'Urenbron niet beschikbaar'];
    if(!$enabled)return $result;
    try {
      $q=$db->prepare("SELECT DISTINCT p.id FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE a.id=? AND a.active=1 AND m.permission='manage' ORDER BY p.id LIMIT 101");$q->execute([$actor]);$projects=$q->fetchAll(PDO::FETCH_COLUMN);
      if(!$projects){$result['scope']='Geen toegang tot urenbeoordeling';return $result;}
      if(count($projects)>100)return $result;
      foreach(['OnSiteSessionReviewReader','OnSitePresenceIntervalProjector']as$name)require_once dirname(__DIR__).'/Inzet/'.$name.'.php';
      $reader=new \Brebo\Office\Inzet\OnSiteSessionReviewReader($db);$pending=0;$open=0;
      foreach($projects as$id){$review=$reader->forManager($actor,(string)$id);foreach($review['sessions']as$session)if($session['approval_status']==='pending')$pending++;$open+=count($review['active']);}
      $result['pending']=$pending;$result['open']=$open;$result['scope']='Projecten waarvoor je uren mag beoordelen';
    }catch(\Throwable){return $result;}
    try {
      $q=$db->prepare("SELECT COALESCE(SUM(r.reviewed_seconds),0) FROM office_onsite_hours_reviews r WHERE r.approval_status='approved' AND r.approved_at>=? AND r.approved_at<? AND EXISTS (SELECT 1 FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id WHERE p.id=r.project_id AND m.operator_id=? AND m.permission='manage' AND a.active=1)");
      $q->execute([$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$actor]);$value=$q->fetchColumn();
      if(is_int($value)||(is_string($value)&&ctype_digit($value)))$result['approved_seconds']=(int)$value;
    }catch(\Throwable){/* A failed approval source does not erase valid evidence counts. */}
    return $result;
  }
  public static function cards(PDO $db,string $actor,?int $projects,bool $imports,?int $issues,bool $hoursEnabled,?DateTimeImmutable $now=null):string {
    if($hoursEnabled&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
      try{require_once dirname(__DIR__).'/Inzet/OnSiteHoursReviewReadiness.php';\Brebo\Office\Inzet\OnSiteHoursReviewReadiness::requireReady($db);}catch(\Throwable){$hoursEnabled=false;}
    }
    $hours=self::hours($db,$actor,$now??new DateTimeImmutable('now',new DateTimeZone('UTC')),$hoursEnabled);
    $duration=null;if($hours['approved_seconds']!==null){$minutes=intdiv($hours['approved_seconds'],60);$duration=intdiv($minutes,60).' uur'.($minutes%60?' '.($minutes%60).' min':'');}
    $cards=[
      ['Mijn projecten',$projects===null?null:(string)$projects,'Binnen jouw projecttoegang','/office-test/projecten','Open projecten'],
      ['Te beoordelen sessies',$hours['pending']===null?null:(string)$hours['pending'],'Volledige IN/UIT-paren · '.$hours['scope'],'/office-test/projecten?view=inzet&tab=controle','Uren controleren'],
      ['Open registraties',$hours['open']===null?null:(string)$hours['open'],'IN geregistreerd, nog geen UIT · '.$hours['scope'],'/office-test/projecten?view=inzet&tab=controle','Registraties bekijken'],
      ['Goedgekeurd deze week',$duration,$hours['period'].' · op beoordelingsdatum','/office-test/projecten?view=inzet&tab=controle','Beoordelingen bekijken'],
      ['Importafwijkingen',!$imports||$issues===null?null:(string)$issues,'Afwijkingen in de urenimport · ter controle',$imports?'/office-test/inzet/importcontrole#afwijkingen':null,'Afwijkingen bekijken'],
      ['Projectresultaat',null,'Finance is nog niet aangesloten','/office-test/finance','Finance openen'],
    ];
    $h=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html='';
    foreach($cards as[$title,$value,$description,$url,$label]){$html.='<section class="panel office-kpi"><h2>'.$h($title).'</h2><div class="number">'.($value===null?'—':$h($value)).'</div><p class="muted-text">'.($value===null?'<strong>Niet beschikbaar</strong><br>':'').$h($description).'</p>';if($url!==null)$html.='<a href="'.$h($url).'">'.$h($label).' →</a>';$html.='</section>';}
    return $html;
  }
}
