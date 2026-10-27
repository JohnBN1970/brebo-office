<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
final class OnSiteHoursPresentation {
  public static function duration(int $seconds):string {
    $minutes=intdiv($seconds,60);$hours=intdiv($minutes,60);$rest=$minutes%60;
    return $hours>0?$hours.' uur'.($rest>0?' '.$rest.' min':''):$rest.' min';
  }
  public static function moment(string $utc):string {
    return (new \DateTimeImmutable($utc,new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i');
  }
  public static function correction(string $hours):?int {
    $hours=str_replace(',','.',trim($hours));if($hours==='')return null;
    if(!preg_match('/^(0|[1-9]|1[0-9]|2[0-4])(?:\.([0-9]{1,2}))?$/D',$hours,$parts))throw new \InvalidArgumentException('invalid_hours_correction');
    $hundredths=(int)$parts[1]*100+(int)str_pad($parts[2]??'',2,'0');
    if($hundredths>2400)throw new \InvalidArgumentException('invalid_hours_correction');
    return $hundredths*36;
  }
}
