<?php
declare(strict_types=1);

/**
 * Office-test project-rights prerequisites. Installs ONLY reviewed additive
 * audit and site-administrator schemas. Never grants any user a permission.
 * --plan is read-only; --apply requires explicit confirmation and root CLI.
 */
$mode=$argv[1]??'';
if (PHP_SAPI!=='cli' || !in_array($mode,['--plan','--apply'],true)
    || ($mode==='--apply' && ($argv[2]??'')!=='CONFIRM-OFFICE-TEST-ROLE-SCHEMA')) {
    fwrite(STDERR,"USAGE: sudo php project-role-rights-migrate.php --plan | --apply CONFIRM-OFFICE-TEST-ROLE-SCHEMA\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid()!==0) {
    fwrite(STDERR,"ROLE_RIGHTS_SCHEMA=BLOCKED_ROOT_CLI_REQUIRED\n");
    exit(2);
}
try {
    $release=realpath('/srv/brebo/office-test/current');
    if (!is_string($release)
        || preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D',$release)!==1
        || trim((string)@file_get_contents($release.'/.office-source-sha'))!==basename($release)) {
        throw new RuntimeException('RELEASE_IDENTITY');
    }
    $configPath='/etc/brebo/office-test-db.env';
    clearstatcache(true,$configPath);
    if (!is_file($configPath)||is_link($configPath)||fileowner($configPath)!==0
        || (fileperms($configPath)&0077)!==0) throw new RuntimeException('PRIVATE_CONFIG_REQUIRED');
    $raw=file_get_contents($configPath);
    if (!is_string($raw)) throw new RuntimeException('PRIVATE_CONFIG_UNREADABLE');
    $env=[];
    foreach (explode("\n",$raw) as $line) {
        if (!preg_match('/^\\s*(?:export\\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D',$line,$m)) continue;
        $value=trim($m[2]);
        if (strlen($value)>=2 && ($value[0]==='"'||$value[0]==="'")
            && substr($value,-1)===$value[0]) $value=substr($value,1,-1);
        $env[$m[1]]=$value;
    }
    if (($env['OFFICE_TEST_DB_NAME']??'')!=='brebo_office_test'
        || ($env['OFFICE_TEST_DB_USER']??'')!=='brebo_office_test'
        || !in_array($env['OFFICE_TEST_DB_HOST']??'', ['localhost','127.0.0.1'],true)) {
        throw new RuntimeException('ISOLATED_DATABASE_REQUIRED');
    }
    $db=new PDO(
        'mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
        $env['OFFICE_TEST_DB_USER'],$env['OFFICE_TEST_DB_PASSWORD']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]
    );
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') {
        throw new RuntimeException('DATABASE_IDENTITY_MISMATCH');
    }
    echo 'ROLE_RIGHTS_RELEASE='.basename($release)."\n";
    $schemas=[
        'office_test_site_administrators'=>'office-test-site-administrators.sql',
        'office_test_project_create_permission_events'=>'office-test-project-create-permission-events.sql',
    ];
    $expected=[
        'office_test_site_administrators'=>['operator_id','granted_at','granted_by','reason'],
        'office_test_project_create_permission_events'=>['id','actor_operator_id','target_operator_id','action','reason','created_at'],
    ];
    foreach ($schemas as $table=>$filename) {
        $path=$release.'/schema/'.$filename;
        if (!is_file($path)||is_link($path)) throw new RuntimeException('SCHEMA_FILE_MISSING_'.$table);
        $sql=file_get_contents($path);
        if (!is_string($sql)) throw new RuntimeException('SCHEMA_UNREADABLE_'.$table);
        $clean=trim((string)preg_replace('/^--.*$/m','',$sql));
        $clean=rtrim($clean," \n\r\t;");
        if (preg_match('/^CREATE TABLE(?: IF NOT EXISTS)? '.preg_quote($table,'/').'\\s*\\(/D',$clean)!==1
            || preg_match('/\\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4$/D',$clean)!==1
            || str_contains($clean,';') || str_contains(strtoupper($clean),'INSERT ')) {
            throw new RuntimeException('UNREVIEWED_SCHEMA_'.$table);
        }
        $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]);
        $fields=$q->fetchAll(PDO::FETCH_COLUMN);
        if ($fields!==[] && array_diff($expected[$table],$fields)!==[]) {
            throw new RuntimeException('EXISTING_SCHEMA_MISMATCH_'.$table);
        }
        $state=$fields===[]?'MISSING':'PRESENT';
        echo 'ROLE_RIGHTS_SCHEMA_'.$table.'='.$state."\n";
        if ($mode==='--apply' && $fields===[]) {
            $db->exec($clean);
            $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $q->execute([$table]);
            if (array_diff($expected[$table],$q->fetchAll(PDO::FETCH_COLUMN))!==[]) {
                throw new RuntimeException('POST_INSTALL_SCHEMA_MISMATCH_'.$table);
            }
            echo 'ROLE_RIGHTS_SCHEMA_'.$table."=INSTALLED\n";
        }
    }
    // Deliberately not printing account identities or grants.
    echo $mode==='--plan' ? "ROLE_RIGHTS_SCHEMA=PLAN_ONLY_NO_WRITES\n"
        : "ROLE_RIGHTS_SCHEMA=READY_NO_PERMISSIONS_GRANTED\n";
} catch (Throwable $e) {
    fwrite(STDERR,'ROLE_RIGHTS_SCHEMA=BLOCKED '.$e->getMessage()."\n");
    exit(1);
}
