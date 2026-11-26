<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;

use PDO;
use DomainException;
use InvalidArgumentException;

final class OfficeBuildingDwellingRepository
{
    public function __construct(private readonly PDO $db) {}

    public function listing(int $operatorId, int $buildingId): array
    {
        $q=$this->db->prepare("SELECT d.id,d.dwelling_code,d.house_number,d.floor_label,d.housing_type_id,d.active,h.name AS housing_type_name
            FROM office_building_dwellings d JOIN office_buildings b ON b.id=d.building_id
            JOIN office_building_register_grants g ON g.building_id=b.id
            JOIN office_operator_accounts a ON a.id=g.operator_id AND a.active=1
            LEFT JOIN office_housing_types h ON h.id=d.housing_type_id AND h.building_id=b.id
            WHERE b.id=? AND b.active=1 AND g.operator_id=? ORDER BY d.dwelling_code,d.id");
        $q->execute([$buildingId,$operatorId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $operatorId,int $buildingId,array $input): int
    {
        $code=self::field($input,'dwelling_code',100,true);
        $number=self::field($input,'house_number',40,true);
        $floor=self::field($input,'floor_label',80,false);
        $raw=$input['housing_type_id']??'';
        $type=$raw===''||$raw===null?null:filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($type===false) throw new InvalidArgumentException('Ongeldig woningtype.');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
            $q->execute([$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen schrijfrecht.');
            $q=$this->db->prepare('SELECT b.id FROM office_buildings b JOIN office_building_register_grants g ON g.building_id=b.id WHERE b.id=? AND b.active=1 AND g.operator_id=? FOR UPDATE');
            $q->execute([$buildingId,$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen gebouwtoegang.');
            if ($type!==null) {
                $q=$this->db->prepare('SELECT id FROM office_housing_types WHERE id=? AND building_id=? FOR UPDATE');
                $q->execute([$type,$buildingId]);
                if (!$q->fetchColumn()) throw new DomainException('Woningtype hoort niet bij gebouw.');
            }
            $q=$this->db->prepare('INSERT INTO office_building_dwellings (building_id,housing_type_id,dwelling_code,house_number,floor_label) VALUES (?,?,?,?,?)');
            $q->execute([$buildingId,$type,$code,$number,$floor===''?null:$floor]);
            $id=(int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $operatorId,int $buildingId,int $dwellingId,array $input,string $revision): void
    {
        $code=self::field($input,'dwelling_code',100,true);
        $number=self::field($input,'house_number',40,true);
        $floor=self::field($input,'floor_label',80,false);
        $raw=$input['housing_type_id']??'';
        $type=$raw===''||$raw===null?null:filter_var($raw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($type===false) throw new InvalidArgumentException('Ongeldig woningtype.');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
            $q->execute([$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen schrijfrecht.');
            $q=$this->db->prepare('SELECT b.id FROM office_buildings b JOIN office_building_register_grants g ON g.building_id=b.id WHERE b.id=? AND b.active=1 AND g.operator_id=? FOR UPDATE');
            $q->execute([$buildingId,$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen gebouwtoegang.');
            $q=$this->db->prepare('SELECT id,dwelling_code,house_number,floor_label,housing_type_id,active FROM office_building_dwellings WHERE id=? AND building_id=? FOR UPDATE');
            $q->execute([$dwellingId,$buildingId]);
            $old=$q->fetch(PDO::FETCH_ASSOC);
            if (!$old || !hash_equals(self::revision($old),$revision)) throw new DomainException('Woning gewijzigd of niet toegankelijk.');
            if ($type!==null) {
                $q=$this->db->prepare('SELECT id FROM office_housing_types WHERE id=? AND building_id=? FOR UPDATE');
                $q->execute([$type,$buildingId]);
                if (!$q->fetchColumn()) throw new DomainException('Woningtype hoort niet bij gebouw.');
            }
            $q=$this->db->prepare('UPDATE office_building_dwellings SET dwelling_code=?,house_number=?,floor_label=?,housing_type_id=? WHERE id=? AND building_id=?');
            $q->execute([$code,$number,$floor===''?null:$floor,$type,$dwellingId,$buildingId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public static function revision(array $row): string
    {
        $values=[];
        foreach (['id','dwelling_code','house_number','floor_label','housing_type_id','active'] as $key) $values[]=$row[$key]===null?null:(string)$row[$key];
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }

    private static function field(array $input,string $key,int $max,bool $required): string
    {
        $v=$input[$key]??'';
        if (!is_string($v)||strlen(trim($v))>$max||($required&&trim($v)==='')) throw new InvalidArgumentException('Ongeldig veld: '.$key);
        return trim($v);
    }
}
