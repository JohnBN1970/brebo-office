<?php
declare(strict_types=1);

/**
 * One-shot additive Calc CRM-first table installation in the verified Office-test DB.
 * Requires the EXACT upstream Calc four-table schema as argv[1]. No data import.
 * Refuses all other DBs and pre-existing partial Calc schemas.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 2) { exit(2); }
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$configFile=$root.'/config/config.php';
$sqlFile=$argv[1];
if (!is_file($configFile) || !is_file($sqlFile) || is_link($sqlFile)) { echo "CALC_SCHEMA_INPUT=INVALID\n"; exit(1); }
$cfg=require $configFile;
if (!is_array($cfg)) { echo "OFFICE_CONFIG=INVALID\n"; exit(1); }
$dsn=$cfg['OFFICE_INTAKE_PDO_DSN']??null;
$user=$cfg['OFFICE_INTAKE_DB_USER']??null;
$password=$cfg['OFFICE_INTAKE_DB_PASSWORD']??null;
if (!is_string($dsn) || !preg_match('/^mysql:host=(localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)
    || $user!=='u213420663_office_test' || !is_string($password)) {
    echo "CALC_OFFICE_DATABASE_IDENTITY=DENIED\n"; exit(1);
}
$sql=file_get_contents($sqlFile);
if (!is_string($sql) || strlen($sql)<2000
    || substr_count($sql,'CREATE TABLE IF NOT EXISTS')!==4
    || preg_match('/\b(DROP|ALTER|TRUNCATE|DELETE|UPDATE|INSERT|REPLACE|LOAD|GRANT|REVOKE)\b/i',$sql)) {
    echo "CALC_SCHEMA_CONTENT=REJECTED\n"; exit(1);
}
$tables=['calculations','calculation_versions','calculation_lines','launch_nonces'];
foreach ($tables as $table) {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS '.preg_quote($table,'/').'\s*\(/',$sql)) {
        echo "CALC_SCHEMA_TABLE_MISSING\n"; exit(1);
    }
}
try {
    $db=new PDO($dsn,$user,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_TIMEOUT=>5
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test') {
        echo "CALC_OFFICE_DATABASE_IDENTITY=MISMATCH\n"; exit(1);
    }
    echo "CALC_OFFICE_DATABASE_IDENTITY=VERIFIED\n";
    $lookup=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $existing=0;
    foreach ($tables as $table) {
        $lookup->execute([$table]);
        $existing+=(int)$lookup->fetchColumn();
    }
    if ($existing===4) {
        echo "CALC_OFFICE_SCHEMA=ALREADY_PRESENT\n";
    } elseif ($existing!==0) {
        echo "CALC_OFFICE_SCHEMA=PARTIAL_REFUSED\n"; exit(1);
    } else {
        $parts=preg_split('/;\s*(?:\r?\n|$)/',$sql);
        if (!is_array($parts)) { exit(1); }
        $executed=0;
        foreach ($parts as $statement) {
            $statement=trim($statement);
            if ($statement==='' || strpos($statement,'CREATE TABLE IF NOT EXISTS')===false) continue;
            $db->exec($statement);
            $executed++;
        }
        if ($executed!==4) {
            echo "CALC_OFFICE_SCHEMA_STATEMENT_COUNT=UNEXPECTED\n"; exit(1);
        }
        echo "CALC_OFFICE_SCHEMA=INSTALLED\n";
    }
    foreach ($tables as $table) {
        $lookup->execute([$table]);
        if ((int)$lookup->fetchColumn()!==1) { echo "CALC_OFFICE_SCHEMA_VERIFY=FAIL\n"; exit(1); }
        $count=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        if ($count!==0) { echo "CALC_OFFICE_TABLE_NOT_EMPTY=".$table."\n";exit(1); }
    }
    echo "CALC_OFFICE_SCHEMA_VERIFIED=FOUR_EMPTY_TABLES\n";
    echo "OFFICE_ORIGINAL_DATA_CHANGED=NO\n";
    echo "CALC_PRODUCTION_DATA_IMPORTED=NO\n";
    echo "CALC_PUBLIC_ROUTER_CHANGED=NO\n";
} catch (Throwable $e) {
    echo "CALC_OFFICE_SCHEMA_APPLY=FAILED\n";
    exit(1);
}
