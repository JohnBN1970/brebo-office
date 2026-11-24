<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use DomainException;
use PDO;

/** Persisted organization display defaults. Never changes document currency or stored cents. */
final class OfficeFinancialDisplaySettings
{
    public function __construct(private readonly PDO $db) {}

    public function read(): array
    {
        $stmt=$this->db->query('SELECT currency_code, locale_code FROM office_financial_display_settings WHERE id = 1');
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new DomainException('Financiële instellingen ontbreken.');
        self::validate((string)$row['currency_code'],(string)$row['locale_code']);
        return ['currency'=>$row['currency_code'],'locale'=>$row['locale_code']];
    }

    public function save(string $currency,string $locale,int $operatorId): void
    {
        self::validate($currency,$locale);
        if ($operatorId<1) throw new DomainException('Bevoegde beheerder vereist.');
        $stmt=$this->db->prepare('UPDATE office_financial_display_settings SET currency_code=?, locale_code=?, updated_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=1');
        $stmt->execute([$currency,$locale,$operatorId]);
        if ($stmt->rowCount()===0) {
            $check=$this->db->query('SELECT 1 FROM office_financial_display_settings WHERE id=1')->fetchColumn();
            if ($check===false) throw new DomainException('Financiële instellingen ontbreken.');
        }
    }

    public static function validate(string $currency,string $locale): void
    {
        if (!in_array($currency,['EUR','USD','GBP'],true)
            || !in_array($locale,['nl-NL','en-GB'],true)) {
            throw new DomainException('Niet-ondersteunde valuta of notatie.');
        }
    }
}
