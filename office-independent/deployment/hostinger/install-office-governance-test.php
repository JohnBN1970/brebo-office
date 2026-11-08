<?php
declare(strict_types=1);

/**
 * Explicit additive installer for governance in the isolated Office-test DB.
 * MySQL DDL autocommits: on failure, leave any created tables in place for review.
 * Never invoked by a web request and never modifies existing tables or feature flags.
 */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_SCHEMA_INSTALL') !== 'explicit') {
    fwrite(STDERR, "ABORT: explicit Office-test installation required\n");
    exit(2);
}
$root = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "ABORT: isolated configuration missing\n");
    exit(2);
}
$config = require $configFile;
$dsn = is_array($config) ? ($config['OFFICE_INTAKE_PDO_DSN'] ?? null) : null;
$user = is_array($config) ? ($config['OFFICE_INTAKE_DB_USER'] ?? null) : null;
$password = is_array($config) ? ($config['OFFICE_INTAKE_DB_PASSWORD'] ?? null) : null;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test'
    || !is_string($password) || $password === '') {
    fwrite(STDERR, "ABORT: isolated database scope mismatch\n");
    exit(2);
}
require_once dirname(__DIR__, 2) . '/src/Governance/OfficeGovernanceSchema.php';

try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('wrong_database');
    }
    $expected = ['office_quality_checks', 'office_risks', 'office_procurement_orders',
        'office_governance_commands', 'office_governance_audit'];
    $tableQuery = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $columnQuery = $db->prepare('SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    foreach (['office_projects' => ['id'], 'office_buildings' => ['id'],
        'office_project_buildings' => ['project_id', 'building_id']] as $table => $columns) {
        $tableQuery->execute(['u213420663_office_test', $table]);
        if ($tableQuery->fetchColumn() !== 'InnoDB') {
            throw new RuntimeException('missing_innodb_parent_' . $table);
        }
        foreach ($columns as $column) {
            $columnQuery->execute(['u213420663_office_test', $table, $column]);
            $record = $columnQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record) || !preg_match('/^bigint(?:\\(20\\))? unsigned$/i', (string)$record['COLUMN_TYPE'])
                || $record['IS_NULLABLE'] !== 'NO') {
                throw new RuntimeException('incompatible_fk_parent_' . $table . '_' . $column);
            }
        }
    }
    $indexQuery = $db->prepare('SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
    $indexQuery->execute(['u213420663_office_test', 'office_project_buildings']);
    $indexes = [];
    foreach ($indexQuery->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $indexes[$record['INDEX_NAME']][] = $record['COLUMN_NAME'];
    }
    $hasParentIndex = false;
    foreach ($indexes as $columns) {
        if (array_slice($columns, 0, 2) === ['project_id', 'building_id']) {
            $hasParentIndex = true;
            break;
        }
    }
    if (!$hasParentIndex) {
        throw new RuntimeException('project_buildings_fk_index_missing');
    }
    foreach ($expected as $table) {
        $tableQuery->execute(['u213420663_office_test', $table]);
        if ($tableQuery->fetchColumn() !== false) {
            throw new RuntimeException('governance_table_already_exists_' . $table);
        }
    }
    echo "OFFICE_GOVERNANCE_PREFLIGHT_PASSED\n";
    foreach (\Brebo\Office\Governance\OfficeGovernanceSchema::statements('mysql') as $sql) {
        $db->exec($sql);
    }
    foreach ($expected as $table) {
        $tableQuery->execute(['u213420663_office_test', $table]);
        if ($tableQuery->fetchColumn() !== 'InnoDB') {
            throw new RuntimeException('verification_failed_' . $table);
        }
        echo $table . ": INSTALLED\n";
    }
    echo "OFFICE_GOVERNANCE_SCHEMA_INSTALL_COMPLETE\n";
} catch (Throwable $e) {
    // Avoid printing database credentials or exception detail from a connector.
    fwrite(STDERR, "ABORT: schema installation incomplete; inspect database before retry\n");
    exit(1);
}
