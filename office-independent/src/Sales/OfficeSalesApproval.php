<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
final class OfficeSalesApproval {
  public static function enabled(): bool{return getenv('OFFICE_FINANCE_CENTER_ENABLED')==='enabled';}
  public static function hash(array $row): string{$keys=['id','revision','relation_id','office_project_id','customer_json','invoice_date','due_date','reference','lines_json','net_cents','vat_cents','gross_cents','price_mode','labor_cents','g_rate_units','g_cents','g_iban','source_contract_id','source_term_id','term_percent_units','source_snapshot'];$v=[];foreach($keys as $k)$v[$k]=isset($row[$k])?(string)$row[$k]:null;return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
  public static function recipient(string $recipient,string $message): void{if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||strlen($recipient)>254||preg_match('/[\r\n]/',$recipient)||trim($message)===''||strlen($message)>10000)throw new \InvalidArgumentException('Controleer e-mailadres en bericht.');}
}
