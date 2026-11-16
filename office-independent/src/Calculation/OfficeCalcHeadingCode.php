<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Canonical classification for calculation headings. Classification is independent
 * of the cost type: NL-SfB, STABU and free codes are never labour/material types.
 * A heading is not a priced calculation line.
 */
final class OfficeCalcHeadingCode
{
    public const SYSTEMS = ['nlsfb', 'stabu', 'free'];

    public static function normalize(string $system, string $code, string $title): array
    {
        $system=strtolower(trim($system));
        $code=trim($code);
        $title=trim($title);
        if (!in_array($system,self::SYSTEMS,true)) {
            throw new DomainException('Onbekend classificatiestelsel.');
        }
        if ($title==='' || mb_strlen($title,'UTF-8')>200) {
            throw new DomainException('Ongeldige hoofdstuktitel.');
        }
        if ($code==='' || strlen($code)>40 || preg_match('/[\x00-\x1F\x7F]/',$code)) {
            throw new DomainException('Ongeldige hoofdstukcode.');
        }
        if ($system==='nlsfb' && !preg_match('/^[0-9]{2}(?:[.][0-9]{1,4}){0,3}$/D',$code)) {
            throw new DomainException('Ongeldige NL-SfB-code.');
        }
        if ($system==='stabu' && !preg_match('/^[0-9]{2}(?:[.][0-9]{1,4}){0,3}$/D',$code)) {
            throw new DomainException('Ongeldige STABU-code.');
        }
        if ($system==='free' && !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\/-]{0,39}$/uD',$code)) {
            throw new DomainException('Ongeldige vrije code.');
        }
        return ['system'=>$system,'code'=>$code,'title'=>$title];
    }

    public static function key(array $heading): string
    {
        $value=self::normalize((string)($heading['system']??''),(string)($heading['code']??''),(string)($heading['title']??''));
        return $value['system'].':'.$value['code'];
    }
}
