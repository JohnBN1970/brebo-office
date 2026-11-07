<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
final class OfficeSalesView {
  private static function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
  public static function money(int $cents): string { return '€ '.number_format(intdiv($cents,100),0,',','.').','.sprintf('%02d',$cents%100); }
  public static function render(array $rows, ?array $invoice, array $customers, array $projects, array $history, string $csrf, bool $canWrite, bool $canSend, string $today, string $error='', bool $new=false, bool $canRemind=false, array $receipts=[]): string {
    $e=self::e(...); $money=self::money(...); $base='/office-test/finance/verkoop';
    $labels=['draft'=>'Concept','sending'=>'Verzending gestart','sent'=>'Verzonden','overdue'=>'Vervallen','partially_paid'=>'Deels betaald','paid'=>'Betaald','reminder_sending'=>'Herinnering wordt verstuurd','reconciliation_required'=>'Verzenduitkomst controleren'];
    $h='<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopfacturen · BREBO Office</title><style>body{margin:0;background:#f3f6fa;color:#17283a;font:16px system-ui}main{max-width:1180px;margin:auto;padding:24px}nav{display:flex;gap:20px;margin-bottom:24px}section{background:white;padding:24px;border:1px solid #dce4ed;border-radius:10px;margin:20px 0}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:10px;border-bottom:1px solid #dce4ed}input,select,textarea,button{font:inherit;padding:8px;max-width:100%;box-sizing:border-box}label{display:block;margin:12px 0}textarea{width:100%;min-height:120px}.scroll{overflow:auto}.lines input{width:100%}.lines th:first-child{width:45%}.notice{padding:12px;background:#fff0b3}a{color:#155c87}.error{background:#ffe3e3;padding:14px}button{cursor:pointer;background:#175a91;color:white;border:0;border-radius:5px}:focus-visible{outline:3px solid #e9a72c;outline-offset:3px}</style></head><body><div class="notice">Office-test · Verkoopfacturen uit BREBO · Verzenden na controle</div><main><nav><a href="/office-test/finance">Finance-overzicht</a><a href="'.$base.'">Verkoopfacturen</a><a href="/office-test/finance/betaalcentrum">Betaalcentrum</a></nav><h1>Verkoopfacturen</h1>';
    if ($error!=='') $h.='<p class="error" role="alert">'.$e($error).'</p>';
    if ($invoice!==null || $new) {
      $row=$invoice??['id'=>0,'revision'=>0,'status'=>'draft','invoice_date'=>$today,'due_date'=>(new \DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d'),'relation_id'=>'','office_project_id'=>'','reference'=>'','lines_json'=>'[]'];
      $status=OfficeSalesService::status($row,$today); $lines=json_decode($row['lines_json'],true,32,JSON_THROW_ON_ERROR);
      $h.='<section><h2>'.$e($row['invoice_number']??('Concept #'.$row['id'])).'</h2><p><strong>'.$e($labels[$status]??$status).'</strong></p>';
      if ($row['status']==='draft' && $canWrite) {
        $h.='<form method="post"><input type="hidden" name="csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.(int)$row['id'].'"><input type="hidden" name="revision" value="'.(int)$row['revision'].'"><label>Relatie <select name="relation_id" required><option value="">Kies debiteur</option>';
        foreach ($customers as $c) $h.='<option value="'.(int)$c['id'].'" '.((string)$c['id']===(string)$row['relation_id']?'selected':'').'>'.$e($c['display_name']).'</option>';
        $h.='</select></label><label>Project <select name="office_project_id"><option value="">Losse factuur</option>';
        foreach ($projects as $p) $h.='<option value="'.(int)$p['id'].'" '.((string)$p['id']===(string)$row['office_project_id']?'selected':'').'>'.$e($p['title']).'</option>';
        $h.='</select></label><label>Factuurdatum <input type="date" name="invoice_date" required value="'.$e($row['invoice_date']).'"></label><label>Vervaldatum <input type="date" name="due_date" required value="'.$e($row['due_date']).'"></label><label>Referentie <input name="reference" maxlength="255" value="'.$e($row['reference']).'"></label><div class="scroll"><table class="lines"><thead><tr><th>Omschrijving</th><th>Aantal</th><th>Prijs excl. btw</th><th>Btw %</th></tr></thead><tbody>';
        for ($i=0;$i<max(5,count($lines)+2);$i++) {
          $l=$lines[$i]??[]; $h.='<tr><td><input aria-label="Omschrijving regel '.($i+1).'" name="lines['.$i.'][description]" maxlength="1000" value="'.$e($l['description']??'').'"></td><td><input aria-label="Aantal regel '.($i+1).'" name="lines['.$i.'][quantity]" inputmode="decimal" value="'.$e($l['quantity']??'1').'"></td><td><input aria-label="Prijs regel '.($i+1).'" name="lines['.$i.'][price]" inputmode="decimal" value="'.$e($l['price']??'').'"></td><td><select aria-label="Btw regel '.($i+1).'" name="lines['.$i.'][vat]">';
          foreach (['21','9','0'] as $rate) $h.='<option '.(($l['vat']??'21')===$rate?'selected':'').'>'.$rate.'</option>';
          $h.='</select></td></tr>';
        }
        $h.='</tbody></table></div><p>0% is uitsluitend het normale nultarief. Verlegde of vrijgestelde btw is nog niet ondersteund.</p><button>Concept opslaan</button></form>';
      } else {
        $h.='<p>Factuurdatum '.$e($row['invoice_date']).' · Vervaldatum '.$e($row['due_date']).'</p><div class="scroll"><table><thead><tr><th>Omschrijving</th><th>Aantal</th><th>Excl. btw</th><th>Btw</th></tr></thead><tbody>';
        foreach ($lines as $line) $h.='<tr><td>'.$e($line['description']).'</td><td>'.$e($line['quantity']).'</td><td>'.$money((int)$line['net_cents']).'</td><td>'.$money((int)$line['vat_cents']).'</td></tr>';
        $h.='</tbody></table></div>';
      }
      if ($invoice!==null) {
        $customer=json_decode($row['customer_json'],true,32,JSON_THROW_ON_ERROR);
        $h.='<p><strong>Debiteur:</strong> '.$e($customer['legal_name']?:$customer['display_name']).' · '.$e($customer['street']).' · '.$e($customer['postal_code']).' '.$e($customer['city']).'</p><p>Excl. btw '.$money((int)$row['net_cents']).' · Btw '.$money((int)$row['vat_cents']).' · <strong>Totaal '.$money((int)$row['gross_cents']).'</strong></p>';
        if ($canWrite && in_array($status,['draft','overdue'],true)) {
          $operation=$status==='draft'?'invoice':'reminder'; $caption=$operation==='invoice'?'Factuur verzenden':'Herinnering versturen';
          $message=$operation==='invoice'?"Geachte heer/mevrouw,\n\nHierbij ontvangt u onze factuur.\n\nMet kleurrijke groet,\nBREBO Bouw en Advies B.V.":"Geachte heer/mevrouw,\n\nVolgens onze administratie staat factuur ".$row['invoice_number'].' met vervaldatum '.$row['due_date'].' nog open voor '.$money((int)$row['gross_cents']-(int)$row['paid_cents']).". Wilt u deze voldoen?\n\nMet kleurrijke groet,\nBREBO Bouw en Advies B.V.";
          $h.='<h3>'.$caption.'</h3>';
          if (!$canSend || ($operation==='reminder' && !$canRemind)) $h.='<p role="status">Verzenden wordt beschikbaar nadat de verzendkoppeling en btw-koppeling zijn gecontroleerd.</p>';
          else $h.='<form method="post"><input type="hidden" name="csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="'.$operation.'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><input type="hidden" name="revision" value="'.(int)$row['revision'].'"><label>Verzenden naar <input type="email" required name="recipient" value="'.$e($customer['email']).'"></label><label>Bericht <textarea name="message" maxlength="10000">'.$e($message).'</textarea></label><label><input type="checkbox" name="confirmed" value="yes" required> Ontvanger, factuurregels, btw en bedrag gecontroleerd</label><button>'.$caption.'</button></form>';
        }
        if ($canWrite && in_array($status,['sent','overdue','partially_paid'],true)) {
          $outstanding=(int)$row['gross_cents']-(int)$row['paid_cents'];
          $h.='<h3>Ontvangst handmatig vastleggen</h3><p>Leg alleen een ontvangen bedrag vast dat je in de bankadministratie hebt gecontroleerd. Dit voert geen bankbetaling uit.</p><form method="post"><input type="hidden" name="csrf" value="'.$e($csrf).'"><input type="hidden" name="action" value="receipt"><input type="hidden" name="id" value="'.(int)$row['id'].'"><input type="hidden" name="revision" value="'.(int)$row['revision'].'"><label>Ontvangen bedrag <input name="receipt_amount" inputmode="decimal" required value="'.intdiv($outstanding,100).'.'.sprintf('%02d',$outstanding%100).'"></label><label>Ontvangstdatum <input type="date" name="receipt_date" required value="'.$e($today).'"></label><label>Bankreferentie <input name="evidence_reference" maxlength="128" required></label><label><input type="checkbox" name="confirmed" value="yes" required> Ontvangst en factuur gecontroleerd in de bankadministratie</label><button>Ontvangst vastleggen</button></form>';
        }
        $h.='<h3>Geregistreerde ontvangsten</h3>';
        foreach ($receipts as $receipt) $h.='<p>'.$e($receipt['receipt_date']).' · '.$money((int)$receipt['amount_cents']).' · '.$e($receipt['evidence_reference']).' · Handmatig vastgelegd</p>';
        $h.='<h3>Verzendhistorie</h3>';
        foreach ($history as $event) $h.='<p>'.$e($event['created_at']).' · '.$e($event['operation']==='invoice'?'Factuur':'Herinnering').' · '.$e($labels[$event['status']]??$event['status']).' · '.$e($event['recipient']).'</p>';
        if ($history===[]) $h.='<p>Nog niet verzonden.</p>';
      }
      $h.='</section>';
    }
    if ($canWrite) $h.='<p><a href="'.$base.'?new=1">+ Nieuwe verkoopfactuur</a></p>';
    $h.='<section><h2>Facturenoverzicht</h2><p>De laatste 200 toegankelijke facturen. Concepten zijn nog geen omzet. Bankmutaties zetten een factuur niet automatisch op betaald.</p><div class="scroll"><table><thead><tr><th>Factuur</th><th>Debiteur</th><th>Vervalt</th><th>Incl. btw</th><th>Status</th><th>Herinneringen</th></tr></thead><tbody>';
    foreach ($rows as $row) { $c=json_decode($row['customer_json'],true,32,JSON_THROW_ON_ERROR);$s=OfficeSalesService::status($row,$today);$h.='<tr><td><a href="'.$base.'?id='.(int)$row['id'].'">'.$e($row['invoice_number']??('Concept #'.$row['id'])).'</a></td><td>'.$e($c['display_name']).'</td><td>'.$e($row['due_date']).'</td><td>'.$money((int)$row['gross_cents']).'</td><td>'.$e($labels[$s]??$s).'</td><td>'.(int)$row['reminder_count'].'</td></tr>'; }
    if ($rows===[]) $h.='<tr><td colspan="6">Nog geen verkoopfacturen.</td></tr>';
    return $h.'</tbody></table></div></section></main></body></html>';
  }
}
