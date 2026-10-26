<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;

require_once __DIR__.'/OfficeInzetOperationalReviewCsrf.php';

final class OfficeInzetOperationalReviewWorkbench {
  public static function render(array $report,WebsiteCrmOperatorContext $operator,?string $secret=null,?string $saved=null): string {
    $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $hours=static fn(int $v):string=>($v<0?'-':'').intdiv(abs($v),60).':'.str_pad((string)(abs($v)%60),2,'0',STR_PAD_LEFT);
    $states=['submitted'=>'Ingediend','approved'=>'Geaccordeerd','rejected'=>'Afgewezen','needs_correction'=>'Correctie gevraagd'];
    $html='<h1>Uren beoordelen</h1><p>Beoordeel ingediende uren voor de projecten die je beheert.</p>';
    if ($secret===null) $html.='<p role="status">Je kunt de beoordelingen bekijken. Opslaan is nog niet ingeschakeld.</p>';
    $html.='<form method="get"><label for="project">Project</label><select name="project" id="project"><option value="">Alle beheerprojecten</option>';
    foreach ($report['projects'] as $id=>$title) $html.='<option value="'.(int)$id.'"'.($report['project']===$id?' selected':'').'>'.$e($title).'</option>';
    $html.='</select><button type="submit">Tonen</button></form>';
    if ($report['rows']===[]) $html.='<p>Geen ingediende registraties gevonden op deze pagina.</p>';
    foreach ($report['rows'] as $row) {
      $id=(string)$row['id'];$anchor='registration-'.substr(hash('sha256',$id),0,16);
      $html.='<article class="registration" id="'.$anchor.'"><div class="heading"><h2>'.$e($row['person_name']).' · '.$e($row['work_date']).'</h2>'
        .'<span class="state">'.$e($row['verified']?($states[$row['state']]??'Controle nodig'):'Controle nodig').'</span></div>'
        .'<p>'.$e($row['project_name']).'</p><p class="description">'.$e($row['work_description']).'</p>'
        .'<dl><div><dt>Gepland</dt><dd>'.$hours((int)$row['planned_minutes']).'</dd></div>'
        .'<div><dt>Geregistreerd</dt><dd>'.$hours((int)$row['registered_minutes']).'</dd></div>'
        .'<div><dt>Afwijking</dt><dd>'.$hours((int)$row['registered_minutes']-(int)$row['planned_minutes']).'</dd></div></dl>';
      if ($saved===$id && $row['verified'] && $row['state']!=='submitted') $html.='<p role="status">De beoordeling is opgeslagen.</p>';
      if (($row['decision_reason']??'')!=='') $html.='<p><strong>Toelichting:</strong> '.$e($row['decision_reason']).'</p>';
      if ($row['can_review'] && $secret!==null) {
        $token=OfficeInzetOperationalReviewCsrf::token($operator,$id,(int)$row['revision'],$row['snapshot'],$secret);
        $html.='<form method="post" action=""><input type="hidden" name="id" value="'.$e($id).'">'
          .'<input type="hidden" name="revision" value="'.(int)$row['revision'].'">'
          .'<input type="hidden" name="snapshot" value="'.$e($row['snapshot']).'">'
          .'<input type="hidden" name="csrf" value="'.$e($token).'">'
          .'<label for="note-'.$anchor.'">Toelichting</label><textarea id="note-'.$anchor.'" name="reason" maxlength="2000" rows="2"></textarea>'
          .'<small>Verplicht bij afwijzen, correctie vragen of een urenafwijking.</small><div class="actions">'
          .'<button class="approve" name="decision" value="approved" type="submit">Accorderen</button>'
          .'<button name="decision" value="rejected" type="submit">Afwijzen</button>'
          .'<button name="decision" value="needs_correction" type="submit">Correctie vragen</button></div></form>';
      } elseif ($row['blocked']!=='') $html.='<p>'.$e($row['blocked']).'</p>';
      $html.='</article>';
    }
    $params=$report['project']===null?[]:['project'=>$report['project']];$html.='<nav aria-label="Paginering">';
    if ($report['page']>1) $html.='<a href="?'.$e(http_build_query($params+['page'=>$report['page']-1])).'">← Vorige pagina</a> ';
    if ($report['has_more']) $html.='<a href="?'.$e(http_build_query($params+['page'=>$report['page']+1])).'">Volgende pagina →</a>';
    return $html.'</nav><p class="footer-note">Accorderen legt de urenbeoordeling vast. Facturatie en financiële verwerking zijn afzonderlijke stappen.</p>';
  }
}
