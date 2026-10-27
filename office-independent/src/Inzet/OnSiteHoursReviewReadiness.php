<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
/** Inspect the exact canonical ledger shape; never run DDL or repair drift. */
final class OnSiteHoursReviewReadiness {
  public static function requireReady(PDO $db): void {
    $required=['id','person_id','project_id','zone_id','in_event_id','out_event_id','approval_status','reviewed_seconds','reviewer_operator_id','approved_at','reviewed_at','reason','revision','request_key','request_fingerprint'];
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new \RuntimeException('hours_review_mysql_required');
    $q=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_reviews'");
    $columns=$q->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
    if(array_diff($required,array_keys($columns))||($columns['reviewer_operator_id']['COLUMN_TYPE']??'')!=='varchar(128)'||($columns['request_key']['COLUMN_TYPE']??'')!=='char(64)') throw new \RuntimeException('hours_review_schema_not_ready');
    $engine=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_reviews'")->fetchColumn();
    if ($engine!=='InnoDB') throw new \RuntimeException('hours_review_schema_not_ready');
    $indexes=$db->query("SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols,NON_UNIQUE,SUM(SUB_PART IS NOT NULL) AS prefixes FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_reviews' GROUP BY INDEX_NAME,NON_UNIQUE")->fetchAll(PDO::FETCH_ASSOC);
    foreach(['in_event_id','out_event_id','request_key'] as $column) {
      $found=false;
      foreach($indexes as $index) if($index['cols']===$column&&(int)$index['NON_UNIQUE']===0&&(int)$index['prefixes']===0) $found=true;
      if(!$found) throw new \RuntimeException('hours_review_schema_not_ready');
    }
    $keys=$db->query("SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_reviews' AND REFERENCED_TABLE_SCHEMA=DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
    foreach(['person_id'=>['office_inzet_people','id'],'in_event_id'=>['office_onsite_event_inbox','event_id'],'out_event_id'=>['office_onsite_event_inbox','event_id']] as $column=>$target) {
      $found=false; foreach($keys as $key) if($key['COLUMN_NAME']===$column&&$key['REFERENCED_TABLE_NAME']===$target[0]&&$key['REFERENCED_COLUMN_NAME']===$target[1])$found=true;
      if(!$found) throw new \RuntimeException('hours_review_schema_not_ready');
    }
  }
}
