<?php
declare(strict_types=1);
/** One controlled, reversible CODE/config test installation. Additive DDL is never rolled back by dropping data. */
if(PHP_SAPI!=='cli'||getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_CRM_COMPLETION_DEPLOY')!=='explicit-test-only'){fwrite(STDERR,"CRM_INSTALL_SCOPE_DENIED\n");exit(2);}
$phase='scope';$installed=[];
try{
    $base='/home/u213420663/domains/brebobv.nl/office_calc_test';$configPath=$base.'/config/config.php';
    if(!is_file($configPath)||is_link($configPath))throw new RuntimeException('config');
    $oldConfig=file_get_contents($configPath);$config=require $configPath;
    if(!is_array($config)||($config['OFFICE_BASE_PATH']??null)!=='/office-test'
        ||!in_array($config['OFFICE_INTAKE_PDO_DSN']??null,['mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4','mysql:host=127.0.0.1;dbname=u213420663_office_test;charset=utf8mb4'],true)
        ||($config['OFFICE_INTAKE_DB_USER']??null)!=='u213420663_office_test'
        ||($config['OFFICE_TEST_CRM_ENABLED']??null)!=='enabled'||($config['OFFICE_CRM_AUTH_MODE']??null)!=='session_only'
        )throw new RuntimeException('configuration_scope');
    // Existing enabled flags are valid on repeat installations; never disable or reset them.
    foreach(['OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WORKSPACE_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED'] as $flag){
        if(!in_array($config[$flag]??null,['enabled','disabled',null],true))throw new RuntimeException('unexpected_flag_state');
    }
    $active=realpath($config['OFFICE_PRIVATE_ROOT']??'');$stage=realpath($argv[1]??'');
    if(!$active||!str_starts_with($active,$base.'/release-')||!str_ends_with($active,'/private')
        ||!$stage||!str_starts_with($stage,$base.'/.crm-completion-stage/'))throw new RuntimeException('release_scope');
    $lock=fopen($base.'/.office-test-release.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('release_lock');
    $manifest=json_decode((string)file_get_contents($stage.'/manifest.json'),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($manifest)||!preg_match('/^[a-f0-9]{40}$/D',$manifest['commit']??''))throw new RuntimeException('manifest');
    $phase='backup';$backupSha=getenv('CRM_BACKUP_SHA');$verified=false;
    if(!is_string($backupSha)||!preg_match('/^[a-f0-9]{64}$/D',$backupSha))throw new RuntimeException('backup_proof');
    foreach(glob($base.'/private-backups/governance/office-test-before-governance-*.sql')?:[] as $backup){
        if(!is_link($backup)&&filesize($backup)>1024&&filemtime($backup)>time()-3600&&hash_equals($backupSha,hash_file('sha256',$backup)))$verified=true;
    }
    if(!$verified)throw new RuntimeException('fresh_backup_required');
    $phase='candidates';
    require_once $stage.'/office-independent/deployment/hostinger/OfficeCrmWorkspaceMount.php';
    $router='/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';$rewrite=dirname($router).'/.htaccess';$nav=$active.'/src/Intake/OfficeNavigation.php';
    $candidates=[];$before=[];
    foreach([$router,$rewrite,$nav,$configPath] as $file){
        if(!is_file($file)||is_link($file)||!is_readable($file)||!is_writable($file))throw new RuntimeException('shared_target');
        $before[$file]=file_get_contents($file);
    }
    $candidates[$router]=OfficeCrmWorkspaceMount::router($before[$router]);
    $candidates[$rewrite]=OfficeCrmWorkspaceMount::rewrite($before[$rewrite]);
    $candidates[$nav]=OfficeCrmWorkspaceMount::navigation($before[$nav]);
    foreach($manifest['files']??[] as $relative=>$spec){
        if(!preg_match('~^(?:src/Crm/OfficeCrm[A-Za-z]+\.php|public/office-crm-[a-z-]+\.php)$~D',$relative)||is_link($stage.'/office-independent/'.$relative))throw new RuntimeException('package_path');
        $source=$stage.'/office-independent/'.$relative;
        if(!is_file($source)||!hash_equals($spec['sha256']??'',hash_file('sha256',$source)))throw new RuntimeException('package_hash');
        $target=$active.'/'.$relative;$parent=dirname($target);
        if(!is_dir($parent)||is_link($target)||!str_starts_with((string)realpath($parent),$active.'/'))throw new RuntimeException('package_target');
        $prior=is_file($target)?file_get_contents($target):null;
        // Verified Office-test baseline from Git commit 816b81985228: only these two UTF-8 fixes.
        $knownActive=[
            'src/Crm/OfficeCrmOpportunityPolicy.php'=>'f8c5ac59c70663b7e52f2dd305e30115eda2e416b5d83b9d99cdafed4fd4de46',
            'src/Crm/OfficeCrmOpportunityRepository.php'=>'e9bdfa34e07dc7d6a1f3323ae95d5e6a6dd45c098c953ca1f3d4762cbcf23802',
        ];
        $accepted=array_filter([$spec['prior_sha256']??null,$spec['sha256'],$knownActive[$relative]??null]);
        if($prior!==null&&!in_array(hash('sha256',$prior),$accepted,true))throw new RuntimeException('unknown_active_source');
        $before[$target]=$prior;$candidates[$target]=file_get_contents($source);
    }
    if(count($manifest['files']??[])!==12)throw new RuntimeException('package_count');
    $stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(5));$backupDir=$base.'/private-backups/crm/'.$stamp;
    if(!mkdir($backupDir,0700,true))throw new RuntimeException('backup_directory');
    foreach($before as $file=>$text)if($text!==null){$copy=$backupDir.'/'.hash('sha256',$file).'.backup';if(file_put_contents($copy,$text,LOCK_EX)!==strlen($text)||!chmod($copy,0600))throw new RuntimeException('file_backup');}
    $previous='.config-before-crm-'.$stamp.'.php';$previousPath=dirname($configPath).'/'.$previous;
    if(file_put_contents($previousPath,$oldConfig,LOCK_EX)!==strlen($oldConfig)||!chmod($previousPath,0600))throw new RuntimeException('config_backup');
    $wrapper="<?php\ndeclare(strict_types=1);\n\$config = require __DIR__ . ".var_export('/'.$previous,true).";\nif (!is_array(\$config)) throw new RuntimeException('Invalid Office configuration');\n";
    foreach(['OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WORKSPACE_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED'] as $flag)$wrapper.=' $config['.var_export($flag,true)."] = 'enabled';\n";
    $wrapper.="return \$config;\n";$candidates[$configPath]=$wrapper;
    $phase='lint';
    foreach($candidates as $file=>$text)if(str_ends_with($file,'.php')){
        $temp=tempnam($backupDir,'.lint-');file_put_contents($temp,$text);$output=[];$status=1;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$output,$status);unlink($temp);if($status!==0)throw new RuntimeException('candidate_syntax');
    }
    // Verify wrapper semantics in its own directory without printing configuration values.
    $temp=tempnam(dirname($configPath),'.crm-candidate-');file_put_contents($temp,$wrapper);chmod($temp,0600);$candidateConfig=require $temp;unlink($temp);
    foreach($config as $key=>$value)if(!in_array($key,['OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WORKSPACE_SCHEMA_VERIFIED','OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED'],true)&&(!array_key_exists($key,$candidateConfig)||$candidateConfig[$key]!==$value))throw new RuntimeException('config_preservation');
    $phase='schema';
    $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $relationCount=(int)$db->query('SELECT COUNT(*) FROM office_crm_relations')->fetchColumn();$contactCount=(int)$db->query('SELECT COUNT(*) FROM office_crm_contact_people')->fetchColumn();
    // The legacy migration is intentionally one-shot. A verified fully migrated
    // Office-test schema is accepted without replaying non-transactional MySQL DDL.
    $schemaName=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    if($schemaName!=='u213420663_office_test')throw new RuntimeException('migration_database_scope');
    $requiredColumns=[
        'office_crm_opportunities'=>['id','title','stage','source','estimated_value','currency','created_at','updated_at','service_type','revision','outcome_note','relation_id','contact_id','description','expected_value','expected_close_date','next_action','next_action_at','opname_required','status'],
        'office_crm_opportunity_activities'=>['id','opportunity_id','activity_type','created_by','request_key','request_hash','detail_json','title','scheduled_at','completed_at','created_at'],
    ];
    $schemaQuery=$db->prepare('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?)');
    $schemaQuery->execute([$schemaName,'office_crm_opportunities','office_crm_opportunity_activities']);
    $existingColumns=[];
    foreach($schemaQuery->fetchAll(PDO::FETCH_ASSOC) as $row)$existingColumns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=true;
    $activitiesExist=isset($existingColumns['office_crm_opportunity_activities']);
    if($activitiesExist){
        foreach($requiredColumns as $table=>$columns)foreach($columns as $column)
            if(!isset($existingColumns[$table][$column]))throw new RuntimeException('existing_schema_incomplete');
        $indexQuery=$db->prepare('SELECT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?)');
        $indexQuery->execute([$schemaName,'office_crm_opportunities','office_crm_opportunity_activities']);
        $existingIndexes=[];
        foreach($indexQuery->fetchAll(PDO::FETCH_ASSOC) as $row)$existingIndexes[$row['TABLE_NAME']][$row['INDEX_NAME']]=true;
        foreach([
            'office_crm_opportunities'=>['PRIMARY','idx_crm_opp_relation','idx_crm_opp_stage','idx_crm_opp_calendar'],
            'office_crm_opportunity_activities'=>['PRIMARY','uq_crm_activity_request','idx_crm_opp_activity_calendar'],
        ] as $table=>$indexes)foreach($indexes as $index)
            if(!isset($existingIndexes[$table][$index]))throw new RuntimeException('existing_schema_index_missing');
        $fkQuery=$db->prepare('SELECT TABLE_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?) AND REFERENCED_TABLE_NAME IS NOT NULL');
        $fkQuery->execute([$schemaName,'office_crm_opportunities','office_crm_opportunity_activities']);
        $existingFks=[];
        foreach($fkQuery->fetchAll(PDO::FETCH_ASSOC) as $row)$existingFks[$row['TABLE_NAME']][$row['CONSTRAINT_NAME']]=true;
        foreach([
            'office_crm_opportunities'=>['fk_crm_opp_relation','fk_crm_opp_contact'],
            'office_crm_opportunity_activities'=>['fk_crm_opp_activity'],
        ] as $table=>$constraints)foreach($constraints as $constraint)
            if(!isset($existingFks[$table][$constraint]))throw new RuntimeException('existing_schema_fk_missing');
        echo "CRM_EXISTING_MIGRATED_SCHEMA_VERIFIED_NO_DDL\\n";
    }else{
        putenv('OFFICE_CRM_LEGACY_MIGRATION=explicit');putenv('OFFICE_TEST_CONFIG_PATH='.$configPath);
        require $stage.'/office-independent/deployment/hostinger/migrate-office-crm-legacy-opportunities-test.php';
    }
    // The migration script is intentionally fail-closed and may stop on unexpected state.
    if((int)$db->query('SELECT COUNT(*) FROM office_crm_relations')->fetchColumn()!==$relationCount||(int)$db->query('SELECT COUNT(*) FROM office_crm_contact_people')->fetchColumn()!==$contactCount)throw new RuntimeException('relations_changed');
    foreach(['currency','service_type','revision','outcome_note','relation_id','contact_id','next_action_at'] as $column)$db->query('SELECT '.$column.' FROM office_crm_opportunities LIMIT 0');
    foreach(['created_by','request_key','request_hash','detail_json'] as $column)$db->query('SELECT '.$column.' FROM office_crm_opportunity_activities LIMIT 0');
    $phase='install';
    // Shared files and configuration are installed last. Every change has a compare-and-swap lease.
    $order=array_merge(array_values(array_diff(array_keys($candidates),[$router,$rewrite,$nav,$configPath])),[$router,$rewrite,$nav,$configPath]);
    foreach($order as $file){
        $text=$candidates[$file];$current=is_file($file)?file_get_contents($file):null;
        if($current!==$before[$file])throw new RuntimeException('concurrent_file_change');
        if($current===$text)continue;
        $temp=tempnam(dirname($file),'.crm-install-');
        if(!is_string($temp)||file_put_contents($temp,$text,LOCK_EX)!==strlen($text)||!chmod($temp,$file===$configPath?0600:($current===null?0644:(fileperms($file)&0777)))||!($current===null?link($temp,$file):rename($temp,$file)))throw new RuntimeException('atomic_install');
        if(is_file($temp))unlink($temp);
        $installed[$file]=hash('sha256',$text);
        if(!hash_equals($installed[$file],hash_file('sha256',$file)))throw new RuntimeException('installed_hash');
    }
    file_put_contents($backupDir.'/manifest.json',json_encode(['commit'=>$manifest['commit'],'backup_sha256'=>$backupSha,'files'=>array_map(static fn($text)=>$text===null?null:hash('sha256',$text),$before)],JSON_THROW_ON_ERROR));chmod($backupDir.'/manifest.json',0600);
    echo "CRM_TEST_WORKSPACE_INSTALLED\nCRM_EXISTING_RELATIONS_PRESERVED\nCRM_CODE_CONFIG_ROUTER_BACKUPS_VERIFIED\n";
}catch(Throwable $e){
    // Restore only our own unchanged writes; never overwrite an intervening release.
    foreach(array_reverse(array_keys($installed)) as $file)if(is_file($file)&&hash_equals($installed[$file],hash_file('sha256',$file))){
        if($before[$file]===null){@unlink($file);}else{$temp=tempnam(dirname($file),'.crm-rollback-');file_put_contents($temp,$before[$file]);chmod($temp,$file===$configPath?0600:0644);rename($temp,$file);}
    }
    // Emit only a fixed, non-sensitive reason code; never expose config, paths or credentials.
    $reason=$e->getMessage();
    if(in_array($reason,['router_conflict','router_anchor','rewrite_anchor','navigation_anchor','navigation_funnel_anchor','navigation_selection_anchor','shared_target','package_path','package_hash','package_target','unknown_active_source','package_count','configuration_scope','release_scope','release_lock'],true))fwrite(STDERR,'CRM_INSTALL_BLOCKED_REASON='.$reason."\n");
    fwrite(STDERR,'CRM_INSTALL_BLOCKED_PHASE='.$phase."\nCRM_ADDITIVE_SCHEMA_IS_NEVER_DROPPED_AUTOMATICALLY\n");exit(1);
}
