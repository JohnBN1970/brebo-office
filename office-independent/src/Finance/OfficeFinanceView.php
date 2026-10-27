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
    $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finance · BREBO Office</title><style>
      *{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#17283a;font:16px system-ui,sans-serif}a{color:#155c87}a:focus-visible,input:focus-visible,button:focus-visible{outline:3px solid #e9a72c;outline-offset:3px}.shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}aside{background:#162639;color:white;padding:26px 18px}aside strong{display:block;font-size:21px;margin-bottom:24px}aside a{display:block;color:white;padding:12px}main{padding:28px clamp(16px,4vw,52px);min-width:0}nav{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}nav a{padding:10px 0}h1{margin:0 0 8px}h2{font-size:20px}.muted,small{color:#536479}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:24px 0}.card,section{background:white;border:1px solid #dce4ed;border-radius:10px;padding:20px}.card strong{display:block;font-size:26px;margin:8px 0}.table{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dce4ed}td.amount{text-align:right;white-space:nowrap}form{display:flex;gap:10px;flex-wrap:wrap;margin:18px 0}input,button{padding:9px;border:1px solid #bfcbd7;border-radius:6px;background:white}.notice{padding:12px;background:#fff0b3;color:#473500}.queues a{display:inline-block;padding:10px 14px;background:#e6edf5;border-radius:6px;text-decoration:none}.queues [aria-current]{background:#162639;color:white}@media(max-width:760px){.shell{display:block}aside{padding:14px}aside strong{margin-bottom:10px}aside a{display:inline-block}main{padding:20px 14px}}
      </style></head><body>';
    if (OfficeMount::isTest()) $html .= '<div class="notice" role="status">TESTOMGEVING — ' . (getenv('OFFICE_FINANCE_SOURCE_KIND') === 'live_read_only' ? 'echte Finance-bron, uitsluitend lezen' : 'geen productiegegevens') . '</div>';
    $html .= '<div class="shell"><aside><strong>BREBO Office</strong><nav aria-label="Hoofdnavigatie"><a href="'.$home.'">Mijn werkplek</a><a href="'.$dashboard.'" aria-current="page">Finance</a></nav></aside><main><nav aria-label="Finance"><a href="'.$dashboard.'">Overzicht</a><a href="'.$payments.'">Betaalcentrum</a></nav><h1>'.($view === 'invoice' ? 'Factuurcontrole' : ($view === 'payments' ? 'Betaalcentrum' : 'Finance')).'</h1>';
    $html .= '<p class="muted">' . ($project === null ? 'Facturen en betaalcontrole voor jouw toegankelijke projecten.' : 'Project: ' . $e($project['title'])) . '</p>';
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

  private static function invoice(array $data): string {
    $e = self::e(...);
    $invoice = $data['invoice'];
    $hasLines = !empty($data['lines']);
    $html = '<section><h2>'.$e($invoice['invoice_number']).'</h2><p>'.$e($invoice['supplier_name']).' · '.self::money($invoice['amount_inc_vat']).' inclusief btw · Vervalt '.self::date($invoice['due_date']).'</p>';
    $html .= $hasLines ? '<p>'.$data['summary']['blocked_lines'].' geblokkeerde regels · '.$data['summary']['unmatched_lines'].' ongematchte regels</p>' : '<p class="notice" role="alert"><strong>Controle niet mogelijk — factuurregels ontbreken.</strong> De factuur is niet inhoudelijk gecontroleerd. Nul blokkades betekent hier niet dat de factuur akkoord is. Betaalvrijgave is niet toegestaan zonder de vereiste controles.</p>';
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
