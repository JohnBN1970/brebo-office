<?php
declare(strict_types=1);

/** Standalone, CLI-only Office-test review preflight. Never initializes/migrates a DB. */
final class OfficeInzetReviewPreflight {
  private static function inside(string $path, string $directory): bool {
    $prefix = rtrim(str_replace('\\','/',$directory),'/') . '/';
    $path = str_replace('\\','/',$path);
    if (PHP_OS_FAMILY === 'Windows') return str_starts_with(strtolower($path),strtolower($prefix));
    return str_starts_with($path,$prefix);
  }

  private static function privateFile(string $path, string $root, string $web): string {
    $real = realpath($path);
    if ($real === false || !is_file($real) || !is_readable($real)
      || !self::inside($real,$root) || self::inside($real,$web)) {
      throw new RuntimeException('PRIVATE_FILE_REQUIRED');
    }
    return $real;
  }

  private static function database(string $path): PDO {
    // Refuse WAL/hot-journal databases rather than risk sidecar creation or
    // inspect a stale main-file snapshot. Checkpointing is an operator action.
    $handle = fopen($path,'rb');
    if ($handle === false) throw new RuntimeException('DATABASE_UNREADABLE');
    try { $header = fread($handle,100); } finally { fclose($handle); }
    if (!is_string($header) || strlen($header)<100 || substr($header,0,16)!=="SQLite format 3\0") {
      throw new RuntimeException('SQLITE_FILE_REQUIRED');
    }
    if (ord($header[18])===2 || ord($header[19])===2
      || (is_file($path.'-wal') && filesize($path.'-wal')>0)
      || (is_file($path.'-journal') && filesize($path.'-journal')>0)) {
      throw new RuntimeException('OFFLINE_SNAPSHOT_REQUIRED');
    }
    $uri = str_replace(['%2F','%3A','%5C'],['/',':','/'],rawurlencode($path));
    $db = new PDO('sqlite:file:'.$uri.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA query_only=ON');
    $db->beginTransaction();
    if ($db->query('PRAGMA quick_check')->fetchColumn()!=='ok') {
      throw new RuntimeException('DATABASE_INTEGRITY_FAILED');
    }
    return $db;
  }

  /** @return array<string,array<string,mixed>> */
  private static function columns(PDO $db, string $table, array $required): array {
    $columns=[];
    foreach ($db->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
      $columns[$column['name']]=$column;
    }
    foreach ($required as $name) {
      if (!isset($columns[$name])) throw new RuntimeException('SCHEMA_COLUMNS_MISSING');
    }
    return $columns;
  }

  /** Inspect only known identity columns; never emit row values or schema SQL. */
  private static function identityEvidence(PDO $db, string $table, string $key, array $columns): array {
    $object=$db->prepare('SELECT type FROM sqlite_master WHERE name=?');
    $object->execute([$table]);
    $primary=array_filter($columns,static fn(array $column): bool => (int)$column['pk']>0);
    $singlePrimary=count($primary)===1 && (int)$columns[$key]['pk']===1;
    $singleUnique=false;
    foreach ($db->query('PRAGMA index_list('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
      if ((int)$index['unique']!==1 || (int)($index['partial'] ?? 0)!==0) continue;
      $quoted='"'.str_replace('"','""',$index['name']).'"';
      $indexed=$db->query('PRAGMA index_info('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC);
      if (count($indexed)===1 && $indexed[0]['name']===$key) $singleUnique=true;
    }
    return [
      'object_kind'=>$object->fetchColumn() ?: 'unknown',
      'key_column'=>$key,
      'declared_type'=>strtoupper($columns[$key]['type']),
      'primary_key_position'=>(int)$columns[$key]['pk'],
      'primary_key_column_count'=>count($primary),
      'single_column_primary_key'=>$singlePrimary,
      'single_column_unique_index'=>$singleUnique,
      'row_count'=>(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(),
      'null_keys'=>(int)$db->query('SELECT COUNT(*) FROM '.$table.' WHERE '.$key.' IS NULL')->fetchColumn(),
      'duplicate_key_groups'=>(int)$db->query('SELECT COUNT(*) FROM (SELECT '.$key.' FROM '.$table.' WHERE '.$key.' IS NOT NULL GROUP BY '.$key.' HAVING COUNT(*)>1)')->fetchColumn(),
    ];
  }

  /** Only aggregate evidence is returned: never labels, notes, IDs or secrets. */
  public static function inspect(array $config, string $privateRoot, string $documentRoot): array {
    $result=['read_only'=>true,'source'=>'NOT_CHECKED','decisions'=>'NOT_CHECKED'];
    try {
      $root=realpath($privateRoot); $web=realpath($documentRoot);
      if ($root===false || $web===false || !is_dir($root) || !is_dir($web)
        || $root===$web || self::inside($root,$web)
        || ($config['OFFICE_BASE_PATH'] ?? null)!=='/office-test') {
        throw new RuntimeException('OFFICE_TEST_PRIVATE_BOUNDARY_REQUIRED');
      }
      $sourcePath=$config['OFFICE_TEST_INZET_SQLITE_PATH'] ?? null;
      if (!is_string($sourcePath) || $sourcePath==='') throw new RuntimeException('SOURCE_NOT_CONFIGURED');
      $sourcePath=self::privateFile($sourcePath,$root,$web);
      $source=self::database($sourcePath);
      $issues=self::columns($source,'review_issues',['id','sheet_id','source_row_id','details','issue_code']);
      $rows=self::columns($source,'inzet_source_workbench',['source_row_id','row_number','person_label','project_label']);
      $sheets=self::columns($source,'worksheets',['id','sheet_name']);
      $result['source_identity']=[
        'review_issues'=>self::identityEvidence($source,'review_issues','id',$issues),
        'inzet_source_workbench'=>self::identityEvidence($source,'inzet_source_workbench','source_row_id',$rows),
        'worksheets'=>self::identityEvidence($source,'worksheets','id',$sheets),
      ];
      $sourceReady=true;
      foreach ($result['source_identity'] as $identity) {
        if (!$identity['single_column_primary_key'] || $identity['null_keys']!==0 || $identity['duplicate_key_groups']!==0) $sourceReady=false;
      }
      $result['source']=$sourceReady ? 'READY' : 'IDENTITY_KEY_REVIEW_REQUIRED';
      if (!$sourceReady) $result['reason']='SOURCE_IDENTITY_KEY_REVIEW_REQUIRED';
      $result['source_rows']=(int)$source->query('SELECT COUNT(*) FROM inzet_source_workbench')->fetchColumn();
      $result['source_issues']=(int)$source->query('SELECT COUNT(*) FROM review_issues')->fetchColumn();
      $result['issues_without_source_row']=(int)$source->query('SELECT COUNT(*) FROM review_issues i LEFT JOIN inzet_source_workbench s ON s.source_row_id=i.source_row_id WHERE s.source_row_id IS NULL')->fetchColumn();
      $reviewer=$config['OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'] ?? null;
      $result['reviewer_configured']=is_string($reviewer) && $reviewer!=='' && strlen($reviewer)<=128;
      $path=$config['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'] ?? null;
      if (!is_string($path) || $path==='') {
        $result['decisions']='NOT_CONFIGURED';
        return $result+['status'=>$sourceReady ? 'REVIEW_REQUIRED' : 'BLOCKED'];
      }
      $path=self::privateFile($path,$root,$web);
      if ($path===$sourcePath || (stat($path)['ino']===stat($sourcePath)['ino'] && stat($path)['dev']===stat($sourcePath)['dev'])) {
        throw new RuntimeException('SOURCE_DECISION_ALIAS_REJECTED');
      }
      $db=self::database($path);
      $current=self::columns($db,'inzet_review_decisions',['issue_id','action','target_id','note','reviewer_id','revision','updated_at']);
      $audit=self::columns($db,'inzet_review_decision_audit',['id','issue_id','action','target_id','note','reviewer_id','revision','recorded_at']);
      if ((int)$current['issue_id']['pk']!==1 || (int)$audit['id']['pk']!==1) {
        throw new RuntimeException('DECISION_IDENTITY_KEY_REVIEW_REQUIRED');
      }
      $result['decisions_count']=(int)$db->query('SELECT COUNT(*) FROM inzet_review_decisions')->fetchColumn();
      $result['audit_count']=(int)$db->query('SELECT COUNT(*) FROM inzet_review_decision_audit')->fetchColumn();
      $result['reviewer_column_types']=[strtoupper($current['reviewer_id']['type']),strtoupper($audit['reviewer_id']['type'])];
      $result['audit_mismatches']=(int)$db->query('SELECT COUNT(*) FROM inzet_review_decisions d WHERE d.revision < 1 OR NOT EXISTS (SELECT 1 FROM inzet_review_decision_audit a WHERE a.issue_id=d.issue_id AND a.revision=d.revision AND a.action=d.action AND a.target_id IS d.target_id AND a.note=d.note AND CAST(a.reviewer_id AS TEXT)=CAST(d.reviewer_id AS TEXT) AND a.recorded_at=d.updated_at)')->fetchColumn();
      $result['duplicate_audit_revisions']=(int)$db->query('SELECT COUNT(*) FROM (SELECT issue_id,revision FROM inzet_review_decision_audit GROUP BY issue_id,revision HAVING COUNT(*)>1)')->fetchColumn();
      $result['audit_without_decision']=(int)$db->query('SELECT COUNT(*) FROM inzet_review_decision_audit a LEFT JOIN inzet_review_decisions d ON d.issue_id=a.issue_id WHERE d.issue_id IS NULL OR a.revision>d.revision')->fetchColumn();
      $result['audit_history_gaps']=(int)$db->query('SELECT COUNT(*) FROM inzet_review_decisions d WHERE (SELECT COUNT(DISTINCT a.revision) FROM inzet_review_decision_audit a WHERE a.issue_id=d.issue_id) != d.revision OR (SELECT MIN(a.revision) FROM inzet_review_decision_audit a WHERE a.issue_id=d.issue_id) != 1')->fetchColumn();
      $result['unexpected_schema_objects']=(int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type IN ('trigger','view')")->fetchColumn();
      $known=$source->prepare('SELECT 1 FROM review_issues WHERE id=?');
      $orphans=0;
      foreach ($db->query('SELECT issue_id FROM inzet_review_decisions')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $known->execute([$id]); if ($known->fetchColumn()===false) $orphans++;
      }
      $result['decisions_without_source_issue']=$orphans;
      $result['decisions']=$result['reviewer_column_types']===['TEXT','TEXT'] ? 'TEXT_SCHEMA' : 'SCHEMA_CONVERSION_REVIEW_REQUIRED';
      $ready=$sourceReady && $result['decisions']==='TEXT_SCHEMA' && $result['reviewer_configured']
        && $result['audit_mismatches']===0 && $result['duplicate_audit_revisions']===0
        && $result['audit_history_gaps']===0 && $result['audit_without_decision']===0 && $result['unexpected_schema_objects']===0 && $orphans===0;
      return $result+['status'=>!$sourceReady ? 'BLOCKED' : ($ready ? 'READY_FOR_REVIEW' : 'REVIEW_REQUIRED')];
    } catch (Throwable $error) {
      // Emit a controlled code only. SQLite errors can contain private paths/data.
      $allowed=['PRIVATE_FILE_REQUIRED','DATABASE_UNREADABLE','SQLITE_FILE_REQUIRED','OFFLINE_SNAPSHOT_REQUIRED','DATABASE_INTEGRITY_FAILED','SCHEMA_COLUMNS_MISSING','OFFICE_TEST_PRIVATE_BOUNDARY_REQUIRED','SOURCE_NOT_CONFIGURED','SOURCE_IDENTITY_KEY_REVIEW_REQUIRED','SOURCE_DECISION_ALIAS_REJECTED','DECISION_IDENTITY_KEY_REVIEW_REQUIRED'];
      $code=in_array($error->getMessage(),$allowed,true) ? $error->getMessage() : 'SCHEMA_INSPECTION_FAILED';
      return array_replace($result,['status'=>'BLOCKED','reason'=>$code]);
    }
  }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
  if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
  $options=getopt('',['private-root:','document-root:','config:']);
  $root=$options['private-root'] ?? ''; $web=$options['document-root'] ?? '';
  if (!is_string($root) || !is_string($web) || !isset($options['config']) || !is_string($options['config'])) {
    fwrite(STDERR,"Usage: php preflight-inzet-review.php --private-root=... --document-root=... --config=...\n"); exit(1);
  }
  // Load only the existing trusted private runtime config, as the test router does.
  $configPath=realpath($options['config']); $private=realpath($root); $public=realpath($web);
  $prefix=$private===false ? '' : rtrim(str_replace('\\','/',$private),'/').'/';
  $normalized=$configPath===false ? '' : str_replace('\\','/',$configPath);
  if ($configPath===false || $private===false || $public===false || !is_file($configPath)
    || !str_starts_with(strtolower($normalized),strtolower($prefix))
    || str_starts_with(strtolower($normalized),strtolower(rtrim(str_replace('\\','/',$public),'/').'/'))) {
    fwrite(STDERR,"Private test config required.\n"); exit(1);
  }
  ob_start();
  try { $config=require $configPath; } catch (Throwable) { $config=null; }
  ob_end_clean();
  if (!is_array($config)) { fwrite(STDERR,"Private test config invalid.\n"); exit(1); }
  $result=OfficeInzetReviewPreflight::inspect($config,$root,$web);
  echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
  exit($result['status']==='READY_FOR_REVIEW' ? 0 : 2);
}
