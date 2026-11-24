<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$dsn=getenv('OFFICE_INTAKE_PDO_DSN')?:'';
if(!preg_match('/(?:^|;)dbname=brebo_office_test(?:;|$)/',substr($dsn,6))||!str_starts_with($dsn,'mysql:')){echo "BLOCK_DATABASE_SCOPE\n";exit(1);}
try{
 $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 if($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test')exit(1);
 $required=[
 'office_calc_crm_draft_collaborators'=>['draft_id','operator_id','granted_by'],
 'office_calc_crm_draft_changes'=>['id','draft_id','actor_id','event_kind','reason','before_json','after_json','content_hash','created_at'],
 'calculation_lines'=>['calc_net_quantity','calc_material_loss_percent','calc_extra_labor_hours','calc_quantity_source','calc_quantity_source_ref'],
 'office_calc_crm_drafts'=>['id','created_by','opportunity_id','calculation_id','crm_revision','project_id']
 ];
 $ok=true;
 $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 foreach($required as $table=>$columns){
  $q->execute([$table]);$missing=array_diff($columns,$q->fetchAll(PDO::FETCH_COLUMN));
  echo $table.'='.($missing?'MISSING:'.implode(',',$missing):'OK')."\n";
  if($missing)$ok=false;
 }
 echo 'CALC_COLLABORATION_PREFLIGHT='.($ok?'COLUMNS_PRESENT':'BLOCKED')."\n";
 exit($ok?0:1);
}catch(Throwable){echo "BLOCK_DATABASE\n";exit(1);}
