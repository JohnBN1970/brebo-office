<?php
declare(strict_types=1);

require_once __DIR__.'/preflight-inzet-review.php';

/** Creates a new private TEXT-schema copy. Never replaces/activates the original. */
final class OfficeInzetReviewCopyPreparation {
  private const TABLES=[
    'inzet_review_decisions'=>['issue_id','action','target_id','note','reviewer_id','revision','updated_at'],
    'inzet_review_decision_audit'=>['id','issue_id','action','target_id','note','reviewer_id','revision','recorded_at'],
  ];

  private static function openReadOnly(string $path): PDO {
    // Shared read locks keep this snapshot stable during copying.
    $uri=str_replace(['%2F','%3A','%5C'],['/',':','/'],rawurlencode($path));
    $db=new PDO('sqlite:file:'.$uri.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA query_only=ON'); $db->beginTransaction();
    $db->query('SELECT name FROM sqlite_master LIMIT 1')->fetchColumn();
    if ($db->query('PRAGMA journal_mode')->fetchColumn()!=='delete') throw new RuntimeException('OFFLINE_SNAPSHOT_REQUIRED');
    return $db;
  }

  private static function records(PDO $db, string $table, array $columns): array {
    $rows=$db->query('SELECT '.implode(',',$columns).' FROM '.$table.' ORDER BY '.$columns[0])->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      if ((!is_int($row['reviewer_id']) && !is_string($row['reviewer_id']))
        || (string)$row['reviewer_id']==='' || strlen((string)$row['reviewer_id'])>128) throw new RuntimeException('REVIEWER_VALUE_REVIEW_REQUIRED');
      // Preserve the stored historical identity; never guess lost leading zeros.
      $row['reviewer_id']=(string)$row['reviewer_id'];
    }
    unset($row);
    return $rows;
  }

