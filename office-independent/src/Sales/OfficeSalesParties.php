<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
use PDO;
final class OfficeSalesParties {
  public static function contacts(PDO $db,int $relation): array{
    try{$q=$db->prepare("SELECT p.id,p.given_name,p.family_name,p.email,c.is_primary FROM office_crm_contact_people p JOIN office_crm_relation_contacts c ON c.contact_id=p.id WHERE c.relation_id=? AND p.status='active' ORDER BY c.is_primary DESC,p.family_name,p.given_name,p.id");$q->execute([$relation]);return $q->fetchAll(PDO::FETCH_ASSOC);}catch(\PDOException $e){if(OfficeSalesApproval::enabled())throw $e;return [];}
  }
  public static function billing(PDO $db,int $project): array{
    try{$q=$db->prepare("SELECT DISTINCT a.relation_id FROM office_crm_assignment_parties a JOIN office_crm_relations r ON r.id=a.relation_id WHERE a.assignment_type IN ('office_project','project') AND a.assignment_id=? AND a.party_role='invoice_debtor' AND r.status='active'");$q->execute([(string)$project]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);if(count($ids)!==1)return ['relation_id'=>'','contact_id'=>''];$relation=(int)$ids[0];$contacts=self::contacts($db,$relation);$primary=array_values(array_filter($contacts,static fn($c)=>(int)$c['is_primary']===1));$contact=count($primary)===1?(int)$primary[0]['id']:0;return ['relation_id'=>$relation,'contact_id'=>$contact];}catch(\PDOException $e){if(OfficeSalesApproval::enabled())throw $e;return ['relation_id'=>'','contact_id'=>''];}
  }
  public static function snapshot(PDO $db,array $relation,mixed $contactId): array{
    $keys=['id','display_name','legal_name','email','street','postal_code','city','country_code','vat_number'];$c=[];foreach($keys as $key)$c[$key]=$relation[$key]??'';$c['general_email']=$c['email'];$c['email']=trim((string)($relation['invoice_email']??''))?:$c['email'];$c['offer_email']=$relation['offer_email']??'';
    $billing=trim((string)($relation['billing_street']??'').(string)($relation['billing_postal_code']??'').(string)($relation['billing_city']??''));if($billing!=='')foreach(['street','postal_code','city','country_code'] as $field)$c[$field]=$relation['billing_'.$field]??'';
    $contacts=self::contacts($db,(int)$relation['id']);$selected=(int)$contactId;if($selected===0){$primary=array_values(array_filter($contacts,static fn($v)=>(int)$v['is_primary']===1));if(count($primary)===1)$selected=(int)$primary[0]['id'];}
    $c['contact_id']=0;$c['contact_name']='';if($selected>0){$match=array_values(array_filter($contacts,static fn($v)=>(int)$v['id']===$selected));if(count($match)!==1)throw new \DomainException('De contactpersoon hoort niet bij deze actieve debiteur.');$c['contact_id']=$selected;$c['contact_name']=trim($match[0]['given_name'].' '.$match[0]['family_name']);}
    return $c;
  }
}
