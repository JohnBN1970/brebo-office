<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use DomainException;

final class OfficePdokAddress {
    public static function fromDocument(array $doc): array {
        if (($doc['bron'] ?? null) !== 'BAG' || ($doc['type'] ?? null) !== 'adres') {
            throw new DomainException('Official BAG address required');
        }
        $id = $doc['nummeraanduiding_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[0-9]{16}$/D', $id)) {
            throw new DomainException('Invalid BAG address identity');
        }
        $street = trim((string)($doc['straatnaam'] ?? ''));
        $number = trim((string)($doc['huis_nlt'] ?? ''));
        $postal = strtoupper(str_replace(' ', '', (string)($doc['postcode'] ?? '')));
        $city = trim((string)($doc['woonplaatsnaam'] ?? ''));
        $point = (string)($doc['centroide_ll'] ?? '');
        if ($street === '' || $number === '' || $city === '' ||
            !preg_match('/^[0-9]{4}[A-Z]{2}$/D', $postal) ||
            !preg_match('/^POINT\\s*\\(\\s*([0-9.]+)\\s+([0-9.]+)\\s*\\)$/D', $point, $m)) {
            throw new DomainException('Incomplete BAG address');
        }
        $lon = (float)$m[1]; $lat = (float)$m[2];
        if ($lat < 50 || $lat > 54 || $lon < 3 || $lon > 8) {
            throw new DomainException('Invalid BAG coordinates');
        }
        return ['bag_id'=>$id,'street_address'=>$street.' '.$number,
            'postal_code'=>substr($postal,0,4).' '.substr($postal,4),
            'city'=>$city,'latitude'=>$lat,'longitude'=>$lon];
    }
}
