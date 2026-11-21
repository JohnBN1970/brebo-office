<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;
/** Server-owned per-process policy; fail closed when unset. Never trust role preview or request data. */
final class OfficeApprovalPolicy {
    public const FOUR_EYES='four_eyes';
    public const SINGLE='single';
    public const PROCESSES=['onsite_hours','external_hours','calculation_release','working_budget','purchase_order','invoice_payment','project_start'];
    public static function resolve(string $process, ?string $storedMode):string {
        if (!in_array($process,self::PROCESSES,true)) throw new \DomainException('Unknown approval process');
        if ($storedMode===null) return self::FOUR_EYES;
        if (!in_array($storedMode,[self::FOUR_EYES,self::SINGLE],true)) throw new \DomainException('Unknown approval mode');
        return $storedMode;
    }
    public static function assertReviewers(string $mode,string $first,?string $second):void {
        if (!in_array($mode,[self::FOUR_EYES,self::SINGLE],true)) throw new \DomainException('Unknown approval mode');
        $first=trim($first);$second=$second===null?null:trim($second);
        if ($first==='') throw new \DomainException('Authenticated first reviewer required');
        if ($mode===self::FOUR_EYES && ($second===null||$second===''||hash_equals($first,$second))) throw new \DomainException('Independent second reviewer required');
    }
}
