<?php
declare(strict_types=1);
namespace Brebo\Office\Sales;
require_once __DIR__.'/OfficeSalesBilling.php';
require_once __DIR__.'/OfficeSalesApproval.php';
final class OfficeSalesView {
  private static function e(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
  public static function money(int $cents): string {return '€ '.number_format(intdiv($cents,100),0,',','.').','.sprintf('%02d',$cents%100);}
  private const LABELS=['draft'=>'Concept','sending'=>'Verzending gestart','sent'=>'Verzonden','overdue'=>'Vervallen','partially_paid'=>'Deels betaald','paid'=>'Betaald','reminder_sending'=>'Herinnering wordt verstuurd','reconciliation_required'=>'Verzenduitkomst controleren'];
  public static function lineScript(): string {
    return <<<'JS'
(() => {
  'use strict';
  const body = document.getElementById('sales-lines');
  const add = document.getElementById('sales-add-line');
  const status = document.getElementById('sales-lines-status');
  if (!body || !add) return;
  let next = body.rows.length;
  const debtor=document.querySelector('[name="relation_id"]'),project=document.querySelector('select[name="office_project_id"]'),contact=document.querySelector('[name="contact_id"]'),preview=document.getElementById('sales-party-preview');
  const party=()=>{try{return JSON.parse(debtor.selectedOptions[0]?.dataset.party||'{}');}catch{return {};}};
  const fillParty=(selected)=>{if(!debtor||!contact||!preview)return;const c=party();contact.replaceChildren(new Option('Geen contactpersoon','0'));for(const p of c.contacts||[])contact.add(new Option((p.given_name+' '+p.family_name).trim(),String(p.id)));contact.value=String(selected||c.contact_id||0);if(!contact.value)contact.value='0';preview.replaceChildren();for(const line of [c.legal_name||c.display_name,c.street,[c.postal_code,c.city].filter(Boolean).join(' '),c.email]){if(!line)continue;const p=document.createElement('p');p.textContent=line;preview.append(p);}};
  if(debtor&&contact){debtor.addEventListener('change',()=>fillParty());if(project)project.addEventListener('change',()=>{let b={};try{b=JSON.parse(project.selectedOptions[0]?.dataset.billing||'{}');}catch{}debtor.value=String(b.relation_id||'');fillParty(b.contact_id);if(!b.relation_id&&preview)preview.textContent='Leg de factuurdebiteur bij het project vast of kies deze hier.';});fillParty(contact.dataset.selected);}
  const invoiceDate=document.getElementById('sales-invoice-date'),paymentDays=document.getElementById('sales-payment-days'),dueDate=document.getElementById('sales-due-date');
  const updateDueDate=()=>{
    const raw=paymentDays.value,date=invoiceDate.value;
    paymentDays.setCustomValidity('');
    if(!/^\d{1,4}$/.test(raw)||Number(raw)>3650){dueDate.value='';paymentDays.setCustomValidity('Vul 0 tot en met 3650 hele dagen in.');return;}
    if(!/^\d{4}-\d{2}-\d{2}$/.test(date)){dueDate.value='';return;}
    const parsed=new Date(date+'T00:00:00Z');
    if(!Number.isFinite(parsed.getTime())||parsed.toISOString().slice(0,10)!==date){dueDate.value='';return;}
    parsed.setUTCDate(parsed.getUTCDate()+Number(raw));
    const result=parsed.toISOString();dueDate.value=result.length===24?result.slice(0,10):'';
  };
  if(invoiceDate&&paymentDays&&dueDate){for(const field of [invoiceDate,paymentDays]){field.addEventListener('input',updateDueDate);field.addEventListener('change',updateDueDate);}updateDueDate();}
  const mode=document.getElementById('sales-price-mode'), term=document.getElementById('sales-term-basis'), termPercent=document.getElementById('sales-term-percent');
  const labor=document.getElementById('sales-labor'), gPercent=document.getElementById('sales-g-percent');
  const fixed=cents=>{const s=cents.toString().padStart(3,'0');return s.slice(0,-2)+'.'+s.slice(-2);};
  const decimal = (value, whole, places) => {
    value = value.trim().replace(',', '.');
    if (!(new RegExp('^\\d{1,' + whole + '}(?:\\.\\d{1,' + places + '})?$')).test(value)) return null;
    const [a, b = ''] = value.split('.');
    return BigInt(a + b.padEnd(places, '0'));
  };
  const money = cents => {
    const digits = cents.toString().padStart(3, '0');
    return '€ ' + digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + digits.slice(-2);
  };
  const update = () => {
    add.disabled = body.rows.length >= 100 || !!term;
    let termValid=true;
    if(term){
      const p=termPercent.value.trim()===''?null:decimal(termPercent.value,3,4);
      termValid=termPercent.value.trim()===''||(p!==null&&p>0n&&p<=1000000n);
      const amount=termValid?(p===null?BigInt(term.dataset.plannedNet):(BigInt(term.dataset.contractNet)*p+500000n)/1000000n):0n;
      body.rows[0].querySelector('[name$="[price]"]').value=fixed(amount);
    }
    let net = 0n, vat = 0n, valid = termValid;
    const taxes=new Map();
    for (const row of body.rows) {
      row.querySelector('[data-remove-line]').disabled = body.rows.length === 1 || !!term;
      const fields = suffix => row.querySelector('[name$="[' + suffix + ']"]');
      const description = fields('description').value.trim();
      const price = fields('price').value.trim();
      const empty = description === '' && price === '';
      const quantity = decimal(fields('quantity').value, 6, 3);
      const cents = decimal(price, 7, 2);
      const rate = fields('vat').value;
      const ok = empty || (quantity !== null && quantity > 0n && cents !== null && ['0', '9', '21'].includes(rate));
      let amount = 0n;
      if (!ok) valid = false;
      else if (!empty) {
        amount=(quantity*cents+500n)/1000n;
        let tax;
        if(mode.value==='incl'){const gross=amount,denominator=100n+BigInt(rate);amount=(gross*100n+denominator/2n)/denominator;tax=gross-amount;}
        else tax=(amount*BigInt(rate)+50n)/100n;
        net+=amount;vat+=tax;
        const subtotal=taxes.get(rate)||[0n,0n];taxes.set(rate,[subtotal[0]+amount,subtotal[1]+tax]);
      }
      row.querySelector('[data-line-net]').textContent = ok ? money(amount) : '—';
    }
    if (net + vat > 999999999999n) valid = false;
    for (const [id, cents] of [['sales-net', net], ['sales-vat', vat], ['sales-gross', net + vat]]) {
      document.getElementById(id).textContent = valid ? money(cents) : '—';
    }
    for(const rate of ['21','9','0']){
      const row=document.getElementById('sales-tax-'+rate),values=taxes.get(rate)||[0n,0n];row.hidden=!taxes.has(rate);
      row.querySelector('[data-tax-base]').textContent=valid?money(values[0]):'—';row.querySelector('[data-tax-amount]').textContent=valid?money(values[1]):'—';
    }
    document.getElementById('sales-price-label').textContent=mode.value==='incl'?'Prijs incl.':'Prijs excl.';
    const laborCents=labor.value.trim()===''?0n:decimal(labor.value,10,2),rate=gPercent.value.trim()===''?0n:decimal(gPercent.value,3,2);
    const allocationValid=valid&&laborCents!==null&&laborCents<=net&&rate!==null&&rate<=10000n&&(rate===0n||laborCents>0n);
    const g=allocationValid?(laborCents*rate+5000n)/10000n:0n;
    document.getElementById('sales-g-split').hidden=rate===0n&&allocationValid;
    document.getElementById('sales-g-amount').textContent=allocationValid?money(g):'—';document.getElementById('sales-ordinary-amount').textContent=allocationValid?money(net+vat-g):'—';
    if(term){
      document.getElementById('sales-summary-term-net').textContent=valid?money(net):'—';
      document.getElementById('sales-summary-percent').textContent=termPercent.value.trim()===''?'':'('+termPercent.value.trim().replace('.',',')+'%)';
      const remaining=BigInt(term.dataset.contractNet)-BigInt(term.dataset.previousNet)-net;
      document.getElementById('sales-summary-unbilled').textContent=valid&&remaining>=0n&&term.dataset.complete==='1'?money(remaining):'—';
      document.getElementById('sales-summary-due').textContent=valid&&term.dataset.complete==='1'?money(BigInt(term.dataset.previousOpen)+net+vat):'—';
    }
    status.textContent=!valid?'Controleer aantallen, prijzen en termijnpercentage.':!allocationValid?'Bepaal de arbeidsprijs en controleer het G-rekeningpercentage.':'';
  };
  add.addEventListener('click', () => {
    if (body.rows.length >= 100 || term) return;
    const row = body.rows[0].cloneNode(true);
    for (const field of row.querySelectorAll('input, select')) {
      field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
      field.setAttribute('aria-label', field.getAttribute('aria-label').replace(/\d+/, String(next + 1)));
      field.value = field.name.endsWith('[quantity]') ? '1' : field.name.endsWith('[vat]') ? '21' : '';
    }
    row.querySelector('[data-remove-line]').setAttribute('aria-label', 'Regel ' + (next + 1) + ' verwijderen');
    next++;
    body.append(row);
    update();
    row.querySelector('input').focus();
  });
  body.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-line]');
    if (!button || body.rows.length <= 1 || term) return;
    button.closest('tr').remove();
    update();
    add.focus();
  });
  body.addEventListener('input', update);
  body.addEventListener('change', update);
  for(const field of [mode,labor,gPercent,termPercent].filter(Boolean)){field.addEventListener('input',update);field.addEventListener('change',update);}
  update();
})();
JS;
  }
  private static function styles(): string {
    return <<<'CSS'
*{box-sizing:border-box}input[type=hidden],[hidden],.invoice-totals [hidden]{display:none!important}body{margin:0;background:#f3f5f8;color:#243242;font:14px/1.5 system-ui,-apple-system,sans-serif}main{max-width:1560px;margin:auto;padding:24px 30px 48px}a{color:#145f94;text-decoration:none}a:hover{text-decoration:underline}nav.sales-nav{display:flex;gap:22px;flex-wrap:wrap;margin:0 0 20px;padding-bottom:15px;border-bottom:1px solid #dde4eb}.sales-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.sales-heading h1{font-size:23px;margin:0}.eyebrow{color:#6c7988;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin:0 0 4px}.sales-workspace{display:grid;grid-template-columns:minmax(0,850px) minmax(270px,340px);gap:30px;align-items:start;justify-content:center}.invoice-paper{background:white;padding:42px;min-height:1000px;box-shadow:0 2px 14px #263b5012;border:1px solid #e0e5eb}.paper-heading{display:flex;justify-content:space-between;gap:25px;align-items:start;margin-bottom:38px}.brand-logo{width:180px;height:54px;object-fit:contain;max-width:45%}.issuer{text-align:right;font-size:11px;line-height:1.7;color:#344252}.issuer strong{color:#1e2c3a}.issuer p{margin:0 0 14px}.invoice-customer{max-width:60%;min-height:106px;margin-bottom:34px}.invoice-customer p{margin:3px 0}.edit-block{border:1px dashed #ccd7e2;border-radius:5px;padding:15px;margin:18px 0}.edit-block:focus-within{border-color:#1780d5;box-shadow:0 0 0 2px #1684dc12}.edit-block legend{font-weight:600;font-size:12px;color:#5c6d7d;padding:0 7px}.invoice-title{font-size:22px;font-weight:700;margin:0 0 12px;color:#192734}.invoice-meta{display:grid;grid-template-columns:minmax(0,1fr) minmax(170px,.8fr);gap:18px}.invoice-meta label:first-child{margin-top:0}.small{font-size:12px;color:#697889}.invoice-meta p{margin:8px 0}label{display:block;font-size:12px;font-weight:600;color:#465768;margin:12px 0}input,select,textarea,button{font:inherit}input:not([type=hidden]),select,textarea{display:block;width:100%;min-width:0;margin-top:5px;padding:9px 10px;border:1px solid #d5dfe8;border-radius:4px;background:#fff;color:#243242;font-size:14px;font-weight:400}input:focus,select:focus,textarea:focus{outline:2px solid #1780d5;outline-offset:1px}button,.button{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:1px solid transparent;border-radius:5px;padding:10px 15px;background:#1769aa;color:white;font:600 13px/1.4 system-ui;cursor:pointer;text-decoration:none}button:hover,.button:hover{background:#11588f;text-decoration:none}.button.secondary,button.secondary{background:white;border-color:#cbd8e3;color:#36536c}.button.secondary:hover,button.secondary:hover{background:#f2f7fb}button:disabled{opacity:.4;cursor:default}.scroll{overflow:auto}.invoice-lines{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px;min-width:580px}.invoice-lines th{text-align:left;border-bottom:1px solid #b6c4d1;font-size:11px;color:#4d6173;font-weight:600;padding:10px 4px}.invoice-lines td{padding:10px 4px;border-bottom:1px solid #edf0f4;vertical-align:top}.invoice-lines th:nth-child(1){width:36%}.invoice-lines th:nth-child(2){width:9%}.invoice-lines th:nth-child(3){width:16%}.invoice-lines th:nth-child(4){width:12%}.invoice-lines th:nth-child(5){width:20%;text-align:right}.invoice-lines th:last-child{width:7%}.invoice-lines input,.invoice-lines select{padding:8px 5px;font-size:12px;margin:0}.invoice-lines .amount{text-align:right;white-space:nowrap;padding-top:18px;font-variant-numeric:tabular-nums}.remove-line{padding:4px 8px;background:transparent;border:0;color:#7b8c9b;font-size:20px;line-height:1}.remove-line:hover{background:#fff1f1;color:#a13535}.lines-tools{display:flex;align-items:center;gap:12px;margin:15px 0}.billing-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.contract-summary{background:#f6f9fc;border:1px solid #e0e8ef;border-radius:5px;padding:17px;margin:25px 0;font-size:12px}.contract-summary h3{margin:0 0 12px}.contract-summary dl{display:grid;grid-template-columns:1fr auto;gap:9px 16px;margin:0}.contract-summary dd{margin:0;text-align:right;font-variant-numeric:tabular-nums}.g-split:not([hidden]){display:block!important;border-top:1px solid #d6e0e8;margin-top:12px;padding-top:10px}.invoice-totals{width:310px;max-width:100%;margin:26px 0 30px auto;font-variant-numeric:tabular-nums}.invoice-totals div{display:flex;justify-content:space-between;gap:24px;padding:7px 0}.invoice-totals .grand{font-size:17px;font-weight:700;border-top:2px solid #273e54;margin-top:7px;padding-top:12px}.closing{font-size:12px;line-height:1.8;margin:38px 0 80px}.paper-footer{font-size:10px;color:#7c8793;text-align:center;padding-top:20px;border-top:1px solid #edf0f4}.invoice-sidebar{position:sticky;top:24px}.side-card{padding:22px;background:white;border:1px solid #e0e6ed;border-radius:7px;margin:0 0 17px}.side-card h2{font-size:14px;margin:0 0 16px}.side-card h3{font-size:13px;margin:18px 0 9px}.side-card p{font-size:12px;color:#677789}.side-card .button,.side-card>button{width:100%;margin:7px 0}.status-badge{display:inline-flex;border-radius:20px;background:#eaf3fa;color:#245b87;font-size:12px;font-weight:600;padding:5px 12px}.activity{list-style:none;padding:0;margin:0}.activity li{position:relative;border-left:2px solid #e2eaf2;padding:0 0 17px 15px;margin-left:4px;font-size:12px}.activity li:before{content:'';position:absolute;left:-5px;top:5px;background:#5e9ac6;border:2px solid white;width:8px;height:8px;border-radius:50%}.activity time{display:block;font-size:11px;color:#82909e}.side-card details{border-top:1px solid #e6ecf1;margin-top:16px;padding-top:14px}.side-card summary{cursor:pointer;font-weight:600;font-size:13px}.side-card textarea{min-height:120px;resize:vertical}.side-card input[type=checkbox]{display:inline-block;width:auto;margin:0 6px 0 0}.side-card label:has(input[type=checkbox]){font-weight:400}.error{padding:14px 18px;background:#fff0f0;color:#953939;border:1px solid #edcaca;border-radius:5px;margin:0 0 20px}.overview{background:white;border:1px solid #e0e6ed;border-radius:7px;padding:22px}.overview table{width:100%;border-collapse:collapse}.overview th,.overview td{text-align:left;border-bottom:1px solid #e8edf2;padding:13px 10px}.overview th{font-size:11px;color:#697d8f}.overview h2{font-size:16px}.empty{text-align:center;padding:55px!important;color:#82909e}.save-bar{display:flex;gap:12px;align-items:center;margin-top:22px}a:focus-visible,button:focus-visible,summary:focus-visible{outline:3px solid #da9c27;outline-offset:3px}@media(max-width:1150px){main{padding:20px}.sales-workspace{grid-template-columns:minmax(0,1fr) 280px;gap:18px}.invoice-paper{padding:25px}.invoice-meta{grid-template-columns:1fr}.invoice-customer{max-width:80%}.brand-logo{width:150px}}@media(max-width:850px){.sales-workspace{grid-template-columns:1fr}.invoice-sidebar{position:static;display:grid;grid-template-columns:1fr 1fr;gap:15px}.side-card{margin:0}.invoice-paper{min-height:0}.invoice-meta{grid-template-columns:1fr 1fr}}@media(max-width:560px){main{padding:14px}.invoice-paper{padding:18px}.sales-heading{align-items:start}.sales-heading h1{font-size:20px}.invoice-sidebar{display:block}.side-card{margin-bottom:15px}.issuer{font-size:9px}.brand-logo{width:130px}.invoice-customer{max-width:100%}.invoice-meta{grid-template-columns:1fr}.invoice-title{font-size:20px}.closing{margin-bottom:35px}.sales-nav{font-size:12px}}
.invoice-totals [hidden]{display:none!important}
CSS;
  }
  private static function logo(): string {
    $asset=json_decode(file_get_contents(__DIR__.'/brebo-logo-web.json'),true,8,JSON_THROW_ON_ERROR);
    return '<img class="brand-logo" alt="BREBO" width="180" height="54" src="data:image/png;base64,'.self::e($asset['png']).'">';
  }
  private static function hidden(array $row,string $csrf,string $action): string {
    return '<input type="hidden" name="csrf" value="'.self::e($csrf).'"><input type="hidden" name="action" value="'.self::e($action).'"><input type="hidden" name="id" value="'.(int)$row['id'].'"><input type="hidden" name="revision" value="'.(int)$row['revision'].'">';
  }
  public static function render(array $rows,?array $invoice,array $customers,array $projects,array $history,string $csrf,bool $canWrite,bool $canSend,string $today,string $error='',bool $new=false,bool $canRemind=false,array $receipts=[],string $scriptNonce='',?array $draftPreview=null,array $terms=[],bool $termSelector=false): string {
    $e=self::e(...);$money=self::money(...);$base='/office-test/finance/verkoop';$editor=$invoice!==null||$new||$draftPreview!==null;
    $h='<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopfacturen · BREBO Office</title><style>'.self::styles().'</style></head><body><main><nav class="sales-nav" aria-label="Finance"><a href="/office-test/finance">Finance-overzicht</a><a href="'.$base.'">Verkoopfacturen</a><a href="/office-test/finance/betaalcentrum">Betaalcentrum</a>'.(OfficeSalesApproval::enabled()?'<a href="/office-test/finance/administratie">Administratie</a>':'').'</nav><header class="sales-heading"><div><p class="eyebrow">BREBO Office · Verkoop</p><h1>'.($editor?($new?'Nieuwe verkoopfactuur':'Verkoopfactuur'):'Verkoopfacturen').'</h1></div>'.($editor?'<a class="button secondary" href="'.$base.'">Terug naar overzicht</a>':($canWrite?'<div><a class="button" href="'.$base.'?terms=1">Factuur uit termijn</a> <a class="button secondary" href="'.$base.'?new=1">+ Losse factuur</a></div>':'')).'</header>';
    if($error!=='')$h.='<p class="error" role="alert">'.$e($error).'</p>';
    if($editor){
      $row=$draftPreview??$invoice??['id'=>0,'revision'=>0,'status'=>'draft','invoice_date'=>$today,'due_date'=>(new \DateTimeImmutable($today))->modify('+14 days')->format('Y-m-d'),'relation_id'=>'','office_project_id'=>'','reference'=>'','lines_json'=>'[]','net_cents'=>0,'vat_cents'=>0,'gross_cents'=>0];
      $row=OfficeSalesBilling::defaults($row);$source=$row['source_snapshot']===null?null:json_decode($row['source_snapshot'],true,32,JSON_THROW_ON_ERROR);$summary=$row['summary_json']===null?null:json_decode($row['summary_json'],true,32,JSON_THROW_ON_ERROR);
      $editable=$row['status']==='draft'&&$canWrite;$status=OfficeSalesService::status($row,$today);$lines=json_decode($row['lines_json'],true,32,JSON_THROW_ON_ERROR);$customer=$invoice===null?null:json_decode($invoice['customer_json'],true,32,JSON_THROW_ON_ERROR);if($invoice!==null&&(string)$invoice['relation_id']!==(string)$row['relation_id'])$customer=null;
      $h.='<div class="sales-workspace"><article class="invoice-paper" aria-label="Factuurdocument">';
      if($editable)$h.='<form id="sales-draft" method="post">'.self::hidden($row,$csrf,'save').'<input type="hidden" name="source_term_id" value="'.(int)($row['source_term_id']??0).'">';
      $h.='<header class="paper-heading">'.self::logo().'<div class="issuer"><p><strong>BREBO Bouw en Advies B.V.</strong><br>Plaza 25A<br>4782 SL Moerdijk</p><p>085 - 500 38 38<br>info@brebobv.nl<br>brebobv.nl</p><p>KvK: 92123139<br>BTW: NL865894607.B01<br>IBAN: NL19 ABNA 0894 3515 67</p></div></header><div class="invoice-customer">';
      if($editable){$h.='<label>Debiteur<select name="relation_id" required><option value="">Kies debiteur</option>';foreach($customers as $c)$h.='<option data-party="'.$e(json_encode($c,JSON_THROW_ON_ERROR)).'" value="'.(int)$c['id'].'" '.((string)$c['id']===(string)$row['relation_id']?'selected':'').'>'.$e($c['display_name']).'</option>';$h.='</select></label>';}
      if($editable){$selectedContact=$row['_contact_input']??($customer['contact_id']??0);$h.='<label>Contactpersoon<select name="contact_id" data-selected="'.$e($selectedContact).'"><option value="0">Geen contactpersoon</option></select></label><div id="sales-party-preview" aria-live="polite"></div>';}
      if($customer!==null)$h.='<p><strong>'.$e($customer['legal_name']?:$customer['display_name']).'</strong></p><p>'.(!empty($customer['contact_name'])?'T.a.v. '.$e($customer['contact_name']).'<br>':'').$e($customer['street']).'<br>'.$e($customer['postal_code'].' '.$customer['city']).'</p>';
      else $h.='<p class="small">Kies een debiteur voor het factuuradres.</p>';
      $h.='</div><h2 class="invoice-title">'.$e($row['invoice_number']??((int)$row['id']>0?'Concept #'.$row['id']:'Conceptfactuur')).'</h2>';
      if($editable){
        $days='14';
        try{$from=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['invoice_date'],new \DateTimeZone('UTC'));$to=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['due_date'],new \DateTimeZone('UTC'));if($from&&$to)$days=$from->diff($to)->format('%r%a');}catch(\Throwable){}
        $days=(string)($row['_payment_days_input']??$days);
        $h.='<fieldset class="edit-block"><legend>Factuurgegevens</legend><div class="invoice-meta"><div><label>Project<select name="office_project_id"'.($source!==null?' disabled':'').'><option value="">Losse factuur</option>';
        foreach($projects as $p)$h.='<option data-billing="'.$e(json_encode($p['billing']??[],JSON_THROW_ON_ERROR)).'" value="'.(int)$p['id'].'" '.((string)$p['id']===(string)$row['office_project_id']?'selected':'').'>'.$e($p['title']).'</option>';
        $h.='</select></label><label>Uw kenmerk / referentie<input name="reference" maxlength="255" value="'.$e($row['reference']).'"></label></div><div><label>Factuurdatum<input id="sales-invoice-date" type="date" name="invoice_date" required value="'.$e($row['invoice_date']).'"></label><label>Betalingstermijn (dagen)<input id="sales-payment-days" type="number" name="payment_days" min="0" max="3650" step="1" required value="'.$e($days).'"></label><label>Vervaldatum<input id="sales-due-date" type="date" name="due_date" readonly required value="'.$e($row['due_date']).'"></label></div></div></fieldset>';
        if($source!==null)$h.='<input type="hidden" name="office_project_id" value="'.(int)$row['office_project_id'].'">';
      }else {$context=OfficeSalesBilling::documentContext($row);$h.='<div class="invoice-meta"><div>'.($context['project']!==''?'<p>Project: '.$e($context['project']).'</p>':'').($context['contract']!==''?'<p>Contract: '.$e($context['contract']).'</p>':'').($row['reference']!==''?'<p>Uw kenmerk: '.$e($row['reference']).'</p>':'').'</div><div><p>Factuurdatum: '.$e($row['invoice_date']).'</p><p>Vervaldatum: '.$e($row['due_date']).'</p></div></div>';}
      if($editable){
        if($source!==null){
          $h.='<fieldset class="edit-block" id="sales-term-basis" data-contract-net="'.(int)$summary['contract_net_cents'].'" data-planned-net="'.OfficeSalesBilling::cents((string)$source['amount']).'" data-previous-net="'.(int)$summary['previous_net_cents'].'" data-previous-open="'.(int)$summary['previous_open_gross_cents'].'" data-complete="'.($summary['coverage_complete']?'1':'0').'"><legend>Project en termijn</legend><p>Contract: '.$e($source['contract_number']).'</p><p><strong>Aanneemsom excl. btw: '.$money((int)$summary['contract_net_cents']).'</strong></p><p>Termijn '.(int)$source['term_number'].' · '.$e($source['description']).'</p><label>Percentage van de aanneemsom<input id="sales-term-percent" name="term_percent" inputmode="decimal" value="'.$e($row['_term_percent_input']??($row['term_percent_units']===null?'':OfficeSalesBilling::percentText((int)$row['term_percent_units'],4))).'"></label><p class="small">Zonder percentage wordt het vastgelegde termijnbedrag gebruikt. Het termijnschema zelf blijft behouden.</p><input type="hidden" id="sales-price-mode" name="price_mode" value="excl"></fieldset>';
        }else $h.='<label>Prijsinvoer<select id="sales-price-mode" name="price_mode"><option value="excl" '.($row['price_mode']==='excl'?'selected':'').'>Exclusief btw</option><option value="incl" '.($row['price_mode']==='incl'?'selected':'').'>Inclusief btw</option></select></label>';
      }
      $h.=($editable?'<fieldset class="edit-block"><legend>Factuurregels</legend>':'').'<div class="scroll"><table class="invoice-lines"><thead><tr><th>Omschrijving</th><th>Aantal</th><th id="sales-price-label">Prijs '.$e($row['price_mode']).'.</th><th>Btw %</th><th>Bedrag excl.</th><th><span class="small">'.($editable?'':'').'</span></th></tr></thead><tbody'.($editable?' id="sales-lines"':'').'>';
      for($i=0;$i<($editable?max(1,count($lines)):count($lines));$i++){
        $l=$lines[$i]??[];
        if($editable){
          $h.='<tr><td><input aria-label="Omschrijving regel '.($i+1).'" name="lines['.$i.'][description]" maxlength="1000" '.($source!==null?'readonly':'').' value="'.$e($l['description']??'').'"></td><td><input aria-label="Aantal regel '.($i+1).'" name="lines['.$i.'][quantity]" inputmode="decimal" '.($source!==null?'readonly':'').' value="'.$e($l['quantity']??'1').'"></td><td><input aria-label="Prijs regel '.($i+1).'" name="lines['.$i.'][price]" inputmode="decimal" '.($source!==null?'readonly':'').' value="'.$e($l['price']??'').'"></td><td><select aria-label="Btw regel '.($i+1).'" name="lines['.$i.'][vat]">';
          foreach(['21','9','0'] as $rate)$h.='<option '.(($l['vat']??'21')===$rate?'selected':'').'>'.$rate.'</option>';
          $h.='</select></td><td class="amount" data-line-net>'.$money((int)($l['net_cents']??0)).'</td><td><button class="remove-line" type="button" data-remove-line aria-label="Regel '.($i+1).' verwijderen">×</button></td></tr>';
        }else $h.='<tr><td>'.$e($l['description']).'</td><td>'.$e($l['quantity']).'</td><td>€ '.$e(str_replace('.',',',$l['price'])).'</td><td>'.$e($l['vat']).'%</td><td class="amount">'.$money((int)$l['net_cents']).'</td><td></td></tr>';
      }
      $h.='</tbody></table></div>';
      if($editable)$h.='<div class="lines-tools"><button class="secondary" type="button" id="sales-add-line">+ Regel toevoegen</button><span class="small" id="sales-lines-status" role="status" aria-live="polite"></span></div><p class="small">0% is het normale nultarief. Verlegd en vrijgesteld worden nog niet ondersteund.</p></fieldset>';
      if($editable)$h.='<fieldset class="edit-block"><legend>Arbeid en G-rekening</legend><div class="billing-fields"><label>Arbeidsprijs excl. btw<input id="sales-labor" name="labor_amount" inputmode="decimal" value="'.$e($row['_labor_input']??((int)$row['labor_cents']===0?'':OfficeSalesBilling::decimal((int)$row['labor_cents']))).'"></label><label>G-rekeningpercentage van arbeid<input id="sales-g-percent" name="g_percent" inputmode="decimal" value="'.$e($row['_g_percent_input']??((int)$row['g_rate_units']===0?'':OfficeSalesBilling::percentText((int)$row['g_rate_units']))).'"></label></div><label>Bevestigd G-rekeningnummer<input name="g_iban" maxlength="34" value="'.$e($row['g_iban']).'"></label><p class="small">Het percentage geldt alleen voor de arbeidsprijs van deze factuur, exclusief btw. Laat het percentage leeg als de G-rekening niet wordt gebruikt.</p></fieldset>';
      $taxes=OfficeSalesBilling::taxes($lines);
      $h.='<div class="invoice-totals"><div><span>Subtotaal excl. btw</span><span id="sales-net">'.$money((int)$row['net_cents']).'</span></div>';
      foreach(['21','9','0'] as $rate){$tax=$taxes[$rate]??['net_cents'=>0,'vat_cents'=>0];$h.='<div id="sales-tax-'.$rate.'"'.(!isset($taxes[$rate])?' hidden':'').'><span>Btw '.$rate.'% <small>over <span data-tax-base>'.$money((int)$tax['net_cents']).'</span></small></span><span data-tax-amount>'.$money((int)$tax['vat_cents']).'</span></div>';}
      $h.='<div><span>Totaal btw</span><span id="sales-vat">'.$money((int)$row['vat_cents']).'</span></div><div class="grand"><span>Totaal</span><span id="sales-gross">'.$money((int)$row['gross_cents']).'</span></div><div class="g-split" id="sales-g-split"'.((int)$row['g_cents']===0?' hidden':'').'><div><span>Naar G-rekening</span><span id="sales-g-amount">'.$money((int)$row['g_cents']).'</span></div><div><span>Naar gewone rekening</span><span id="sales-ordinary-amount">'.$money((int)$row['gross_cents']-(int)$row['g_cents']).'</span></div></div></div>';
      if($summary!==null&&$source!==null){
        $complete=(bool)$summary['coverage_complete'];
        $h.='<section class="contract-summary"><h3>Aanneemsom en termijnen</h3><dl><dt>Aanneemsom excl. btw</dt><dd>'.$money((int)$summary['contract_net_cents']).'</dd><dt>Deze termijn <span id="sales-summary-percent">'.($row['term_percent_units']===null?'':'('.$e(OfficeSalesBilling::percentText((int)$row['term_percent_units'],4)).'%)').'</span> excl. btw</dt><dd id="sales-summary-term-net">'.$money((int)$row['net_cents']).'</dd><dt>Eerder gefactureerd excl. btw</dt><dd>'.$money((int)$summary['previous_net_cents']).'</dd><dt>Geregistreerd betaald incl. btw</dt><dd>'.$money((int)$summary['paid_gross_cents']).'</dd><dt>Openstaand incl. deze termijn, incl. btw</dt><dd id="sales-summary-due">'.($complete?$money((int)$summary['due_including_current_gross_cents']):'Niet volledig beschikbaar').'</dd><dt>Nog te factureren na deze termijn, excl. btw</dt><dd id="sales-summary-unbilled">'.($complete?$money((int)$summary['unbilled_after_net_cents']):'Niet volledig beschikbaar').'</dd></dl><p class="small">Stand van gekoppelde termijnfacturen en geregistreerde ontvangsten bij opslaan.'.(!$complete?' Eerdere gefactureerde termijnen zijn nog niet allemaal gekoppeld.':'').'</p></section>';
      }
      $closing=$row['status']==='draft'?'Dit is een conceptfactuur. Nog niet verzonden; geen betaalverzoek.':((int)$row['g_cents']>0?'Betaal '.$money((int)$row['g_cents']).' naar G-rekening '.$e($row['g_iban']).' en '.$money((int)$row['gross_cents']-(int)$row['g_cents']).' naar NL19 ABNA 0894 3515 67, onder vermelding van het factuurnummer.':'Wij verzoeken u het bedrag voor de vervaldatum over te maken op rekeningnummer NL19 ABNA 0894 3515 67 onder vermelding van het factuurnummer.');
      $h.='<div class="closing"><strong>Afsluiting</strong><p>'.$closing.'</p></div><footer class="paper-footer">Op onze facturen zijn onze algemene voorwaarden van toepassing.</footer>';
      if($editable)$h.='</form>';
      $h.='</article><aside class="invoice-sidebar" aria-label="Factuurinformatie en acties"><section class="side-card"><h2>Factuurinformatie</h2><span class="status-badge">'.$e(self::LABELS[$status]??$status).'</span>';
      if($editable)$h.='<p>Wijzigingen bewaren met Concept opslaan.</p><button type="submit" form="sales-draft">Concept opslaan</button>';
      if($invoice!==null)$h.='<a class="button secondary" href="'.$base.'?id='.(int)$row['id'].'&amp;download=pdf">PDF downloaden</a><p>PDF van de laatst opgeslagen versie.</p>';
      else $h.='<p>Sla het concept op om een PDF te downloaden.</p>';
      if($invoice!==null)$h.=self::actions($row,$customer??json_decode($invoice['customer_json'],true,32,JSON_THROW_ON_ERROR),$csrf,$canWrite,$canSend&&!isset($row['_unsaved']),$canRemind,$status,$today);
      $h.='</section><section class="side-card"><h2>Historie</h2>';
      if($invoice===null)$h.='<p>Nieuw concept. Nog niet opgeslagen.</p>';
      else{
        $h.='<ul class="activity"><li><strong>Factuur aangemaakt</strong>'.(isset($row['created_at'])?'<time>'.$e($row['created_at']).'</time>':'').'</li>';
        foreach($history as $event)$h.='<li><strong>'.$e($event['operation']==='invoice'?'Factuur':'Herinnering').' · '.$e(self::LABELS[$event['status']]??$event['status']).'</strong><time>'.$e($event['created_at']).'</time>'.$e($event['recipient']).'</li>';
        foreach($receipts as $receipt)$h.='<li><strong>Ontvangst '.$money((int)$receipt['amount_cents']).'</strong><time>'.$e($receipt['receipt_date']).'</time>'.$e($receipt['evidence_reference']).' · Handmatig vastgelegd</li>';
        $h.='</ul>';if($history===[])$h.='<p>Nog niet verzonden.</p>';
      }
      $h.='</section></aside></div><div class="save-bar"><a class="button secondary" href="'.$base.'">Terug naar overzicht</a>'.($editable?'<button type="submit" form="sales-draft">Concept opslaan</button>':'').'</div>';
    }elseif($termSelector){
      $h.='<section class="overview"><h2>Factuur uit projecttermijn</h2><p class="small">Kies een beschikbare termijn uit een toegankelijk project.</p><div class="scroll"><table><thead><tr><th>Project</th><th>Contract</th><th>Termijn</th><th>Bedrag excl. btw</th><th></th></tr></thead><tbody>';
      foreach($terms as $term)$h.='<tr><td>'.$e($term['project_title']).'</td><td>'.$e($term['contract_number']).'</td><td>'.(int)$term['term_number'].' · '.$e($term['description']).'</td><td>'.$money(OfficeSalesBilling::cents((string)$term['amount'])).'</td><td><a class="button secondary" href="'.$base.'?from_term='.(int)$term['id'].'">Factuur maken</a></td></tr>';
      if($terms===[])$h.='<tr><td class="empty" colspan="5">Geen beschikbare termijnen. Leg eerst een project, contract en termijnschema vast.</td></tr>';
      $h.='</tbody></table></div></section>';
    }else{
      $h.='<section class="overview"><h2>Facturenoverzicht</h2><p class="small">De laatste 200 toegankelijke facturen. Concepten zijn nog geen omzet.</p><div class="scroll"><table><thead><tr><th>Factuur</th><th>Debiteur</th><th>Vervalt</th><th>Incl. btw</th><th>Status</th><th>Herinneringen</th></tr></thead><tbody>';
      foreach($rows as $row){$c=json_decode($row['customer_json'],true,32,JSON_THROW_ON_ERROR);$status=OfficeSalesService::status($row,$today);$h.='<tr><td><a href="'.$base.'?id='.(int)$row['id'].'">'.$e($row['invoice_number']??('Concept #'.$row['id'])).'</a></td><td>'.$e($c['display_name']).'</td><td>'.$e($row['due_date']).'</td><td>'.$money((int)$row['gross_cents']).'</td><td><span class="status-badge">'.$e(self::LABELS[$status]??$status).'</span></td><td>'.(int)$row['reminder_count'].'</td></tr>';}
      if($rows===[])$h.='<tr><td class="empty" colspan="6">Nog geen verkoopfacturen.</td></tr>';
      $h.='</tbody></table></div></section>';
    }
    $h.='</main>';
    if($canWrite&&($new||($invoice['status']??'')==='draft'))$h.='<script nonce="'.$e($scriptNonce).'">'.self::lineScript().'</script>';
    return $h.'</body></html>';
  }
  private static function actions(array $row,array $customer,string $csrf,bool $canWrite,bool $canSend,bool $canRemind,string $status,string $today): string {
    $e=self::e(...);$money=self::money(...);$h='';
    if($canWrite&&$status==='draft'&&OfficeSalesApproval::enabled()&&!isset($row['_unsaved'])){
      $a=$row['_approval']??null;$message=$a['message']??"Geachte heer/mevrouw,\n\nHierbij ontvangt u onze factuur.\n\nMet kleurrijke groet,\nBREBO Bouw en Advies B.V.";
      $h.='<h3>Goedkeuring en verzending</h3><p>'.($a?($a['state']==='approved'?'Concept goedgekeurd.':'Wacht op goedkeuring.'):'Vraag goedkeuring voor deze opgeslagen conceptversie.').'</p>';
      if($a){$h.='<p>Ontvanger: '.$e($a['recipient']).'</p><p style="white-space:pre-wrap">'.$e($a['message']).'</p>';if($a['state']==='pending')$h.='<form method="post">'.self::hidden($row,$csrf,'approve').'<label><input type="checkbox" name="confirmed" value="yes" required> Factuur, ontvanger en bericht gecontroleerd</label><button>Concept goedkeuren</button></form>';elseif($canSend)$h.='<form method="post">'.self::hidden($row,$csrf,'invoice').'<input type="hidden" name="recipient" value="'.$e($a['recipient']).'"><input type="hidden" name="message" value="'.$e($a['message']).'"><label><input type="checkbox" name="confirmed" value="yes" required> Goedgekeurde factuur nu per e-mail verzenden</label><button>Factuur verzenden</button></form>';}
      if(!$canSend)$h.='<p>Verzenden wordt beschikbaar nadat de verzendkoppeling en btw-koppeling zijn gecontroleerd.</p>';
      $h.='<details><summary>'.($a?'Ontvanger of bericht aanpassen':'Goedkeuring aanvragen').'</summary><form method="post">'.self::hidden($row,$csrf,'request_approval').'<label>Factuur e-mailadres<input type="email" name="recipient" required value="'.$e($a['recipient']??$customer['email']).'"></label><label>Bericht<textarea name="message" maxlength="10000" required>'.$e($message).'</textarea></label><button>Ter goedkeuring aanbieden</button></form></details>';
    }
    if($canWrite&&in_array($status,['draft','overdue'],true)&&!($status==='draft'&&OfficeSalesApproval::enabled())){
      $operation=$status==='draft'?'invoice':'reminder';$caption=$operation==='invoice'?'Factuur verzenden':'Herinnering versturen';
      if(!$canSend||($operation==='reminder'&&!$canRemind))$h.='<p>Verzenden wordt beschikbaar nadat de verzendkoppeling en btw-koppeling zijn gecontroleerd.</p>';
      else{
        $message=$operation==='invoice'?"Geachte heer/mevrouw,\n\nHierbij ontvangt u onze factuur.\n\nMet kleurrijke groet,\nBREBO Bouw en Advies B.V.":"Geachte heer/mevrouw,\n\nVolgens onze administratie staat factuur ".$row['invoice_number'].' met vervaldatum '.$row['due_date'].' nog open voor '.$money((int)$row['gross_cents']-(int)$row['paid_cents']).". Wilt u deze voldoen?\n\nMet kleurrijke groet,\nBREBO Bouw en Advies B.V.";
        $h.='<details><summary>'.$caption.'</summary><form method="post">'.self::hidden($row,$csrf,$operation).'<label>Verzenden naar<input type="email" required name="recipient" value="'.$e($customer['email']).'"></label><label>Bericht<textarea name="message" maxlength="10000">'.$e($message).'</textarea></label><label><input type="checkbox" name="confirmed" value="yes" required> Ontvanger, factuurregels, btw en bedrag gecontroleerd</label><button>'.$caption.'</button></form></details>';
      }
    }
    if($canWrite&&in_array($status,['sent','overdue','partially_paid'],true)){
      $outstanding=(int)$row['gross_cents']-(int)$row['paid_cents'];
      $h.='<details><summary>Ontvangst vastleggen</summary><p>Leg alleen een bedrag vast dat je in de bankadministratie hebt gecontroleerd.</p><form method="post">'.self::hidden($row,$csrf,'receipt').'<label>Ontvangen bedrag<input name="receipt_amount" inputmode="decimal" required value="'.intdiv($outstanding,100).'.'.sprintf('%02d',$outstanding%100).'"></label><label>Ontvangstdatum<input type="date" name="receipt_date" required value="'.$e($today).'"></label>'.(OfficeSalesApproval::enabled()?'<label>Rekeningsoort<select name="account_bucket"><option value="regular">Gewone rekening</option><option value="g">G-rekening</option></select></label>':'').'<label>Bankreferentie<input name="evidence_reference" maxlength="128" required></label><label><input type="checkbox" name="confirmed" value="yes" required> Ontvangst en factuur gecontroleerd in de bankadministratie</label><button>Ontvangst vastleggen</button></form></details>';
    }
    return $h;
  }
}