  public static function prepare(array $config, string $privateRoot, string $documentRoot, string $outputName): array {
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,100}\.sqlite$/D',$outputName)) throw new RuntimeException('SAFE_OUTPUT_NAME_REQUIRED');
    $report=OfficeInzetReviewPreflight::inspect($config,$privateRoot,$documentRoot);
    if (($report['source'] ?? null)!=='READY' || !($report['reviewer_configured'] ?? false)
      || !in_array($report['status'],['READY_FOR_REVIEW','REVIEW_REQUIRED'],true)
      || !in_array($report['decisions'] ?? '',['SCHEMA_CONVERSION_REVIEW_REQUIRED','TEXT_SCHEMA'],true)) throw new RuntimeException('PREFLIGHT_REVIEW_REQUIRED');
    foreach (['audit_mismatches','duplicate_audit_revisions','audit_without_decision','audit_history_gaps','unexpected_schema_objects','decisions_without_source_issue'] as $check) {
      if (($report[$check] ?? -1)!==0) throw new RuntimeException('AUDIT_REVIEW_REQUIRED');
    }
    $sourcePath=realpath($config['OFFICE_TEST_INZET_SQLITE_PATH']);
    $originalPath=realpath($config['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH']);
    if ($sourcePath===false || $originalPath===false) throw new RuntimeException('PRIVATE_INPUT_REQUIRED');
    $original=self::openReadOnly($originalPath);
    $source=self::openReadOnly($sourcePath);
    $sourceHash=hash_file('sha256',$sourcePath); $originalHash=hash_file('sha256',$originalPath);
    $tables=$original->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT GLOB 'sqlite_*' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $expected=array_keys(self::TABLES); sort($expected);
    if ($tables!==$expected) throw new RuntimeException('UNKNOWN_TABLE_REVIEW_REQUIRED');
    if ((int)$original->query("SELECT COUNT(*) FROM sqlite_master WHERE type IN ('trigger','view') OR type='index'")->fetchColumn()!==0) throw new RuntimeException('UNKNOWN_SCHEMA_OBJECT_REVIEW_REQUIRED');
    foreach ($original->query("SELECT sql FROM sqlite_master WHERE type='table' AND name NOT GLOB 'sqlite_*'")->fetchAll(PDO::FETCH_COLUMN) as $sql) {
      if (preg_match('/\b(CHECK|REFERENCES|UNIQUE|COLLATE|GENERATED|STRICT)\b/i',$sql)) throw new RuntimeException('UNKNOWN_SCHEMA_OBJECT_REVIEW_REQUIRED');
    }
    $data=[];
    foreach (self::TABLES as $table=>$columns) {
      $actual=array_column($original->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
      $wanted=$columns; sort($actual); sort($wanted);
      if ($actual!==$wanted) throw new RuntimeException('UNKNOWN_COLUMN_REVIEW_REQUIRED');
      $data[$table]=self::records($original,$table,$columns);
    }
    $sequence=(int)$original->query('SELECT COALESCE(MAX(id),0) FROM inzet_review_decision_audit')->fetchColumn();
    if ($original->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'")->fetchColumn()!==false) {
      $stored=$original->query("SELECT seq FROM sqlite_sequence WHERE name='inzet_review_decision_audit'")->fetchColumn();
      if ($stored!==false) {
        if ((int)$stored<$sequence) throw new RuntimeException('AUDIT_SEQUENCE_REVIEW_REQUIRED');
        $sequence=(int)$stored;
      }
    }
    $target=dirname($originalPath).DIRECTORY_SEPARATOR.$outputName;
    // x mode atomically refuses files, symlinks and earlier prepared copies.
    $mask=umask(0077);
    try { $handle=@fopen($target,'x+b'); } finally { umask($mask); }
    if ($handle===false) throw new RuntimeException('OUTPUT_ALREADY_EXISTS_OR_UNWRITABLE');
    $identity=fstat($handle); $complete=false; $copy=null;
    try {
      if (!chmod($target,0600)) throw new RuntimeException('PRIVATE_OUTPUT_MODE_REQUIRED');
      $uri=str_replace(['%2F','%3A','%5C'],['/',':','/'],rawurlencode($target));
      $copy=new PDO('sqlite:file:'.$uri.'?mode=rw',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
      $copy->beginTransaction();
      $copy->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id TEXT NOT NULL,revision INTEGER NOT NULL DEFAULT 1,updated_at TEXT NOT NULL)');
      $copy->exec('CREATE TABLE inzet_review_decision_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER NOT NULL,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id TEXT NOT NULL,revision INTEGER NOT NULL,recorded_at TEXT NOT NULL)');
      foreach (self::TABLES as $table=>$columns) {
        $insert=$copy->prepare('INSERT INTO '.$table.'('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($columns),'?')).')');
        foreach ($data[$table] as $row) $insert->execute(array_values($row));
      }
      $copy->exec("DELETE FROM sqlite_sequence WHERE name='inzet_review_decision_audit'");
      $copy->prepare('INSERT INTO sqlite_sequence(name,seq) VALUES(?,?)')->execute(['inzet_review_decision_audit',$sequence]);
      foreach (self::TABLES as $table=>$columns) {
        if (self::records($copy,$table,$columns)!==$data[$table]) throw new RuntimeException('COPY_VALUE_VERIFICATION_FAILED');
      }
      $copy->commit(); unset($insert,$copy);
      $copyConfig=$config; $copyConfig['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH']=$target;
      $verified=OfficeInzetReviewPreflight::inspect($copyConfig,$privateRoot,$documentRoot);
      if ($verified['status']!=='READY_FOR_REVIEW') throw new RuntimeException('COPY_PREFLIGHT_FAILED');
      if (hash_file('sha256',$originalPath)!==$originalHash || hash_file('sha256',$sourcePath)!==$sourceHash) throw new RuntimeException('INPUT_CHANGED_DURING_COPY');
      $complete=true;
      return ['status'=>'COPY_CREATED_NOT_ACTIVATED','output_file'=>$outputName,'original_unchanged'=>true,'source_unchanged'=>true,'config_unchanged'=>true,'decisions_count'=>$verified['decisions_count'],'audit_count'=>$verified['audit_count'],'reviewer_column_types'=>$verified['reviewer_column_types'],'copy_sha256'=>hash_file('sha256',$target),'issues_without_source_row'=>$verified['issues_without_source_row'],'issues_with_null_source_reference'=>$verified['issues_with_null_source_reference'],'issues_with_unresolved_source_reference'=>$verified['issues_with_unresolved_source_reference']];
    } finally {
      unset($insert,$copy); fclose($handle);
      if (!$complete) {
        clearstatcache(true,$target); $now=@stat($target);
        if (is_array($now) && $now['dev']===$identity['dev'] && $now['ino']===$identity['ino']) unlink($target);
      }
    }
  }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
  if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
  $options=getopt('',['private-root:','document-root:','config:','output-name:','create-copy']);
  if (!isset($options['create-copy'])) { fwrite(STDERR,"Explicit --create-copy is required; original files are never replaced.\n"); exit(1); }
  $root=$options['private-root'] ?? ''; $web=$options['document-root'] ?? ''; $path=$options['config'] ?? ''; $name=$options['output-name'] ?? '';
  if (!is_string($root) || !is_string($web) || !is_string($path) || !is_string($name)) { fwrite(STDERR,"Invalid options.\n"); exit(1); }
  $configPath=realpath($path); $private=realpath($root); $public=realpath($web);
  $normalized=$configPath===false ? '' : str_replace('\\','/',$configPath);
  if ($configPath===false || $private===false || $public===false || !is_file($configPath)
    || !str_starts_with($normalized,rtrim(str_replace('\\','/',$private),'/').'/')
    || str_starts_with($normalized,rtrim(str_replace('\\','/',$public),'/').'/')) {
    fwrite(STDERR,"Private test config required.\n"); exit(1);
  }
  ob_start(); try { $config=require $configPath; } catch (Throwable) { $config=null; } ob_end_clean();
  if (!is_array($config)) { fwrite(STDERR,"Private test config invalid.\n"); exit(1); }
  try {
    $configHash=hash_file('sha256',$configPath);
    $result=OfficeInzetReviewCopyPreparation::prepare($config,$root,$web,$name);
    if (hash_file('sha256',$configPath)!==$configHash) throw new RuntimeException('CONFIG_CHANGED');
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
  } catch (Throwable $error) {
    // Controlled codes only: never expose row values, paths or SQLite errors.
    $allowed=['SAFE_OUTPUT_NAME_REQUIRED','PREFLIGHT_REVIEW_REQUIRED','AUDIT_REVIEW_REQUIRED','PRIVATE_INPUT_REQUIRED','OFFLINE_SNAPSHOT_REQUIRED','UNKNOWN_TABLE_REVIEW_REQUIRED','UNKNOWN_SCHEMA_OBJECT_REVIEW_REQUIRED','UNKNOWN_COLUMN_REVIEW_REQUIRED','REVIEWER_VALUE_REVIEW_REQUIRED','AUDIT_SEQUENCE_REVIEW_REQUIRED','OUTPUT_ALREADY_EXISTS_OR_UNWRITABLE','PRIVATE_OUTPUT_MODE_REQUIRED','COPY_VALUE_VERIFICATION_FAILED','COPY_PREFLIGHT_FAILED','INPUT_CHANGED_DURING_COPY','CONFIG_CHANGED'];
    $reason=in_array($error->getMessage(),$allowed,true) ? $error->getMessage() : 'COPY_INSPECTION_FAILED';
    echo json_encode(['status'=>'COPY_PREPARATION_REFUSED','reason'=>$reason,'activated'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; exit(2);
  }
}
