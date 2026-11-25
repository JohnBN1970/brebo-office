<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use Closure;
use DomainException;
use PDO;

require_once __DIR__.'/OfficeBuildingRepository.php';

/** Shared creation boundary; callers must supply verified context authorization. */
final class OfficeBuildingCreateService {
    public function __construct(private PDO $db) {}

    public function create(array $input, Closure $authorized): string {
        if ($authorized() !== true) {
            throw new DomainException('building_create_forbidden');
        }
        $clean=[];
        foreach (['building_name','street_address','postal_code','city'] as $field) {
            $value=$input[$field]??'';
            if (!is_string($value)) throw new DomainException('invalid_building_input');
            $clean[$field]=trim($value);
        }
        if ($clean['street_address']!=='' && $clean['postal_code']!=='') {
            $q=$this->db->prepare("SELECT id FROM office_buildings WHERE UPPER(REPLACE(postal_code,' ',''))=? AND LOWER(TRIM(street_address))=LOWER(?) LIMIT 1");
            $q->execute([strtoupper(str_replace(' ','',$clean['postal_code'])),$clean['street_address']]);
            if ($q->fetchColumn()!==false) throw new DomainException('building_address_candidate_exists');
        }
        return (new OfficeBuildingRepository($this->db))->create($clean);
    }
}
