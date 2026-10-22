<?php
declare(strict_types=1);

namespace Brebo\Office\Deployment;

use PDO;
use RuntimeException;

/** Explicit CLI migration only. No runtime dependency, DML, config changes or destructive down migration. */
final class OfficeOnSiteTestMigration {
  private const TABLES = ['office_onsite_event_inbox', 'office_onsite_device_tokens'];

  public function __construct(private PDO $db, private string $database = 'u213420663_office_test') {
    if (!in_array($database, ['u213420663_office_test', 'office_onsite_migration_ci'], true)
      || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
      || $db->query('SELECT DATABASE()')->fetchColumn() !== $database) {
      throw new RuntimeException('isolated_database_required');
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
  }

  /** Validate before opening the connection; CLI never accepts a DSN override or CI target. */
  public static function assertTarget(array $c): void {
    $root = '/home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-20261018/private';
    if (($c['OFFICE_BASE_PATH'] ?? null) !== '/office-test'
      || ($c['OFFICE_PUBLIC_HOST'] ?? null) !== 'brebobv.nl'
      || ($c['OFFICE_PRIVATE_ROOT'] ?? null) !== $root
      || ($c['OFFICE_INTAKE_DB_USER'] ?? null) !== 'u213420663_office_test'
      || !is_string($c['OFFICE_INTAKE_PDO_DSN'] ?? null)
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $c['OFFICE_INTAKE_PDO_DSN'])) {
      throw new RuntimeException('isolated_test_configuration_required');
    }
  }

  public function run(string $mode = '--preflight', ?string $confirmation = null): array {
    if (!in_array($mode, ['--preflight', '--apply', '--postflight', '--rollback-plan'], true)) {
      throw new RuntimeException('unknown_mode');
    }
    if ($mode === '--apply' && $confirmation !== '--confirm=office-test-onsite-schema-20261022') {
      throw new RuntimeException('explicit_apply_confirmation_required');
    }
    if ($this->db->inTransaction()) throw new RuntimeException('ddl_inside_transaction_forbidden');
    $lock = $this->db->prepare('SELECT GET_LOCK(?, 0)');
    $name = $this->database . ':onsite-schema-20261022';
    $lock->execute([$name]);
    if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('migration_lock_unavailable');
    try {
      $before = $this->inspect();
      if ($before['blockers'] !== []) return ['status'=>'BLOCK'] + $before;
      if ($mode === '--rollback-plan') {
        return ['status'=>'RETAIN_TABLES_AND_DATA', 'procedure'=>'Stop consumers manually; restore prior code/config from the reviewed backup. Retain both tables. No DROP, DELETE, token revocation or flag changes performed.'] + $before;
      }
      $created = [];
      if ($mode === '--apply') {
        // MySQL/MariaDB DDL commits independently. A partial apply is resumed by rechecking and rerunning.
        foreach ($this->definitions() as $table=>$ddl) {
          if ($before['tables'][$table] === 'MISSING') {
            $this->db->exec($ddl);
            $created[] = $table;
          }
        }
      }
      $after = $mode === '--apply' ? $this->inspect() : $before;
      $missing = in_array('MISSING', $after['tables'], true);
      return ['status'=>$after['blockers'] !== [] ? 'BLOCK' : ($missing
        ? ($mode === '--postflight' ? 'BLOCK' : 'READY_TO_APPLY') : 'SCHEMA_VERIFIED'),
        'created_tables'=>$created, 'activation'=>'UNCHANGED', 'data_changes'=>'NONE'] + $after;
    } finally {
      $q = $this->db->prepare('SELECT RELEASE_LOCK(?)');
      $q->execute([$name]);
    }
  }

  private function definitions(): array {
    require_once dirname(__DIR__, 2).'/src/Inzet/OnSiteEventInboxSchema.php';
    require_once dirname(__DIR__, 2).'/src/Inzet/OnSiteDeviceTokenSchema.php';
    return array_combine(self::TABLES, [
      \Brebo\Office\Inzet\OnSiteEventInboxSchema::statements()[0],
      \Brebo\Office\Inzet\OnSiteDeviceTokenSchema::statements()[0],
    ]);
  }

  private function rows(string $sql, array $params = []): array {
    $q = $this->db->prepare($sql);
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
  }

