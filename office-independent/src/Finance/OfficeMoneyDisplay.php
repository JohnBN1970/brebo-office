<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use InvalidArgumentException;

/** Shared display-only money formatting; never converts or rounds stored amounts. */
final class OfficeMoneyDisplay
{
    private const SYMBOLS=['EUR'=>'€','USD'=>'$','GBP'=>'£'];
    public static function formatCents(int $cents, string $currency='EUR', string $locale='nl-NL'): string
    {
        if (!isset(self::SYMBOLS[$currency])) throw new InvalidArgumentException('Niet-ondersteunde valuta.');
        if (!in_array($locale,['nl-NL','en-GB'],true)) throw new InvalidArgumentException('Niet-ondersteunde notatie.');
        $negative=$cents<0;
        $absolute=(string)$cents;
        if ($negative) $absolute=substr($absolute,1); // safe for PHP_INT_MIN
        $absolute=str_pad($absolute,3,'0',STR_PAD_LEFT);
        $whole=substr($absolute,0,-2);
        $fraction=substr($absolute,-2);
        $grouped=preg_replace('/\B(?=(\d{3})+(?!\d))/', $locale==='nl-NL'?'.':',', $whole);
        $number=$grouped.($locale==='nl-NL'?',':'.').$fraction;
        return ($negative?'-':'').self::SYMBOLS[$currency].' '.$number;
    }
}
