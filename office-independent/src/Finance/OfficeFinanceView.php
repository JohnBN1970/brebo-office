<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use Brebo\Office\Intake\OfficeMount;

final class OfficeFinanceView {
  public const QUEUES = [
    'to_code'=>'Te coderen', 'blocked'=>'Geblokkeerd', 'to_match'=>'Te matchen',
    'release_ready'=>'Vrijgave voorbereiden', 'to_approve'=>'Ter goedkeuring', 'ready_to_pay'=>'Goedgekeurde vrijgave',
  ];

  private static function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  private static function money(mixed $value): string { return '€ ' . number_format((float)$value, 2, ',', '.'); }
  private static function date(mixed $value): string {
    if ($value === null || $value === '' || $value === 0 || $value === '0') return '—';
    return ctype_digit((string)$value) ? gmdate('d-m-Y', (int)$value) : self::e($value);
  }
  public static function render(array $data, string $view = 'dashboard', string $queue = '', int $page = 1, string $search = ''): string {
    $e = self::e(...);
    $project = $data['project'] ?? null;
    $url = static fn(string $name, array $query = []): string => OfficeMount::route($name, ($project === null ? [] : ['project_id'=>(int)$project['office_project_id']]) + $query);
    $home = $e(OfficeMount::route('home'));
    $dashboard = $e($url('finance'));
    $payments = $e($url('finance/betaalcentrum'));
    $newInvoice = $e($url('finance/nieuw'));
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finance · BREBO Office</title><style>
      *{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#17283a;font:16px system-ui,sans-serif}a{color:#155c87}a:focus-visible,input:focus-visible,button:focus-visible{outline:3px solid #e9a72c;outline-offset:3px}.shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}aside{background:#162639;color:white;padding:26px 18px}aside strong{display:block;font-size:21px;margin-bottom:24px}aside a{display:block;color:white;padding:12px}main{padding:28px clamp(16px,4vw,52px);min-width:0}nav{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}nav a{padding:10px 0}h1{margin:0 0 8px}h2{font-size:20px}.muted,small{color:#536479}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:24px 0}.card,section{background:white;border:1px solid #dce4ed;border-radius:10px;padding:20px}.card strong{display:block;font-size:26px;margin:8px 0}.table{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dce4ed}td.amount{text-align:right;white-space:nowrap}form{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}input,button{padding:9px;border:1px solid #bfcbd7;border-radius:6px;background:white}.notice{padding:12px;background:#fff0b3;color:#473500}.queues a{display:inline-block;padding:10px 14px;background:#e6edf5;border-radius:6px;text-decoration:none}.queues [aria-current]{background:#162639;color:white}@media(max-width:760px){.shell{display:block}aside{padding:14px}aside strong{margin-bottom:10px}aside a{display:inline-block}main{padding:20px 14px}}
      </style></head><body>';
    if (OfficeMount::isTest()) $html .= '<div class="notice" role="status">TESTOMGEVING — ' . (getenv('OFFICE_FINANCE_SOURCE_KIND') === 'live_read_only' ? 'echte Finance-bron, uitsluitend lezen' : 'geen productiegegevens') . '</div>';
    $html .= '<div class="shell"><aside><strong>BREBO Office</strong><nav aria-label="Hoofdnavigatie"><a href="'.$home.'">Mijn werkplek</a><a href="'.$dashboard.'" aria-current="page">Finance</a></nav></aside><main><nav aria-label="Finance"><a href="'.$dashboard.'">Overzicht</a><a href="'.$payments.'">Betaalcentrum</a><a href="'.$newInvoice.'">Nieuwe inkoopfactuur</a></nav><h1>'.($view === 'new' ? 'Nieuwe inkoopfactuur' : ($view === 'invoice' ? 'Factuurcontrole' : ($view === 'payments' ? 'Betaalcentrum' : 'Finance'))).'</h1>';
    $html .= '<p class="muted">' . ($project === null ? 'Facturen en betaalcontrole voor jouw toegankelijke projecten.' : 'Project: ' . $e($project['title'])) . '</p>';
    if ($view === 'draft') return $html . self::draft($data) . '</main></div></body></html>';
    if ($view === 'new') return $html . self::newInvoice($data) . '</main></div></body></html>';
    if ($view === 'invoice') return $html . self::invoice($data) . '</main></div></body></html>';
    $count = array_sum($data['counts']);
    $amount = array_sum(array_map('floatval', $data['amounts_inc_vat']));
    $html .= '<p class="muted">Bron: Finance-inkoopfacturen · Peildatum: '.$e(gmdate('d-m-Y H:i', $data['generated_at'])).' UTC · Bedragen inclusief btw.</p>';
    $html .= '<div class="cards"><div class="card">Open facturen<strong>'.$count.'</strong>'.self::money($amount).'</div><div class="card">Geblokkeerd<strong>'.$data['counts']['blocked'].'</strong>'.self::money($data['amounts_inc_vat']['blocked']).'</div><div class="card">Ter goedkeuring<strong>'.$data['counts']['to_approve'].'</strong>'.self::money($data['amounts_inc_vat']['to_approve']).'</div></div>';
    if ($view !== 'payments') {
      $html .= '<section><h2>Werkvoorraad</h2><p>Volg codering, matching en vrijgave vanuit het Betaalcentrum.</p><nav class="queues">';
      foreach (self::QUEUES as $key=>$label) $html .= '<a href="'.$e($url('finance/betaalcentrum',['queue'=>$key])).'">'.$e($label).' ('.$data['counts'][$key].')</a>';
      return $html . '</nav><p>Dit overzicht toont bestaande controlestatussen. Betalingen en wijzigingen zijn hier nog niet beschikbaar.</p></section></main></div></body></html>';
    }
    $queue = isset(self::QUEUES[$queue]) ? $queue : 'to_code';
    $html .= '<nav class="queues" aria-label="Werkvoorraad">';
    foreach (self::QUEUES as $key=>$label) $html .= '<a '.($queue === $key ? 'aria-current="page" ' : '').'href="'.$e($url('finance/betaalcentrum',['queue'=>$key])).'">'.$e($label).' ('.$data['counts'][$key].')</a>';
    $rows = array_values(array_filter($data['queues'][$queue], static fn(array $row): bool => $search === '' || stripos($row['invoice_number'].' '.$row['supplier_name'].' '.$row['project_label'], $search) !== false));
    $pages = max(1, (int)ceil(count($rows)/50));
    $page = min(max(1,$page),$pages);
    $html .= '</nav><section><h2>'.$e(self::QUEUES[$queue]).'</h2><form method="get" action="'.$payments.'">'.($project === null ? '' : '<input type="hidden" name="project_id" value="'.(int)$project['office_project_id'].'">').'<input type="hidden" name="queue" value="'.$e($queue).'"><label>Zoek factuur, leverancier of project <input name="q" maxlength="120" value="'.$e($search).'"></label><button>Zoeken</button></form>';
    if ($rows === []) $html .= '<p>Geen facturen in deze selectie. Kies een andere werkvoorraad of pas de zoekopdracht aan.</p>';
    else {
      $html .= '<div class="table"><table><thead><tr><th>Factuur / leverancier</th><th>Project</th><th>Vervaldatum</th><th>Inclusief btw</th><th>Controle</th></tr></thead><tbody>';
      foreach (array_slice($rows,($page-1)*50,50) as $row) $html .= '<tr><td><a href="'.$e($url('finance/factuur',['id'=>$row['invoice_id']])).'">'.$e($row['invoice_number']).'</a><br><small>'.$e($row['supplier_name']).'</small></td><td>'.$e($row['project_label']).'</td><td>'.self::date($row['due_date']).'<br><small>'.$e($row['priority']).'</small></td><td class="amount">'.self::money($row['amount_inc_vat']).'</td><td>' . (isset($row['line_count']) && (int)$row['line_count'] === 0 ? 'Controle niet mogelijk — factuurregels ontbreken' : $row['blocked_lines'].' blokkades · '.$row['unmatched_lines'].' ongematcht') . '</td></tr>';
      $html .= '</tbody></table></div><nav aria-label="Paginering">';
      if ($page>1) $html .= '<a href="'.$e($url('finance/betaalcentrum',['queue'=>$queue,'q'=>$search,'page'=>$page-1])).'">Vorige</a>';
      $html .= '<span>Pagina '.$page.' van '.$pages.'</span>';
      if ($page<$pages) $html .= '<a href="'.$e($url('finance/betaalcentrum',['queue'=>$queue,'q'=>$search,'page'=>$page+1])).'">Volgende</a>';
      $html .= '</nav>';
    }
    return $html . '<p>Een goedgekeurde vrijgave bewijst geen bankbetaling. Betaal-, vierogen- en reconciliatiecontroles blijven leidend.</p></section></main></div></body></html>';
  }

  private static function newInvoice(array $data): string {
    if (!OfficeMount::isTest() || !isset($data['upload_csrf'])) return '<p>Alleen beschikbaar in test.</p>';
    $action = self::e(OfficeMount::route('finance/nieuw'));
    $csrf = self::e($data['upload_csrf']);
    $html = '<section><h2>Nieuwe inkoopfactuur</h2><p>PDF, JPG of PNG (max. 10 MB). Alleen conceptopslag; geen verzending naar Moneybird.</p>';
    if (($_GET['uploaded'] ?? '') === '1') $html .= '<p role="status">Concept opgeslagen; nog niet verwerkt.</p>';
    $html .= '<form method="post" enctype="multipart/form-data" action="'.$action.'"><input type="hidden" name="csrf" value="'.$csrf.'"><label>Document <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" required></label><button>Nieuwe factuur uploaden</button></form></section>';
    $html .= '<section><h2>Mijn conceptfacturen</h2><p>Privé opgeslagen; nog niet uitgelezen of geboekt.</p>';
    if (empty($data['drafts'])) return $html . '<p>Geen concepten gevonden.</p></section>';
    $html .= '<div class="table"><table><thead><tr><th>Concept</th><th>Aangemaakt UTC</th><th>Type</th><th>Status</th></tr></thead><tbody>';
    foreach ($data['drafts'] as $draft) {
      $link = self::e(OfficeMount::route('finance/concept', ['id'=>$draft['id']]));
      $html .= '<tr><td><a href="'.$link.'">'.self::e(substr($draft['id'], 0, 12)).'</a></td><td>'.self::e($draft['created_at']).'</td><td>'.self::e($draft['mime']).'</td><td>'.self::e($draft['status']).'</td></tr>';
    }
    return $html . '</tbody></table></div></section>';
  }

  private static function draft(array $data): string {
    $draft = $data['draft'];
    $id = self::e($draft['id']);
    $mime = self::e($draft['mime']);
    $src = self::e(OfficeMount::route('finance/concept', ['id'=>$draft['id'], 'document'=>'1']));
    $html = '<section><h2>Conceptfactuur '.$id.'</h2><p>Aangemaakt: '.self::e($draft['created_at']).' · '.$mime.'</p><p>Status: '.self::e($draft['status']).' — nog niet uitgelezen of gecontroleerd.</p>';
    $html .= '<p><a href="'.$src.'" target="_blank" rel="noopener noreferrer">Document openen</a></p>';
    if ($draft['mime'] === 'application/pdf') $html .= '<iframe title="Factuurdocument" src="'.$src.'" style="width:100%;height:680px;border:1px solid #ccd5df"></iframe>';
    else $html .= '<img src="'.$src.'" alt="Geüpload factuurdocument" style="max-width:100%;height:auto">';
    $extraction = $draft['extraction'] ?? null;
    $action = self::e(OfficeMount::route('finance/concept', ['id'=>$draft['id']]));
    $csrf = self::e($data['upload_csrf'] ?? '');
    $html .= '<h3>Automatische documentextractie</h3><p>Lees de factuur uit met de bestaande BREBO-service. Dit wijzigt geen handmatig ingevoerde gegevens en boekt niets.</p>';
    $html .= '<form method="post" action="'.$action.'"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="action" value="extract"><button type="submit">Factuur automatisch uitlezen</button></form>';
    if (($_GET['extracted'] ?? '') === '1') $html .= '<p role="status">Extractie opgeslagen. Controleer de brontekst met de PDF.</p>';
    if (is_array($extraction)) {
      $html .= '<details><summary>Uitgelezen brontekst · '.self::e($extraction['status'] ?? '').'</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">'.self::e($extraction['text'] ?? '').'</pre></details>';
    }
    $proposals = $draft['proposals'] ?? [];
    if ($proposals !== []) {
      $labels = ['invoice_number'=>'Factuurnummer','amount_inc_vat'=>'Totaal inclusief btw','vat_amount'=>'Btw-bedrag'];
      $html .= '<h3>Herkenningsvoorstellen (niet gecontroleerd)</h3><p>Neem een waarde pas over nadat je deze met het originele document hebt vergeleken. Bestaande invoer wordt niet aangepast.</p><div class="table"><table><thead><tr><th>Veld</th><th>Voorstel</th><th>Controle</th></tr></thead><tbody>';
      foreach ($proposals as $key=>$proposal) {
        if (!isset($labels[$key])) continue;
        $html .= '<tr><td>'.self::e($labels[$key]).'</td><td>'.self::e($proposal['value'] ?? '').'</td><td>Handmatig controleren</td></tr>';
      }
      $html .= '</tbody></table></div>';
    }
    $lineCandidates = $draft['line_proposals'] ?? [];
    $html .= '<h3>Factuurregels herkennen (voorstellen)</h3>';
    if ($lineCandidates === []) {
      $html .= '<p>Geen betrouwbaar herkenbare factuurregels. Controleer de PDF; er zijn geen regels aangenomen.</p>';
    } else {
      $html .= '<p>Niet gecontroleerd of geboekt. Btw per regel is nog onbekend. Vergelijk elke regel met de originele factuur.</p>';
      $html .= '<div class="table"><table><thead><tr><th>Omschrijving</th><th>Aantal</th><th>Eenheid</th><th>Eenheidsprijs</th><th>Regeltotaal</th><th>Btw</th></tr></thead><tbody>';
      foreach ($lineCandidates as $candidate) {
        $html .= '<tr><td>'.self::e($candidate['description']).'</td><td>'.self::e($candidate['quantity']).'</td><td>'.self::e($candidate['unit']).'</td><td>'.self::e($candidate['unit_price']).'</td><td>'.self::e($candidate['line_total']).'</td><td>Nog controleren</td></tr>';
      }
      $html .= '</tbody></table></div>';
    }
    $duplicateCandidates = $draft['duplicate_candidates'] ?? [];
    if ($duplicateCandidates !== []) {
      $html .= '<p role="alert"><strong>Mogelijk dubbele conceptfactuur.</strong> Hetzelfde factuurnummer komt voor bij een ander privéconcept. Controleer de leverancier en het document vóór verdere verwerking.</p><ul>';
      foreach ($duplicateCandidates as $otherId) {
        $link = self::e(OfficeMount::route('finance/concept', ['id'=>$otherId]));
        $html .= '<li><a href="'.$link.'">Vergelijk concept '.self::e(substr($otherId, 0, 12)).'</a></li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p>Dubbelcheck: geen overeenkomstig factuurnummer gevonden binnen je eigen privéconcepten. Geïmporteerde facturen en andere gebruikers zijn nog niet gecontroleerd.</p>';
    }
    $importedCandidates = $draft['imported_duplicate_candidates'] ?? [];
    if ($importedCandidates !== []) {
      $html .= '<p role="alert"><strong>Mogelijk dubbele geïmporteerde factuur.</strong> Hetzelfde factuurnummer bestaat in het toegankelijke Finance-register. Vergelijk leverancier, bedrag en document voordat je verdergaat.</p><ul>';
      foreach ($importedCandidates as $candidate) {
        $link = self::e(OfficeMount::route('finance/factuur', ['id'=>(int)$candidate['id']]));
        $html .= '<li><a href="'.$link.'">Factuur '.self::e($candidate['invoice_number']).'</a> · '.self::e($candidate['supplier_name']).' · '.self::e($candidate['status']).'</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p>Geïmporteerde facturen: geen overeenkomstig factuurnummer binnen je toegankelijke Finance-register. Niet alle mogelijke bronnen zijn gecontroleerd.</p>';
    }
    $review = $draft['review'] ?? [];
    $action = self::e(OfficeMount::route('finance/concept', ['id'=>$draft['id']]));
    $csrf = self::e($data['upload_csrf'] ?? '');
    if (($_GET['saved'] ?? '') === '1') $html .= '<p role="status">Controlegegevens opgeslagen. Nog niet goedgekeurd.</p>';
    $html .= '<h3>Factuurgegevens controleren</h3><p>Herkenningsvoorstellen zijn alvast ingevuld waar nog geen opgeslagen waarde bestaat. Vergelijk alles met de originele factuur voordat je opslaat.</p>';
    $html .= '<form method="post" action="'.$action.'" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px"><input type="hidden" name="csrf" value="'.$csrf.'">';
    $fields = ['supplier_name'=>'Leverancier','invoice_number'=>'Factuurnummer','invoice_date'=>'Factuurdatum (JJJJ-MM-DD)','due_date'=>'Vervaldatum (JJJJ-MM-DD)','reference'=>'Referentie / project','amount_inc_vat'=>'Totaal inclusief btw','vat_amount'=>'Btw-bedrag'];
    foreach ($fields as $name=>$label) {
      $saved = trim((string)($review[$name] ?? ''));
      $suggested = trim((string)($proposals[$name]['value'] ?? ''));
      $value = self::e($saved !== '' ? $saved : $suggested);
      $html .= '<label>'.self::e($label).'<input style="display:block;width:100%" name="'.$name.'" value="'.$value.'" maxlength="160"></label>';
    }
    $choices = $draft['project_choices'] ?? [];
    $selected = (int)($draft['office_project_id'] ?? 0);
    $html .= '<label style="grid-column:1/-1">BREBO Office-project (gecontroleerde koppeling)<select name="office_project_id" style="display:block;width:100%"><option value="">Nog niet gekoppeld</option>';
    foreach ($choices as $projectId=>$projectTitle) {
      $html .= '<option value="'.(int)$projectId.'"'.($selected === (int)$projectId ? ' selected' : '').'>'.self::e($projectTitle).' (#'.(int)$projectId.')</option>';
    }
    $html .= '</select></label>';
    if ($choices === []) $html .= '<p style="grid-column:1/-1">Geen geverifieerde projecten beschikbaar binnen je huidige toegangsrechten.</p>';
    $lines = is_array($draft['review_lines'] ?? null) ? $draft['review_lines'] : ($draft['line_proposals'] ?? []);
    $lines = array_slice($lines, 0, 30);
    // Financial check uses saved lines only; extraction proposals are never approved implicitly.
    $savedLines = is_array($draft['review_lines'] ?? null) ? $draft['review_lines'] : [];
    $netCents = 0; $vatCents = 0; $complete = $savedLines !== [];
    foreach ($savedLines as $line) {
      $desc = trim((string)($line['description'] ?? ''));
      $qty = trim((string)($line['quantity'] ?? ''));
      $price = trim((string)($line['unit_price'] ?? ''));
      $total = trim((string)($line['line_total'] ?? ''));
      $rate = trim((string)($line['vat_rate'] ?? ''));
      if ($desc === '' || $qty === '' || $price === '' || $total === '' || $rate === '') { $complete = false; continue; }
      $q = (float)str_replace(',', '.', $qty);
      $p = (float)str_replace(',', '.', $price);
      $t = (int)round((float)str_replace(',', '.', $total) * 100);
      $v = (float)str_replace(',', '.', $rate);
      if (abs($q * $p * 100 - $t) > 1 || $v < 0 || $v > 100) $complete = false;
      $netCents += $t;
      $vatCents += (int)round($t * $v / 100);
    }
    $gross = trim((string)($review['amount_inc_vat'] ?? ''));
    $invoiceVat = trim((string)($review['vat_amount'] ?? ''));
    if ($gross === '' || $invoiceVat === '') $complete = false;
    $grossCents = (int)round((float)str_replace(',', '.', $gross) * 100);
    $invoiceVatCents = (int)round((float)str_replace(',', '.', $invoiceVat) * 100);
    $difference = $netCents + $vatCents - $grossCents;
    $vatDifference = $vatCents - $invoiceVatCents;
    $balanced = $complete && abs($difference) <= 1 && abs($vatDifference) <= 1;
    $html .= '<div style="grid-column:1/-1"><h3>Financiële controle (opgeslagen regels)</h3><p>Regels excl. btw: € '.number_format($netCents / 100, 2, ',', '.').' · Btw regels: € '.number_format($vatCents / 100, 2, ',', '.').' · Regels incl. btw: € '.number_format(($netCents + $vatCents) / 100, 2, ',', '.').'</p>';
    $html .= '<p>Verschil met factuurtotaal: € '.number_format($difference / 100, 2, ',', '.').' · Verschil btw: € '.number_format($vatDifference / 100, 2, ',', '.').'</p>';
    $html .= $balanced ? '<p style="color:#146c2e"><strong>Bedragen sluiten aan.</strong> Nog niet goedgekeurd of geboekt.</p>' : '<p role="alert" style="color:#a32020"><strong>Controle niet sluitend.</strong> Vul alle regelvelden en factuurbedragen in en controleer de verschillen. Nog niet goedgekeurd of geboekt.</p>';
    $html .= '</div>';
    $lineCount = min(30, max(5, count($lines) + 2));
    $html .= '<div style="grid-column:1/-1"><h3>Factuurregels controleren en opslaan</h3><p>Lege regels worden overgeslagen. Verwijder een regel door alle velden leeg te maken. Btw is een percentage per regel; geen boeking of goedkeuring.</p><input type="hidden" name="line_count" value="'.$lineCount.'"><div class="table"><table><thead><tr><th>Omschrijving</th><th>Aantal</th><th>Eenheid</th><th>Prijs</th><th>Btw %</th><th>Regeltotaal excl. btw</th></tr></thead><tbody>';
    for ($i = 0; $i < $lineCount; $i++) {
      $line = $lines[$i] ?? [];
      $html .= '<tr>';
      foreach (['description'=>180,'quantity'=>20,'unit'=>20,'unit_price'=>20,'vat_rate'=>20,'line_total'=>20] as $field=>$limit) {
        $html .= '<td><input aria-label="'.self::e($field.' regel '.($i+1)).'" name="line_'.$field.'['.$i.']" maxlength="'.$limit.'" style="width:100%;min-width:90px" value="'.self::e($line[$field] ?? '').'"></td>';
      }
      $html .= '</tr>';
    }
    $html .= '</tbody></table></div></div>';
    $html .= '<div style="grid-column:1/-1"><button type="submit">Controle opslaan</button></div></form>';
    return $html . '<p>Opslaan is geen goedkeuring. Volledige dubbele-factuurcontrole en Moneybird-verzending zijn nog niet beschikbaar.</p></section>';
  }

  private static function invoice(array $data): string {
    $e = self::e(...);
    $invoice = $data['invoice'];
    $hasLines = !empty($data['lines']);
    $html = '<section><h2>'.$e($invoice['invoice_number']).'</h2><p>'.$e($invoice['supplier_name']).' · '.self::money($invoice['amount_inc_vat']).' inclusief btw · Vervalt '.self::date($invoice['due_date']).'</p>';
    $html .= $hasLines ? '<p>'.$data['summary']['blocked_lines'].' geblokkeerde regels · '.$data['summary']['unmatched_lines'].' ongematchte regels</p>' : '<p class="notice" role="alert"><strong>Controle niet mogelijk — factuurregels ontbreken.</strong> De factuur is niet inhoudelijk gecontroleerd. Nul blokkades betekent hier niet dat de factuur akkoord is. Betaalvrijgave is niet toegestaan zonder de vereiste controles.</p>';
    if (isset($data['upload_csrf']) && OfficeMount::isTest()) {
      $action = self::e(OfficeMount::route('finance/factuur', ['id'=>(int)($invoice['id'] ?? $invoice['invoice_id'] ?? 0)]));
      $html .= '<section><h3>Factuurdocument toevoegen (test)</h3><p>PDF, JPG of PNG, maximaal 10 MB. Privé opgeslagen; geen automatische verwerking of verzending naar Moneybird.</p><form method="post" enctype="multipart/form-data" action="'.$action.'"><input type="hidden" name="csrf" value="'.$e($data['upload_csrf']).'"><label>Document <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label><button type="submit">Document uploaden</button></form></section>';
      if (($_GET['uploaded'] ?? '') === '1') $html .= '<p role="status">Document veilig opgeslagen in de testomgeving. Nog niet verwerkt of doorgestuurd.</p>';
    }
    $status = ['pending_approval'=>'Ter goedkeuring','approved'=>'Goedgekeurd','rejected'=>'Afgewezen','cancelled'=>'Geannuleerd','executed'=>'Uitgevoerd'];
    $release = $data['payment_release']['status'] ?? '';
    $html .= '<p>Laatste betaalvrijgave: '.$e($status[$release] ?? ($release === '' ? 'Geen vrijgave' : 'Overige status')).'</p><p>G-rekeninginstructie: '.($data['g_account'] === null ? 'Geen instructie' : 'Aanwezig').'</p><details open><summary>Factuurregels en blokkades</summary><div class="table"><table><thead><tr><th>Regel</th><th>Omschrijving</th><th>Inclusief btw</th><th>Controle</th></tr></thead><tbody>';
    $reasons = ['missing_order'=>'Inkooporder ontbreekt','performance_table_missing'=>'Prestatiebron niet beschikbaar','invoice_line_missing'=>'Factuurregel ontbreekt','invoice_line_table_missing'=>'Factuurregelbron niet beschikbaar'];
    foreach ($data['lines'] as $line) {
      $blocked = $line['blocker']['blocked'] ?? false;
      $reason = $line['blocker']['reason'] ?? '';
      $text = $reasons[$reason] ?? ($blocked ? 'Prestatie, bewijs of matching controleren' : 'Geen regelblokkade');
      $html .= '<tr><td>'.$e($line['line_number']).'</td><td>'.$e($line['description'] ?? '').'</td><td class="amount">'.self::money($line['amount_inc_vat'] ?? 0).'</td><td>'.$e($text).'</td></tr>';
    }
    return $html . '</tbody></table></div></details><p>Wijzigingen en betalingen zijn hier nog niet beschikbaar.</p></section>';
  }
}
