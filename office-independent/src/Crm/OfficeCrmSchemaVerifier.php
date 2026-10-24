<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;

/**
 * Read-only verification against the versioned Office CRM DDL.
 * No schema mutations and no authorization of CRM writes.
 */
final class OfficeCrmSchemaVerifier {
  /** @return array{ready:bool,issues:list<string>,checked:int} */
  public static function inspect(PDO $db): array {
    $issues = [];
    $checked = 0;
    foreach (OfficeCrmSchema::statements() as $ddl) {
      if (!preg_match('/CREATE TABLE IF NOT EXISTS\s+(office_crm_[a-z_]+)\s*\(/i', $ddl, $match)) {
        $issues[] = 'CRM-DDL: tabelnaam ontbreekt';
        continue;
      }
      $table = $match[1];
      $meta = $db->prepare('SELECT ENGINE, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
      $meta->execute([$table]);
      $info = $meta->fetch(PDO::FETCH_ASSOC);
      if (!$info) {
        $issues[] = "$table: ontbreekt";
        continue;
      }
      $checked++;
      if (strcasecmp((string)$info['ENGINE'], 'InnoDB') !== 0) {
        $issues[] = "$table: engine is niet InnoDB";
      }
      if (strpos((string)$info['TABLE_COLLATION'], 'utf8mb4_') !== 0) {
        $issues[] = "$table: charset is niet utf8mb4";
      }

      $columns = $db->query("SHOW FULL COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
      $actual = [];
      foreach ($columns as $column) {
        $actual[strtolower((string)$column['Field'])] = $column;
      }
      preg_match_all('/^\s*([a-z_]+)\s+(BIGINT UNSIGNED|VARCHAR\(\d+\)|DECIMAL\(\d+,\d+\)|CHAR\(\d+\)|TIMESTAMP|DATE|TINYINT\(1\))\s+(NOT NULL|NULL)([^\n]*)/im', $ddl, $defs, PREG_SET_ORDER);
      $expectedNames = [];
      foreach ($defs as $def) {
        $name = strtolower($def[1]);
        $expectedNames[] = $name;
        if (!isset($actual[$name])) {
          $issues[] = "$table.$name: kolom ontbreekt";
          continue;
        }
        $column = $actual[$name];
        $expectedType = strtolower($def[2]);
        $realType = strtolower((string)$column['Type']);
        $realType = preg_replace('/^bigint\(\d+\) unsigned$/', 'bigint unsigned', $realType);
        $realType = preg_replace('/^tinyint\(\d+\)$/', 'tinyint(1)', $realType);
        if ($realType !== $expectedType) {
          $issues[] = "$table.$name: type $realType in plaats van $expectedType";
        }
        $nullable = strtoupper($def[3]) === 'NULL' ? 'YES' : 'NO';
        if (strtoupper((string)$column['Null']) !== $nullable) {
          $issues[] = "$table.$name: NULL-instelling wijkt af";
        }
        $suffix = $def[4];
        if (preg_match("/DEFAULT\s+'([^']*)'/i", $suffix, $default)) {
          if ((string)$column['Default'] !== $default[1]) {
            $issues[] = "$table.$name: standaardwaarde wijkt af";
          }
        } elseif (preg_match('/DEFAULT\s+CURRENT_TIMESTAMP/i', $suffix)) {
          if (strtolower((string)$column['Default']) !== 'current_timestamp()' && strtolower((string)$column['Default']) !== 'current_timestamp') {
            $issues[] = "$table.$name: timestamp-standaardwaarde wijkt af";
          }
        }
        if (stripos($suffix, 'AUTO_INCREMENT') !== false && stripos((string)$column['Extra'], 'auto_increment') === false) {
          $issues[] = "$table.$name: AUTO_INCREMENT ontbreekt";
        }
        if (stripos($suffix, 'ON UPDATE CURRENT_TIMESTAMP') !== false && stripos((string)$column['Extra'], 'on update current_timestamp') === false) {
          $issues[] = "$table.$name: ON UPDATE ontbreekt";
        }
      }
      foreach (array_diff(array_keys($actual), $expectedNames) as $extra) {
        $issues[] = "$table.$extra: onverwachte kolom";
      }
      $indexRows = $db->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
      $indexes = [];
      foreach ($indexRows as $row) {
        $indexes[(string)$row['Key_name']][(int)$row['Seq_in_index']] = strtolower((string)$row['Column_name']);
      }
      foreach ($indexes as &$cols) {
        ksort($cols);
        $cols = array_values($cols);
      }
      unset($cols);
      $expectedIndexes = [];
      if (preg_match('/\bPRIMARY KEY\s*\(([^)]+)\)/i', $ddl, $pk)) {
        $expectedIndexes['PRIMARY'] = array_map('trim', explode(',', strtolower($pk[1])));
      } elseif (preg_match('/^\s*id\s+BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY/im', $ddl)) {
        $expectedIndexes['PRIMARY'] = ['id'];
      }
      preg_match_all('/\bINDEX\s+(idx_[a-z_]+)\s*\(([^)]+)\)/i', $ddl, $ix, PREG_SET_ORDER);
      foreach ($ix as $entry) {
        $expectedIndexes[$entry[1]] = array_map('trim', explode(',', strtolower($entry[2])));
      }
      foreach ($expectedIndexes as $name => $cols) {
        if (($indexes[$name] ?? null) !== $cols) {
          $issues[] = "$table: index $name wijkt af";
        }
      }
      $fkQuery = $db->prepare('SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL');
      $fkQuery->execute([$table]);
      $actualFk = [];
      foreach ($fkQuery->fetchAll(PDO::FETCH_ASSOC) as $fk) {
        $actualFk[$fk['CONSTRAINT_NAME']] = strtolower($fk['COLUMN_NAME'] . ':' . $fk['REFERENCED_TABLE_NAME'] . ':' . $fk['REFERENCED_COLUMN_NAME']);
      }
      preg_match_all('/CONSTRAINT\s+(fk_[a-z_]+)\s+FOREIGN KEY\s*\(([^)]+)\)\s+REFERENCES\s+([a-z_]+)\s*\(([^)]+)\)/i', $ddl, $fkDefs, PREG_SET_ORDER);
      $expectedFk = [];
      foreach ($fkDefs as $fk) {
        $expectedFk[$fk[1]] = strtolower(trim($fk[2]) . ':' . $fk[3] . ':' . trim($fk[4]));
      }
      if ($actualFk !== $expectedFk) {
        foreach ($expectedFk as $name => $target) {
          if (($actualFk[$name] ?? null) !== $target) {
            $issues[] = "$table: foreign key $name wijkt af";
          }
        }
        foreach (array_diff(array_keys($actualFk), array_keys($expectedFk)) as $name) {
          $issues[] = "$table: onverwachte foreign key $name";
        }
      }
    }
    if ($checked !== 10) {
      $issues[] = "CRM: $checked van 10 tabellen gecontroleerd";
    }
    return ['ready' => $issues === [], 'issues' => $issues, 'checked' => $checked];
  }
}
