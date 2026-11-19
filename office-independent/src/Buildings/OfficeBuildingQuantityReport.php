<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;
use PDO;

/**
 * Read-only quantities report. A report is not a priced calculation, and no
 * baseline is modified. Controller must check building-level read access.
 */
final class OfficeBuildingQuantityReport {
  public const LEVELS=['building','location','dwelling_type','floor','address','component'];

  public function __construct(private PDO $db) {}

  public function rows(int $buildingId,string $level): array {
    if ($buildingId<1 || !in_array($level,self::LEVELS,true)) {
      throw new InvalidArgumentException('invalid_report_level');
    }
    // Inventory records represent measured quantities. Do not multiply
    // dwelling-type templates by address count: this would double-count items.
    $dimension=match($level) {
      'building'=>"'Gehele gebouw'",
      'location'=>"COALESCE(d.name,'Niet toegewezen')",
      'dwelling_type'=>"COALESCE(t.type_code,'Niet toegewezen')",
      'floor'=>"CASE WHEN a.primary_floor IS NULL THEN 'Niet toegewezen' ELSE CAST(a.primary_floor AS CHAR) END",
      'address'=>"CASE WHEN a.id IS NULL THEN 'Gemeenschappelijk' ELSE TRIM(CONCAT(a.street,' ',a.house_number,COALESCE(a.house_letter,''),' ',COALESCE(a.house_addition,''))) END",
      'component'=>"COALESCE(NULLIF(i.component_code,''),'Zonder code')"
    };
    $sql="SELECT $dimension AS group_label, i.component_code,i.description,i.unit,
       SUM(i.quantity) AS quantity,COUNT(*) AS source_lines
       FROM office_building_inventory_items i
       LEFT JOIN office_building_demarcations d ON d.id=i.demarcation_id AND d.building_id=i.building_id
       LEFT JOIN office_building_addresses a ON a.id=i.address_id AND a.building_id=i.building_id
       LEFT JOIN office_building_dwelling_types t ON t.id=COALESCE(i.dwelling_type_id,a.dwelling_type_id) AND t.building_id=i.building_id
       WHERE i.building_id=?
       GROUP BY group_label,i.component_code,i.description,i.unit
       ORDER BY group_label,i.component_code,i.description,i.unit";
    $statement=$this->db->prepare($sql);
    $statement->execute([$buildingId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** Excel-compatible UTF-8 CSV; caller owns HTTP headers and authorization. */
  public static function csv(array $rows): string {
    $stream=fopen('php://temp','w+');
    if ($stream===false) throw new \RuntimeException('csv_stream_failed');
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,['Groep','Bouwdeelcode','Omschrijving','Hoeveelheid','Eenheid','Bronregels'],';');
    foreach ($rows as $row) {
      $cells=[];
      foreach (['group_label','component_code','description','quantity','unit','source_lines'] as $key) {
        $value=(string)($row[$key]??'');
        // Avoid spreadsheet formula execution when exported data is opened.
        if (preg_match('/^[\s\x00-\x1F]*[=+@-]/u',$value) && !in_array($key,['quantity','source_lines'],true)) {
          $value="'".$value;
        }
        $cells[]=$value;
      }
      fputcsv($stream,$cells,';');
    }
    rewind($stream);
    $csv=stream_get_contents($stream);
    fclose($stream);
    if ($csv===false) throw new \RuntimeException('csv_read_failed');
    return $csv;
  }
}
