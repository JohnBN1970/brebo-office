<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;
use Brebo\Office\Finance\OfficeFinanceAccess;

/** Reads native purchase invoice evidence without mutating Finance or trusting legacy IDs. */
final class MaterialInvoiceSource
{
    public function __construct(private readonly PDO $db){}
    public function read(string $operatorId,int $invoiceId,array $lineIds=[]):array
    {
        if($invoiceId<1||count($lineIds)>500)throw new \InvalidArgumentException('Ongeldige factuurselectie.');
        foreach($lineIds as$id)if(!is_int($id)||$id<1)throw new \InvalidArgumentException('Ongeldige factuurregel.');
        $access=new OfficeFinanceAccess($this->db,$operatorId);
        if(!$access->allows('read'))throw new \DomainException('Geen Finance-leesrecht.');
        $projects=array_fill_keys(array_map(static fn(array $p):int=>(int)$p['office_project_id'],$access->draftProjects()),true);
        $q=$this->db->prepare('SELECT id,supplier_id,supplier_name,invoice_number,invoice_date,currency,status,updated_at FROM office_finance_invoices WHERE id=?');$q->execute([$invoiceId]);$invoice=$q->fetch(PDO::FETCH_ASSOC);
        if(!$invoice||trim((string)$invoice['supplier_id'])===''||trim((string)$invoice['supplier_name'])==='')throw new \OutOfBoundsException('Inkoopfactuur niet beschikbaar.');
        $q=$this->db->prepare('SELECT id,line_number,description,quantity,unit,unit_price,net_amount,office_project_id FROM office_finance_invoice_lines WHERE invoice_id=? ORDER BY line_number,id');$q->execute([$invoiceId]);$lines=$q->fetchAll(PDO::FETCH_ASSOC);
        if($lines===[]||count($lines)>500)throw new \DomainException('Factuurregels ontbreken of overschrijden de importlimiet.');
        foreach($lines as$line){
            if($line['office_project_id']===null){if(!$access->allows('unassigned_read'))throw new \OutOfBoundsException('Inkoopfactuur niet beschikbaar.');}
            elseif(!isset($projects[(int)$line['office_project_id']]))throw new \OutOfBoundsException('Inkoopfactuur niet beschikbaar.');
        }
        if($lineIds!==[]){
            $selected=array_fill_keys($lineIds,true);$lines=array_values(array_filter($lines,static fn(array $line):bool=>isset($selected[(int)$line['id']])));
            if(count($lines)!==count($selected))throw new \OutOfBoundsException('Factuurregel hoort niet bij deze factuur.');
        }
        $rows=[];
        foreach($lines as$line){
            if(bccomp((string)$line['quantity'],'0',6)<=0||bccomp((string)$line['net_amount'],'0',6)<=0)continue;
            $rows[]=['supplier'=>(string)$invoice['supplier_name'],'article_code'=>'','description'=>(string)$line['description'],
                'quantity'=>(string)$line['quantity'],'unit'=>(string)$line['unit'],
                'price'=>bcdiv((string)$line['net_amount'],(string)$line['quantity'],6),'price_quantity'=>'1','discount_percentage'=>'0',
                'price_is_ex_vat'=>true,'currency'=>(string)$invoice['currency'],'price_date'=>(string)$invoice['invoice_date'],'number_format'=>'international',
                'source_row'=>(string)$line['id'],'source_fragment'=>'Factuur '.$invoice['invoice_number'].', regel '.$line['line_number'].': '.$line['description'],
                'approved'=>false,'issues'=>['Controleer dat dit materiaal is. Vul de artikelcode uit de oorspronkelijke factuur in.']];
        }
        $source=['kind'=>'native_purchase_invoice','invoice_id'=>$invoiceId,'line_ids'=>array_map(static fn(array $row):int=>(int)$row['id'],$lines),
            'reference'=>'office:purchase-invoice:'.$invoiceId,'invoice_snapshot'=>['invoice'=>$invoice,'lines'=>$lines],'sha256'=>hash('sha256',json_encode([$invoice,$lines],JSON_THROW_ON_ERROR))];
        return ['source'=>$source,'rows'=>$rows,'issues'=>$rows===[]?['Geen positieve materiaalprijzen uit deze factuurregels beschikbaar.']:[]];
    }
    public function verify(string $operatorId,array $expected):void
    {
        if(($expected['kind']??null)!=='native_purchase_invoice')throw new \InvalidArgumentException('Ongeldige factuurbron.');
        $current=$this->read($operatorId,(int)($expected['invoice_id']??0),(array)($expected['line_ids']??[]));
        if(!hash_equals((string)($expected['sha256']??''),$current['source']['sha256']))throw new \DomainException('Factuur is gewijzigd sinds de materiaalreview. Lees de actuele factuur opnieuw in.');
    }
}
