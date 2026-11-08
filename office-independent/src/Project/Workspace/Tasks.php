<?php
declare(strict_types=1);
namespace Brebo\Office\Project\Workspace;
/** Native Office task model. Drupal project_nid is deliberately never treated as an Office project ID. */
trait Tasks {
  public function tasks(int $project):array {
    $this->authorize($project);
    $q=$this->db->prepare('SELECT * FROM office_project_tasks WHERE project_id=? ORDER BY due_date,id');$q->execute([$project]);$tasks=$q->fetchAll(\PDO::FETCH_ASSOC);
    $q=$this->db->prepare('SELECT task_id,predecessor_id FROM office_project_task_dependencies WHERE project_id=? ORDER BY predecessor_id');$q->execute([$project]);$deps=[];foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $d)$deps[(int)$d['task_id']][]=(int)$d['predecessor_id'];
    foreach($tasks as &$t)$t['predecessors']=$deps[(int)$t['id']]??[];unset($t);return $tasks;
  }
  public function taskSave(int $project,int $id,int $revision,array $input,string $today):int {
    self::date($today);$title=self::text($input['title']??null,200);$description=self::text($input['description']??'',5000,true);
    $start=self::date($input['starts_on']??null);$due=self::date($input['due_date']??null);
    if($start<'2000-01-01'||$due>'2100-12-31'||$start>$due)throw new \InvalidArgumentException('Kies een geldige periode tussen 2000 en 2100; einde ligt op of na de start.');
    $status=$input['status']??'';if(!in_array($status,['planned','active','blocked','done'],true))throw new \InvalidArgumentException('Kies een taakstatus.');
    $reason=self::text($input['reason']??'',1000,true);if($status==='blocked'&&$reason==='')throw new \InvalidArgumentException('Beschrijf de blokkade.');
    $person=self::number($input['assignee_person_id']??0);$minutes=self::number($input['estimated_minutes']??0,0,10000000);
    $progress=self::number($input['progress_percent']??0,0,100);if($status==='done')$progress=100;elseif($progress===100)throw new \InvalidArgumentException('100% hoort bij de status Gereed.');
    $raw=$input['predecessors']??[];if(!is_array($raw)||count($raw)>100)throw new \InvalidArgumentException('Ongeldige afhankelijkheden.');$deps=[];foreach($raw as $v)$deps[]=self::number($v,1);$deps=array_values(array_unique($deps));sort($deps);
    $key=self::text($input['command_key']??null,64);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new \InvalidArgumentException('Ongeldige opdracht.');
    return $this->transaction($project,function()use($project,$id,$revision,$title,$description,$start,$due,$status,$reason,$person,$minutes,$progress,$deps,$key,$today){
      $all=[];foreach($this->tasks($project) as $t)$all[(int)$t['id']]=$t;$old=$id?($all[$id]??null):null;
      if(($id&&(!$old||(int)$old['revision']!==$revision))||(!$id&&$revision!==0))throw new \DomainException('Taak gewijzigd. Laad de actuele taak opnieuw.');
      if($person){$q=$this->db->prepare('SELECT 1 FROM office_inzet_project_team t JOIN office_inzet_people p ON p.id=t.person_id WHERE t.project_id=? AND t.person_id=? AND p.active=1');$q->execute([$project,$person]);if(!$q->fetchColumn())throw new \DomainException('Delegeer uitsluitend aan een actief lid van dit projectteam.');}
      if($old&&(int)($old['assignee_person_id']??0)!==$person&&$reason==='')throw new \InvalidArgumentException('Vul de reden voor overdracht of delegatie in.');
      foreach($deps as $dep){if(!isset($all[$dep])||$dep===$id)throw new \DomainException('Afhankelijkheden moeten andere taken binnen dit project zijn.');if(in_array($status,['active','done'],true)&&$all[$dep]['status']!=='done')throw new \DomainException('Rond eerst de voorgangers af.');if($all[$dep]['due_date']>$start)throw new \DomainException('Een voorganger eindigt na de geplande start van deze taak.');}
      $target=$id?:-1;$graph=[];foreach($all as $tid=>$task)$graph[$tid]=$task['predecessors'];$graph[$target]=$deps;$visiting=[];$visited=[];
      $visit=function(int $node)use(&$visit,&$visiting,&$visited,$graph){if(isset($visiting[$node]))throw new \DomainException('De afhankelijkheden vormen een cirkel.');if(isset($visited[$node]))return;$visiting[$node]=true;foreach($graph[$node]??[] as $p)$visit($p);unset($visiting[$node]);$visited[$node]=true;};foreach(array_keys($graph) as $node)$visit($node);
      foreach($all as $tid=>$task)if($tid!==$id&&in_array($id,$task['predecessors'],true)){if($due>$task['starts_on'])throw new \DomainException('Deze einddatum valt na de start van een opvolger.');if($status!=='done'&&in_array($task['status'],['active','done'],true))throw new \DomainException('Zet eerst actieve of afgeronde opvolgers terug voordat u deze voorganger heropent.');}
      $completed=$status==='done'?($old['completed_on']??$today):null;$now=gmdate('c');$values=[$title,$description,$start,$due,$status,$person?:null,$minutes,$progress,$reason,$completed,$this->actor,$now];
      if($id){$this->db->prepare('UPDATE office_project_tasks SET title=?,description=?,starts_on=?,due_date=?,status=?,assignee_person_id=?,estimated_minutes=?,progress_percent=?,reason=?,completed_on=?,operator_id=?,updated_at=?,revision=revision+1 WHERE project_id=? AND id=? AND revision=?')->execute([...$values,$project,$id,$revision]);}
      else{$this->db->prepare('INSERT INTO office_project_tasks(title,description,starts_on,due_date,status,assignee_person_id,estimated_minutes,progress_percent,reason,completed_on,operator_id,updated_at,project_id,baseline_start,baseline_end,command_key,revision) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)')->execute([...$values,$project,$start,$due,$key]);$id=(int)$this->db->lastInsertId();}
      $this->db->prepare('DELETE FROM office_project_task_dependencies WHERE project_id=? AND task_id=?')->execute([$project,$id]);foreach($deps as $dep)$this->db->prepare('INSERT INTO office_project_task_dependencies(project_id,task_id,predecessor_id) VALUES(?,?,?)')->execute([$project,$id,$dep]);
      $this->audit($project,'task_saved',$id,['before'=>$old,'title'=>$title,'status'=>$status,'assignee_person_id'=>$person?:null,'starts_on'=>$start,'due_date'=>$due,'progress_percent'=>$progress,'estimated_minutes'=>$minutes,'reason'=>$reason,'predecessors'=>$deps]);return $id;
    });
  }
}
