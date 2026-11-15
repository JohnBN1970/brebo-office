<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/** Office-owned reusable labor rates; never overwrite historical line rates. */
final class OfficeCalcLaborRateCatalog
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query("SELECT code,description,hourly_rate,active FROM office_calc_labor_rates ORDER BY active DESC,code ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolve(string $code): array
    {
        $code=self::normalizeCode($code);
        $q=$this->db->prepare("SELECT code,description,hourly_rate FROM office_calc_labor_rates WHERE code=? AND active=1");
        $q->execute([$code]);
        $rate=$q->fetch(PDO::FETCH_ASSOC);
        if(!$rate)throw new DomainException('Onbekende of inactieve tariefcode.');
        return $rate;
    }

    public function save(string $code,string $description,string $rate,bool $active): void
    {
        $code=self::normalizeCode($code);
        $description=trim($description);
        if($description===''||mb_strlen($description)>160)throw new DomainException('Ongeldige tariefomschrijving.');
        if(!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,4})?$/D',$rate))throw new DomainException('Ongeldig uurtarief.');
        $q=$this->db->prepare("INSERT INTO office_calc_labor_rates(code,description,hourly_rate,active) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE description=VALUES(description),hourly_rate=VALUES(hourly_rate),active=VALUES(active)");
        $q->execute([$code,$description,$rate,$active?1:0]);
    }

    private static function normalizeCode(string $code): string
    {
        $code=strtoupper(trim($code));
        if(!preg_match('/^[A-Z][A-Z0-9_-]{0,15}$/D',$code))throw new DomainException('Tariefcode moet 1-16 letters/cijfers bevatten.');
        return $code;
    }
}
