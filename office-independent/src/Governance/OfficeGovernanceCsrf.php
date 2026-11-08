<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;
final class OfficeGovernanceCsrf
{
    public static function token(string $session,string $module,int $project,int $building,string $secret,int $now): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$session) || strlen($secret)<32 || !in_array($module,OfficeGovernanceService::MODULES,true)) throw new \InvalidArgumentException('Verified session required.');
        $expires=$now+1800;
        return $expires.'.'.hash_hmac('sha256',"office-governance:v1:$session:$module:$project:$building:$expires",$secret);
    }
    public static function verify(mixed $token,string $session,string $module,int $project,int $building,string $secret,int $now): bool
    {
        if (!is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/D',$token,$m)) return false;
        $expires=(int)$m[1];
        if ($expires<$now || $expires>$now+1800) return false;
        try { return hash_equals(self::token($session,$module,$project,$building,$secret,$expires-1800),$token); }
        catch (\InvalidArgumentException) { return false; }
    }
}
