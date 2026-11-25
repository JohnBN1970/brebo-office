<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
use DomainException;

final class OfficeAcceptedBuildingScope {
    /** Both inputs must contain explicit scope; persisted input must be loaded by the acceptance verifier. */
    public static function verify(array $persisted,array $submitted): void {
        if (self::index($persisted)!==self::index($submitted)) {
            throw new DomainException('Building scope differs from accepted calculation');
        }
    }
    private static function index(array $lines): array {
        $result=[];
        foreach ($lines as $line) {
            if (!is_array($line) || !is_string($line['line_id']??null) || $line['line_id']==='' || !array_key_exists('building_id',$line)) throw new DomainException('Explicit accepted building scope required');
            $id=$line['line_id'];
            $building=$line['building_id'];
            if (array_key_exists($id,$result) || ($building!==null && (!is_int($building) || $building<1))) throw new DomainException('Invalid accepted building scope');
            $result[$id]=$building;
        }
        ksort($result,SORT_STRING);
        return $result;
    }
}
