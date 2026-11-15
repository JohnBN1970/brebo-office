<?php
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../public/office-materials.php');
foreach(["OFFICE_MATERIALS_ENABLED","/office-test","requireImport(\$operator->operatorId)","material_csrf","hash_equals","is_uploaded_file","source_mode","MaterialInvoiceSource","MaterialDocumentSource","MaterialCatalogPublisher","price_is_ex_vat","material_reviews","expires","actor_id","\$documentSource()->verify"]as$needle)if(!str_contains($source,$needle))throw new RuntimeException('Missing route control: '.$needle);
if(str_contains($source,'CREATE TABLE')||str_contains($source,'ALTER TABLE'))throw new RuntimeException('No DDL in HTTP route');
$connection=file_get_contents(__DIR__.'/../src/Materials/MaterialCatalogConnection.php');
if(!str_contains($connection,'OFFICE_MATERIALS_CANONICAL_PDO_DSN')||str_contains($connection,'OFFICE_INTAKE_PDO_DSN'))throw new RuntimeException('No automatic second catalogue connection');
echo "MATERIAL_ROUTE=PASS\n";
