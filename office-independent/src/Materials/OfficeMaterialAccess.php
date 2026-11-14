<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;

/** Native Office authority, independent of legacy source accounts and CRM edit rights. */
final class OfficeMaterialAccess
{
    public function __construct(private readonly PDO $db){}
    public function allows(string $operatorId,string $permission):bool
    {
        if($operatorId===''||!in_array($permission,['read','import'],true))return false;
        $query=$this->db->prepare("SELECT 1 FROM office_module_permissions p JOIN office_operator_accounts a ON a.id=p.operator_id WHERE p.operator_id=? AND a.active=1 AND p.module='materials' AND p.permission=? LIMIT 1");
        $query->execute([$operatorId,$permission]);return $query->fetchColumn()!==false;
    }
    public function requireImport(string $operatorId):void
    {
        if(!$this->allows($operatorId,'read')||!$this->allows($operatorId,'import'))throw new \DomainException('Geen recht om de materialenprijslijst te wijzigen.');
    }
}
