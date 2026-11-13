<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;

/** Explicit Office-owned canonical provider; never falls back to an empty native catalogue. */
final class MaterialCatalogConnection
{
    public static function fromEnvironment():PDO
    {
        if(getenv('OFFICE_MATERIALS_CANONICAL_SCHEMA_VERIFIED')!=='enabled')throw new \RuntimeException('De bestaande artikelbron is nog niet geverifieerd voor materiaalimport.');
        $dsn=getenv('OFFICE_MATERIALS_CANONICAL_PDO_DSN');
        if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new \RuntimeException('De canonieke artikelbron is niet geconfigureerd.');
        return new PDO($dsn,getenv('OFFICE_MATERIALS_CANONICAL_DB_USER')?:'',getenv('OFFICE_MATERIALS_CANONICAL_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
}