  private function columns(string $table): array {
    $rows = $this->rows('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME
      FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$table]);
    $result = [];
    foreach ($rows as $row) {
      // MariaDB exposes integer display widths; they are not storage types.
      $row['COLUMN_TYPE'] = preg_replace('/\b(bigint|tinyint|int)\(\d+\)/', '$1', strtolower($row['COLUMN_TYPE']));
      $result[$row['COLUMN_NAME']] = $row;
    }
    return $result;
  }

  private function engine(string $table): ?string {
    $r = $this->rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
    return $r === [] ? null : ($r[0]['ENGINE'] ?? 'VIEW');
  }

  private function count(string $sql): int { return (int)$this->db->query($sql)->fetchColumn(); }

  private function inspect(): array {
    $blockers = [];
    $parent = $this->columns('office_inzet_people');
    if ($this->engine('office_inzet_people') !== 'InnoDB'
      || ($parent['id']['COLUMN_TYPE'] ?? '') !== 'bigint unsigned'
      || ($parent['id']['IS_NULLABLE'] ?? '') !== 'NO'
      || !in_array($parent['active']['COLUMN_TYPE'] ?? '', ['tinyint', 'tinyint unsigned'], true)
      || ($parent['active']['IS_NULLABLE'] ?? '') !== 'NO'
      || ($this->indexes('office_inzet_people')['PRIMARY'] ?? null) !== ['unique'=>true, 'columns'=>['id']]) {
      return ['tables'=>[], 'blockers'=>['office_people_schema_incompatible'], 'identity_bridge'=>'UNVERIFIED'];
    }
    $expected = [
      'office_onsite_event_inbox'=>[
        'event_id'=>['varchar(100)', 'NO'], 'person_id'=>['bigint unsigned', 'NO'],
        'project_id'=>['varchar(64)', 'NO'], 'zone_id'=>['varchar(64)', 'NO'],
        'event_kind'=>['varchar(3)', 'NO'], 'occurred_at_utc'=>['varchar(27)', 'NO'],
        'received_at'=>['timestamp', 'NO'],
      ],
      'office_onsite_device_tokens'=>[
        'token_hash'=>['char(64)', 'NO'], 'person_id'=>['bigint unsigned', 'NO'],
        'expires_at'=>['datetime', 'YES'], 'revoked_at'=>['datetime', 'YES'],
      ],
    ];
    $tables = [];
    foreach ($expected as $table=>$columns) {
      if ($this->engine($table) === null) { $tables[$table] = 'MISSING'; continue; }
      $actual = $this->columns($table);
      $valid = $this->engine($table) === 'InnoDB' && array_keys($actual) === array_keys($columns);
      foreach ($columns as $name=>[$type, $nullable]) {
        $c = $actual[$name] ?? [];
        $extra = strtolower($c['EXTRA'] ?? '');
        $default = strtolower((string)($c['COLUMN_DEFAULT'] ?? ''));
        $valid = $valid && ($c['COLUMN_TYPE'] ?? '') === $type && ($c['IS_NULLABLE'] ?? '') === $nullable;
        if ($name === 'received_at') {
          $valid = $valid && in_array($default, ['current_timestamp', 'current_timestamp()'], true)
            && in_array($extra, ['', 'default_generated'], true);
        } else {
          $valid = $valid && in_array($default, ['', 'null'], true) && $extra === '';
        }
        if ($name === 'token_hash') $valid = $valid && ($c['COLLATION_NAME'] ?? '') === 'ascii_bin';
      }
      $wantedIndexes = $table === self::TABLES[0]
        ? ['PRIMARY'=>['unique'=>true,'columns'=>['event_id']], 'idx_onsite_person_project_time'=>['unique'=>false,'columns'=>['person_id','project_id','occurred_at_utc']]]
        : ['PRIMARY'=>['unique'=>true,'columns'=>['token_hash']], 'idx_onsite_device_person'=>['unique'=>false,'columns'=>['person_id']]];
      $actualIndexes = $this->indexes($table);
      ksort($wantedIndexes); ksort($actualIndexes);
      $valid = $valid && $actualIndexes === $wantedIndexes;
      $fk = $this->rows('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
        WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$table]);
      $valid = $valid && count($fk) === 1 && $fk[0]['COLUMN_NAME'] === 'person_id'
        && $fk[0]['REFERENCED_TABLE_SCHEMA'] === $this->database
        && $fk[0]['REFERENCED_TABLE_NAME'] === 'office_inzet_people' && $fk[0]['REFERENCED_COLUMN_NAME'] === 'id'
        && in_array($fk[0]['UPDATE_RULE'], ['RESTRICT','NO ACTION'], true)
        && in_array($fk[0]['DELETE_RULE'], ['RESTRICT','NO ACTION'], true);
      $triggers = $this->rows('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?', [$table]);
      $valid = $valid && $triggers === [];
      $tables[$table] = $valid ? 'COMPATIBLE' : 'INCOMPATIBLE';
      if (!$valid) $blockers[] = $table.'_schema_incompatible';
    }
    $links = ['office_people'=>$this->count('SELECT COUNT(*) FROM office_inzet_people'),
      'active_office_people'=>$this->count('SELECT COUNT(*) FROM office_inzet_people WHERE active=1')];
    foreach ($tables as $table=>$state) {
      if ($state !== 'COMPATIBLE') continue;
      $links[$table] = ['rows'=>$this->count('SELECT COUNT(*) FROM `'.$table.'`'),
        'orphan_people'=>$this->count('SELECT COUNT(*) FROM `'.$table.'` d LEFT JOIN office_inzet_people p ON p.id=d.person_id WHERE p.id IS NULL'),
        'inactive_people'=>$this->count('SELECT COUNT(*) FROM `'.$table.'` d JOIN office_inzet_people p ON p.id=d.person_id WHERE p.active<>1')];
      if ($links[$table]['orphan_people'] > 0) $blockers[] = $table.'_orphan_people';
    }
    if (($tables[self::TABLES[1]] ?? '') === 'COMPATIBLE') {
      $badHashes = $this->count("SELECT COUNT(*) FROM office_onsite_device_tokens WHERE token_hash NOT REGEXP '^[0-9a-f]{64}$'");
      if ($badHashes > 0) $blockers[] = 'invalid_device_hashes';
      $links['usable_office_devices'] = $this->count('SELECT COUNT(*) FROM office_onsite_device_tokens d JOIN office_inzet_people p ON p.id=d.person_id WHERE p.active=1 AND d.revoked_at IS NULL AND (d.expires_at IS NULL OR d.expires_at>UTC_TIMESTAMP())');
      $team = $this->columns('office_inzet_project_team');
      $projects = $this->columns('office_projects');
      $links['project_assignment_check'] = 'UNAVAILABLE';
      if (($team['person_id']['COLUMN_TYPE'] ?? '') === 'bigint unsigned'
        && ($team['project_id']['COLUMN_TYPE'] ?? '') === 'bigint unsigned'
        && ($projects['id']['COLUMN_TYPE'] ?? '') === 'bigint unsigned') {
        $links['project_assignment_check'] = 'CHECKED';
        $links['assigned_usable_office_devices'] = $this->count('SELECT COUNT(*) FROM office_onsite_device_tokens d JOIN office_inzet_people p ON p.id=d.person_id WHERE p.active=1 AND d.revoked_at IS NULL AND (d.expires_at IS NULL OR d.expires_at>UTC_TIMESTAMP()) AND EXISTS (SELECT 1 FROM office_inzet_project_team t JOIN office_projects j ON j.id=t.project_id WHERE t.person_id=p.id)');
      }
    }
    // Legacy/Drupal credentials are a separate authority. Never infer identity by coinciding numeric IDs.
    $legacy = [];
    foreach (['onsite_device_tokens','onsite_event_inbox','onsite_employees'] as $table) {
      $legacy[$table] = $this->engine($table) === null ? 'ABSENT' : 'PRESENT_NOT_IMPORTED';
    }
    $legacy['drupal_key_value_devices'] = 'NOT_CHECKED_EXTERNAL_AUTHORITY';
    return ['tables'=>$tables, 'blockers'=>$blockers, 'links'=>$links, 'legacy'=>$legacy,
      'identity_bridge'=>'UNVERIFIED_REQUIRES_SEPARATE_DEVICE_AND_PERSON_ACCEPTANCE'];
  }

  private function indexes(string $table): array {
    $rows = $this->rows('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART,INDEX_TYPE
      FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX', [$table]);
    $result = [];
    foreach ($rows as $r) {
      $name = $r['INDEX_NAME'];
      $result[$name]['unique'] = (int)$r['NON_UNIQUE'] === 0;
      $result[$name]['columns'][] = $r['SUB_PART'] === null && $r['INDEX_TYPE'] === 'BTREE'
        ? $r['COLUMN_NAME'] : 'unsupported_index';
    }
    return $result;
  }
}
