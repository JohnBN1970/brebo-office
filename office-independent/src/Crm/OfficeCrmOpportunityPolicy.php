<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Framework-free commercial rules. No project is required before an accepted order. */
final class OfficeCrmOpportunityPolicy
{
    public const SERVICES=['execution'=>'Uitvoering','advice'=>'Advies','supply'=>'Levering'];
    public const ACTIVITIES=['call'=>'Bellen','meeting'=>'Afspraak','follow_up'=>'Opvolging','opname'=>'Opname'];
    public const OPNAMEN=['measurement'=>'Inmeting','condition'=>'Conditieopname','technical'=>'Technische opname'];
    public static function details(array $input): array
    {
        $relation=self::id($input['relation_id']??null);
        $contact=($input['contact_id']??'')===''?null:self::id($input['contact_id']);
        $value=self::text($input,'expected_value',20,false);
        if($value!==''&&!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:[.,][0-9]{1,2})?$/D',$value))throw new \InvalidArgumentException('Vul een positief bedrag in met maximaal twee decimalen.');
        $date=self::text($input,'expected_close_date',10,false);
        if($date!=='')self::date($date);
        $service=self::text($input,'service_type',24,false)?:'execution';
        if(!isset(self::SERVICES[$service]))throw new \InvalidArgumentException('Onbekende dienst.');
        return ['relation_id'=>$relation,'contact_id'=>$contact,'title'=>self::text($input,'title',255),
            'description'=>self::text($input,'description',12000,false),'service_type'=>$service,
            'expected_value'=>$value===''?null:str_replace(',','.',$value),'expected_close_date'=>$date===''?null:$date];
    }
    public static function activity(array $input): array
    {
        $type=self::text($input,'activity_type',24);
        if(!isset(self::ACTIVITIES[$type]))throw new \InvalidArgumentException('Onbekende activiteit.');
        $opname=self::text($input,'opname_type',24,false);
        if($type==='opname'){
            if(!isset(self::OPNAMEN[$opname]))throw new \InvalidArgumentException('Kies het soort opname.');
            $type.=':'.$opname;
        }
        $at=self::text($input,'scheduled_at',19);
        $at=str_replace('T',' ',$at);if(strlen($at)===16)$at.=':00';
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$at,new \DateTimeZone('Europe/Amsterdam'));
        if(!$date||$date->format('Y-m-d H:i:s')!==$at)throw new \InvalidArgumentException('Ongeldige afspraakdatum.');
        return ['activity_type'=>$type,'title'=>self::text($input,'title',255),'scheduled_at'=>$at];
    }
    public static function transition(string $from,string $to,string $note): array
    {
        if(!OfficeCrmFunnel::canTransition($from,$to))throw new \DomainException('Deze faseovergang is niet toegestaan.');
        $note=trim($note);
        if(strlen($note)>4000)throw new \InvalidArgumentException('Toelichting is te lang.');
        if(in_array($to,['won','lost'],true)&&$note==='')throw new \InvalidArgumentException($to==='won'?'Leg de opdrachtbevestiging of akkoordreferentie vast.':'Leg de reden van niet gunnen vast.');
        return ['stage'=>$to,'status'=>in_array($to,['won','lost'],true)?'closed':'open','outcome_note'=>$note];
    }
    public static function key(mixed $key): string
    {
        if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new \InvalidArgumentException('Ongeldige formuliercode.');
        return $key;
    }
    public static function id(mixed $value,int $min=1): int
    {
        if(!is_string($value)&&!is_int($value))throw new \InvalidArgumentException('Ongeldige selectie.');
        $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min]]);
        if(!is_int($id))throw new \InvalidArgumentException('Ongeldige selectie.');return $id;
    }
    private static function text(array $input,string $key,int $limit,bool $required=true): string
    {
        $value=$input[$key]??'';
        if(!is_string($value)||strlen(trim($value))>$limit||($required&&trim($value)===''))throw new \InvalidArgumentException('Controleer het veld '.$key.'.');
        return trim($value);
    }
    private static function date(string $s): void
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s);
        if(!$d||$d->format('Y-m-d')!==$s)throw new \InvalidArgumentException('Ongeldige verwachte opdrachtdatum.');
    }
}
