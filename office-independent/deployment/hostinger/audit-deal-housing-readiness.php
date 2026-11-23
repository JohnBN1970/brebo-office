<?php
declare(strict_types=1);
/** Read-only deployment gate. No schema changes, no feature flag writes. */
if (getenv('OFFICE_BASE_PATH')!=='/office-test') { echo "DEAL_HOUSING_PREFLIGHT=BLOCK_SCOPE\n"; exit(2); }
$dsn=getenv('OFFICE_INTAKE_PDO_DSN')?:'';
if (!preg_match('/(?:^|;)dbname=brebo_office_test(?:;|$)/',$dsn)) {
    echo "DEAL_HOUSING_PREFLIGHT=BLOCK_DATABASE\n"; exit(2);
}
try {
    $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $required=[
        'office_crm_opportunities'=>['id','stage','status'],
        'office_buildings'=>['id','building_name'],
        'office_housing_types'=>['id','building_id','code','name'],
        'office_project_buildings'=>['project_id','building_id'],
        'office_project_housing_types'=>['project_id','building_id','housing_type_id','dwelling_count'],
        'office_crm_deal_housing_types'=>['opportunity_id','building_id','housing_type_id','dwelling_count','revision','updated_by'],
        'office_crm_deal_housing_events'=>['opportunity_id','building_id','housing_type_id','old_count','new_count','revision','changed_by'],
    ];
    $check=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $ready=true;
    foreach($required as $table=>$columns){
        $check->execute([$table]);
        $found=$check->fetchAll(PDO::FETCH_COLUMN);
        foreach($columns as $column){
            if(!in_array($column,$found,true)){
                echo "MISSING_SCHEMA=".$table.'.'.$column."\n";
                $ready=false;
            }
        }
    }
    echo 'DEAL_HOUSING_PREFLIGHT='.($ready?'READY':'BLOCK_SCHEMA')."\n";
    exit($ready?0:2);
} catch(Throwable $e) {
    echo "DEAL_HOUSING_PREFLIGHT=BLOCK_CONNECTION\n"; exit(2);
}
