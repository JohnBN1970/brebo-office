<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;

final class OfficeSalesTransport {
  public static function send(string $operation, array $payload): array {
    if (getenv('OFFICE_SALES_SEND_ENABLED')!=='enabled' || !function_exists('curl_init')) throw new \RuntimeException('Verzenden is nog niet geconfigureerd.');
    $paths=['invoice'=>'/v1/accounting/sales-invoices','reminder'=>'/v1/accounting/sales-reminders'];
    if (!isset($paths[$operation])) throw new \InvalidArgumentException('Ongeldige verzendactie.');
    $secret=getenv('OFFICE_INTEGRATION_SHARED_SECRET');
    if (!is_string($secret) || strlen($secret)<24) throw new \RuntimeException('Verzendkoppeling ontbreekt.');
    $path=$paths[$operation]; $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $bytes=random_bytes(16); $bytes[6]=chr((ord($bytes[6])&15)|64); $bytes[8]=chr((ord($bytes[8])&63)|128);
    $uuid=vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4)); $timestamp=(string)time();
    $signature=hash_hmac('sha256',implode("\n",['POST',$path,hash('sha256',$body),$timestamp,$uuid]),$secret);
    $curl=curl_init('https://brebo-integration-api.john-boon.workers.dev'.$path);
    $response='';
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-BREBO-Request-Id: '.$uuid,'X-BREBO-Timestamp: '.$timestamp,'X-BREBO-Signature: v1='.$signature],CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$response): int { if (strlen($response)+strlen($chunk)>65536) return 0; $response.=$chunk; return strlen($chunk); }]);
    $ok=curl_exec($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if ($ok===false || $code!==200) throw new \RuntimeException('Verzending niet bevestigd.');
    $result=json_decode($response,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($result) || ($result['status']??'')!=='ok') throw new \RuntimeException('Ongeldige verzendbevestiging.');
    return $result;
  }
}
