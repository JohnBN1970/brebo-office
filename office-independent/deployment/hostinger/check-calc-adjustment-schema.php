<?php
declare(strict_types=1);
// Read-only verification. Execute from a trusted Office runtime with a PDO connection
// already established as $db. Never install schema or flip feature flags here.
if(!isset($db)||!$db instanceof PDO){
    fwrite(STDERR,"SCHEMA_CHECK=NO_DATABASE_CONNECTION\n");
    exit(2);
}
$expected=[
 'calc_net_quantity'=>'decimal(10,4)',
 'calc_material_loss_percent'=>'decimal(5,2)',
 'calc_extra_labor_hours'=>'decimal(10,4)',
 'calc_quantity_source'=>'varchar(64)',
 'calc_quantity_source_ref'=>'varchar(128)',
];
try{
    if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('MYSQL_REQUIRED');
    $stmt=$db->prepare("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calculation_lines'");
    $stmt->execute();
    $columns=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$columns[(string)$row['COLUMN_NAME']]=$row;
    foreach($expected as $name=>$type){
        $row=$columns[$name]??null;
        if(!is_array($row)||strtolower((string)$row['COLUMN_TYPE'])!==$type||(string)$row['IS_NULLABLE']!=='YES'){
            echo "SCHEMA_CHECK=BLOCKED\nCOLUMN=".$name."\n";
            exit(1);
        }
    }
    echo "SCHEMA_CHECK=PASS\nCOLUMNS_VERIFIED=".count($expected)."\n";
}catch(Throwable $e){
    fwrite(STDERR,"SCHEMA_CHECK=ERROR\n");
    exit(2);
}
