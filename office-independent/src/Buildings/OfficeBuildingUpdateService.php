<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use PDO;
use DomainException;
final class OfficeBuildingUpdateService {
    public function __construct(private PDO $db) {}
    public static function revision(array $row): string {
        return hash('sha256',serialize(array_map(static fn($k)=>(string)($row[$k]??''),['building_name','street_address','postal_code','city'])));
    }
    public function update(int $id,int $operatorId,array $input,string $revision): void {
        if ($id<1 || $operatorId<1) throw new DomainException('invalid_identity');
        $clean=[];
        foreach (['building_name'=>160,'street_address'=>255,'postal_code'=>32,'city'=>160] as $key=>$limit) {
            $v=$input[$key]??null;
            if (!is_string($v) || strlen(trim($v))>$limit) throw new DomainException('invalid_input');
            $clean[$key]=trim($v);
        }
        if ($clean['building_name']==='') throw new DomainException('invalid_name');
        $this->db->beginTransaction();
        try {
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'':' FOR UPDATE';
            $q=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1'.$lock);
            $q->execute([$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('forbidden');
            $q=$this->db->prepare('SELECT b.building_name,b.street_address,b.postal_code,b.city FROM office_buildings b JOIN office_building_register_grants g ON g.building_id=b.id WHERE b.id=? AND b.active=1 AND g.operator_id=?'.$lock);
            $q->execute([$id,$operatorId]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row || !hash_equals(self::revision($row),$revision)) throw new DomainException('forbidden_or_stale');
            if ($clean['street_address']!=='' && $clean['postal_code']!=='') {
                $q=$this->db->prepare("SELECT id FROM office_buildings WHERE id<>? AND UPPER(REPLACE(postal_code,' ',''))=? AND LOWER(TRIM(street_address))=LOWER(?) LIMIT 1");
                $q->execute([$id,strtoupper(str_replace(' ','',$clean['postal_code'])),$clean['street_address']]);
                if ($q->fetchColumn()!==false) throw new DomainException('duplicate_address');
            }
            $q=$this->db->prepare('UPDATE office_buildings SET building_name=?,street_address=?,postal_code=?,city=? WHERE id=?');
            $q->execute([$clean['building_name'],$clean['street_address'],$clean['postal_code'],$clean['city'],$id]);
            if (getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')!=='enabled') throw new DomainException('building_audit_unavailable');
            require_once __DIR__.'/OfficeBuildingAudit.php';
            if (self::revision($row)!==self::revision($clean)) (new OfficeBuildingAudit($this->db))->record($id,$operatorId,'updated','register',null,$row,$clean);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
