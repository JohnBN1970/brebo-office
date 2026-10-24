<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/** Existing private files only. No initialization, schema conversion or writes. */
final class OfficeInzetOperationalReviewConfig {
  private static function norm(string $path): string {
    $path=str_replace('\\','/',$path);
    return PHP_OS_FAMILY==='Windows'?strtolower($path):$path;
  }
  private static function inside(string $path,string $root): bool {
    return str_starts_with(self::norm($path),rtrim(self::norm($root),'/').'/');
  }
  public static function privateFile(string $path,string $root,string $web): string {
    $base=realpath($root);$document=realpath($web);$file=realpath($path);
    if ($base===false || $document===false || $file===false || !is_dir($base) || !is_dir($document)
      || self::norm($base)===self::norm($document) || self::inside($base,$document)
      || !self::inside($file,$base) || self::inside($file,$document)
      || !is_file($file) || !is_readable($file) || is_link($path)) {
      throw new RuntimeException('OPERATIONAL_PRIVATE_FILE_REQUIRED');
    }
    return $file;
  }
  public static function identityMap(string $path,string $root,string $web): array {
    require_once __DIR__.'/OfficeInzetIdentityMappingGuard.php';
    $file=self::privateFile($path,$root,$web);
    if (filesize($file)>1048576) throw new RuntimeException('OPERATIONAL_IDENTITY_MAP_INVALID');
    $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['version']??null)!==1 || !is_array($data['mappings']??null)
      || !array_is_list($data['mappings']) || $data['mappings']===[] || count($data['mappings'])>5000) {
      throw new RuntimeException('OPERATIONAL_IDENTITY_MAP_INVALID');
    }
    foreach ($data['mappings'] as $mapping) {
      if (!is_array($mapping) || ($mapping['source_system']??null)!=='office_operator') {
        throw new RuntimeException('OPERATIONAL_IDENTITY_MAP_INVALID');
      }
    }
    $validated=OfficeInzetIdentityMappingGuard::validate($data['mappings']);$map=[];
    if (count($validated)!==count($data['mappings'])) throw new RuntimeException('OPERATIONAL_IDENTITY_MAP_INVALID');
    foreach ($data['mappings'] as $mapping) $map[$mapping['source_person_id']]=$mapping['office_person_id'];
    return $map;
  }
  public static function open(string $path,string $root,string $web,bool $write=false,array $forbidden=[]): PDO {
    $file=self::privateFile($path,$root,$web);$stat=stat($file);
    foreach ($forbidden as $other) {
      if (!is_string($other) || $other==='') continue;
      $real=realpath($other);$otherStat=$real===false?false:stat($real);
      if (($real!==false && self::norm($real)===self::norm($file))
        || ($stat!==false && $otherStat!==false && $stat['ino']>0
          && $stat['dev']===$otherStat['dev'] && $stat['ino']===$otherStat['ino'])) {
        throw new RuntimeException('OPERATIONAL_SOURCE_ALIAS_REFUSED');
      }
    }
    if (is_file($file.'-wal') || is_file($file.'-shm') || (is_file($file.'-journal') && filesize($file.'-journal')>0)) {
      throw new RuntimeException('OPERATIONAL_OFFLINE_SNAPSHOT_REQUIRED');
    }
    // Reject WAL before opening SQLite, which can otherwise create sidecars even on a read-only connection.
    $header=file_get_contents($file,false,null,0,100);
    if (!is_string($header) || strlen($header)<100 || substr($header,0,16)!=="SQLite format 3\0"
      || ord($header[18])!==1 || ord($header[19])!==1) throw new RuntimeException('OPERATIONAL_OFFLINE_SNAPSHOT_REQUIRED');
    if ($write && (!is_writable($file) || !is_writable(dirname($file)))) throw new RuntimeException('OPERATIONAL_WRITE_UNAVAILABLE');
    $uri=str_replace(['%2F','%3A','%5C'],['/',':','/'],rawurlencode($file));
    $db=new PDO('sqlite:file:'.$uri.'?mode='.($write?'rw':'ro'),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>2]);
    if (!$write) $db->exec('PRAGMA query_only=ON');
    self::assertSchema($db);
    return $db;
  }
  public static function assertSchema(PDO $db): void {
    $expected=[
      'inzet_planning'=>['id'=>'TEXT','project_id'=>'TEXT','person_id'=>'TEXT','work_date'=>'TEXT','planned_minutes'=>'INTEGER','revision'=>'INTEGER','updated_at'=>'TEXT'],
      'inzet_registration'=>['id'=>'TEXT','planning_id'=>'TEXT','registered_minutes'=>'INTEGER','work_description'=>'TEXT','state'=>'TEXT','revision'=>'INTEGER','updated_at'=>'TEXT'],
      'inzet_operational_audit'=>['id'=>'INTEGER','entity_type'=>'TEXT','entity_id'=>'TEXT','action'=>'TEXT','actor_id'=>'TEXT','reason'=>'TEXT','created_at'=>'TEXT'],
    ];
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite' || $db->query('PRAGMA journal_mode')->fetchColumn()!=='delete') {
      throw new RuntimeException('OPERATIONAL_SCHEMA_REVIEW_REQUIRED');
    }
    foreach ($db->query("SELECT name,type,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_autoindex_%'")->fetchAll(PDO::FETCH_ASSOC) as $object) {
      if ($object['type']!=='table' || (!isset($expected[$object['name']]) && $object['name']!=='sqlite_sequence')) {
        throw new RuntimeException('OPERATIONAL_SCHEMA_REVIEW_REQUIRED');
      }
    }
    foreach ($expected as $table=>$columns) {
      $actual=[];
      foreach ($db->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        if ((int)$column['pk']!==($column['name']==='id'?1:0)) throw new RuntimeException('OPERATIONAL_SCHEMA_REVIEW_REQUIRED');
        $actual[$column['name']]=strtoupper($column['type']);
      }
      if ($actual!==$columns) throw new RuntimeException('OPERATIONAL_SCHEMA_REVIEW_REQUIRED');
    }
    $fk=$db->query('PRAGMA foreign_key_list(inzet_registration)')->fetchAll(PDO::FETCH_ASSOC);
    if (count($fk)!==1 || $fk[0]['table']!=='inzet_planning' || $fk[0]['from']!=='planning_id' || $fk[0]['to']!=='id'
      || $db->query('PRAGMA foreign_key_check')->fetch()!==false) throw new RuntimeException('OPERATIONAL_SCHEMA_REVIEW_REQUIRED');
  }
}
