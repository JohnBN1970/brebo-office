<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use PDO;
use RuntimeException;

/** Read-only schema inspection; never creates tables, seeds data or prints credentials. */
final class OfficeBuildingZoneReadiness {
  /** @return array<string,bool> */
  public static function inspect(PDO $db): array {
    $tables=['office_projects','office_operator_accounts','office_project_memberships',
      'office_buildings','office_project_buildings','office_building_zones'];
    $q=$db->prepare('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?,?,?,?,?)');
    $q->execute($tables);
    $engines=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $report=[];
    foreach ($tables as $table) $report['InnoDB '.$table]=($engines[$table] ?? '')==='InnoDB';
    $columns=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_building_zones'")->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
    foreach (['request_key','request_fingerprint'] as $column) {
      $c=$columns[$column] ?? [];
      $report['nullable ascii request column '.$column]=($c['COLUMN_TYPE'] ?? '')==='char(64)'
        && ($c['IS_NULLABLE'] ?? '')==='YES' && ($c['COLLATION_NAME'] ?? '')==='ascii_bin';
    }
    $report['database issued zone identity']=preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/D', $columns['id']['COLUMN_TYPE'] ?? '')===1
      && str_contains($columns['id']['EXTRA'] ?? '', 'auto_increment');
    $indexes=$db->query("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols,
      SUM(SUB_PART IS NOT NULL) AS prefixes
      FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_building_zones'
      GROUP BY INDEX_NAME,NON_UNIQUE")->fetchAll(PDO::FETCH_ASSOC);
    $report['unique request key']=false;
    foreach ($indexes as $index) {
      if ((int)$index['NON_UNIQUE']===0 && $index['cols']==='request_key'
        && (int)$index['prefixes']===0) $report['unique request key']=true;
    }
    $keys=$db->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
      FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME IN ('office_building_zones','office_project_buildings')
        AND REFERENCED_TABLE_SCHEMA=DATABASE()
        AND REFERENCED_TABLE_NAME='office_buildings' AND REFERENCED_COLUMN_NAME='id'")->fetchAll(PDO::FETCH_ASSOC);
    foreach (['office_building_zones','office_project_buildings'] as $table) {
      $report['building foreign key '.$table]=false;
      foreach ($keys as $key) {
        if ($key['TABLE_NAME']===$table && $key['COLUMN_NAME']==='building_id') $report['building foreign key '.$table]=true;
      }
    }
    try {
      $db->query("SELECT z.id,z.building_id,z.zone_name,z.latitude,z.longitude,z.radius_metres,z.active,z.revision,
        z.request_key,z.request_fingerprint,b.building_name,b.street_address,b.postal_code,b.city,a.active,m.permission,p.id
        FROM office_building_zones z JOIN office_buildings b ON b.id=z.building_id
        JOIN office_project_buildings pb ON pb.building_id=b.id JOIN office_projects p ON p.id=pb.project_id
        JOIN office_project_memberships m ON m.project_id=p.id JOIN office_operator_accounts a ON a.id=m.operator_id LIMIT 0");
      $report['zone and authorization columns']=true;
    } catch (\Throwable) {$report['zone and authorization columns']=false;}
    return $report;
  }

  public static function requireReady(PDO $db): void {
    if (in_array(false,self::inspect($db),true)) throw new RuntimeException('building_zone_schema_not_ready');
  }
}

