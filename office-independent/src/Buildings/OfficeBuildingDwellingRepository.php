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

    public function importCsv(int $operatorId,int $buildingId,string $csv): int
    {
        if (strlen($csv)>250000 || !preg_match('//u',$csv)) throw new InvalidArgumentException('Ongeldig CSV-bestand.');
        $stream=fopen('php://memory','r+');
        fwrite($stream,preg_replace('/^\xEF\xBB\xBF/','',$csv));
        rewind($stream);
        $header=fgetcsv($stream,0,';','"','');
        if ($header!==['woningcode','huisnummer','verdieping','woningtype']) {fclose($stream);throw new InvalidArgumentException('Ongeldige CSV-kolommen.');}
        $rows=[];
        while (($row=fgetcsv($stream,0,';','"',''))!==false) {
            if ($row===[null]) continue;
            if (count($row)!==4 || count($rows)>=1000) {fclose($stream);throw new InvalidArgumentException('Ongeldige rij of meer dan 1000 woningen.');}
            $rows[]=$row;
        }
        fclose($stream);
        if (!$rows) throw new InvalidArgumentException('Geen woningen.');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 FOR UPDATE');
            $q->execute([$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen schrijfrecht.');
            $q=$this->db->prepare('SELECT b.id FROM office_buildings b JOIN office_building_register_grants g ON g.building_id=b.id WHERE b.id=? AND b.active=1 AND g.operator_id=? FOR UPDATE');
            $q->execute([$buildingId,$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen gebouwtoegang.');
            $typeQuery=$this->db->prepare('SELECT id FROM office_housing_types WHERE building_id=? AND code=? FOR UPDATE');
            $insert=$this->db->prepare('INSERT INTO office_building_dwellings (building_id,housing_type_id,dwelling_code,house_number,floor_label) VALUES (?,?,?,?,?)');
            foreach ($rows as $row) {
                $code=self::field(['v'=>$row[0]],'v',100,true);
                $number=self::field(['v'=>$row[1]],'v',40,true);
                $floor=self::field(['v'=>$row[2]],'v',80,false);
                $typeCode=self::field(['v'=>$row[3]],'v',100,false);
                $type=null;
                if ($typeCode!=='') {
                    $typeQuery->execute([$buildingId,$typeCode]);
                    $type=$typeQuery->fetchColumn();
                    if ($type===false) throw new DomainException('Onbekend woningtype.');
                }
                $insert->execute([$buildingId,$type,$code,$number,$floor===''?null:$floor]);
            }
            $this->db->commit();
            return count($rows);
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
