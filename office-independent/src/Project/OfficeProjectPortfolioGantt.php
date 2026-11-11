<?php
declare(strict_types=1);

namespace Brebo\Office\Project;

/**
 * Read-only portfolio Gantt. Only renders dates and blockers supplied by a
 * verified, authorized planning source. Never invents schedule data.
 */
final class OfficeProjectPortfolioGantt {
  public static function render(array $projects, string $base, bool $demo = false): string {
    $e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $today = new \DateTimeImmutable('today');
    $from = $today->modify('monday this week')->modify('-14 days');
    $days = 84;
    $html = '<style>.portfolio-gantt{overflow-x:auto;margin-top:18px}.portfolio-gantt-grid{min-width:850px}.portfolio-gantt-row{display:grid;grid-template-columns:240px minmax(580px,1fr);border-bottom:1px solid #e1e6ed;min-height:52px;align-items:center}.portfolio-gantt-name{padding:10px;font-size:14px}.portfolio-gantt-track{height:42px;position:relative;background:repeating-linear-gradient(to right,#f8fafc 0,#f8fafc calc(100% / 12 - 1px),#dbe1e8 calc(100% / 12 - 1px),#dbe1e8 calc(100% / 12))}.portfolio-gantt-today{position:absolute;left:var(--today);top:0;bottom:0;border-left:2px dashed #172c45;z-index:2}.portfolio-gantt-bar{position:absolute;top:13px;height:17px;border-radius:4px;background:#247b55}.portfolio-gantt-empty{font-size:12px;color:#64748b;position:absolute;top:12px;left:10px}.portfolio-gantt-badge{font-size:11px;padding:3px 6px;border-radius:5px;background:#edf1f6;display:inline-block}.portfolio-gantt-badge.critical{background:#fee2e2;color:#991b1b}.portfolio-gantt-badge.ontrack{background:#dcfce7;color:#166534}.portfolio-gantt-badge.waiting{background:#fef3c7;color:#92400e}.portfolio-gantt-badge.done{background:#dbeafe;color:#1e40af}.portfolio-gantt-note{font-size:12px;color:#64748b}.portfolio-gantt-weeks{display:grid;grid-template-columns:repeat(12,1fr);font-size:11px;text-align:center;color:#526278}</style>';

    $html .= '<p class="portfolio-gantt-note">Peildatum: '.$e($today->format('d-m-Y')).'. De stippellijn is vandaag. Planning, voortgang en kritieke-padmeldingen worden uitsluitend getoond wanneer de geautoriseerde bron deze levert.</p>';
    $html .= '<div class="portfolio-gantt"><div class="portfolio-gantt-grid"><div class="portfolio-gantt-row"><strong>Project / status</strong><div class="portfolio-gantt-weeks">';
    for ($w=0;$w<12;$w++) $html .= '<span>W'.$e($from->modify('+'.($w*7).' days')->format('W')).'</span>';
    $html .= '</div></div>';
    $todayOffset = max(0,min(100,((int)$from->diff($today)->format('%r%a') / $days)*100));
    foreach ($projects as $project) {
      $status = strtolower(trim((string)($project['status'] ?? '')));
      $statuses = [
        'kritiek'=>['Kritiek','critical'],'critical'=>['Kritiek','critical'],
        'op schema'=>['Op schema','ontrack'],'in de pas'=>['Op schema','ontrack'],
        'in wacht'=>['In wacht','waiting'],'on hold'=>['In wacht','waiting'],
        'afgevoerd'=>['Afgevoerd',''],'gereed'=>['Gereed','done'],
        'completed'=>['Gereed','done'],'vervallen'=>['Vervallen','']
      ];
      [$label,$class] = $statuses[$status] ?? [($status !== '' ? $project['status'] : 'Onbekend'),''];
      $url = $demo ? $base.'/projecten?view=gantt&demo=1&demo_project='.(int)$project['id'] : $base.'/projecten/detail?id='.(int)$project['id'];
      $criticalNote = trim((string)($project['critical_note'] ?? ''));
      $hoverNote = $criticalNote !== '' ? ' title="'.$e($criticalNote).'"' : '';
      $html .= '<div class="portfolio-gantt-row"><div class="portfolio-gantt-name"><a href="'.$e($url).'"'.$hoverNote.'>'.$e($project['title']).'</a><br><span class="portfolio-gantt-badge '.$e($class).'"'.$hoverNote.'>'.$e($label).'</span></div>';
      $html .= '<div class="portfolio-gantt-track" style="--today:'.$todayOffset.'%"><span class="portfolio-gantt-today" aria-label="Standlijn vandaag"></span>';
      $start = self::date($project['planned_start'] ?? null);
      $end = self::date($project['planned_end'] ?? null);
      if ($start && $end && $end >= $start) {
        $startDay = (int)$from->diff($start)->format('%r%a');
        $endDay = (int)$from->diff($end)->format('%r%a')+1;
        $left = max(0,min($days,$startDay));
        $right = max(0,min($days,$endDay));
        if ($right > $left) {
          $note = trim((string)($project['critical_note'] ?? ''));
          $title = $note !== '' ? $note : 'Kritiek pad: nog niet vastgesteld';
          $barColor = ['critical'=>'#b91c1c','waiting'=>'#b45309','done'=>'#2563eb','ontrack'=>'#247b55'][$class] ?? '#64748b';
          $html .= '<a class="portfolio-gantt-bar" href="'.$e($url).'" title="'.$e($title).'" aria-label="'.$e($project['title'].': '.$title).'" style="background:'.$barColor.';left:'.round($left/$days*100,3).'%;width:'.round(($right-$left)/$days*100,3).'%"></a>';
        } else {
          $html .= '<span class="portfolio-gantt-empty">Buiten zichtbare periode</span>';
        }
      } else {
        $html .= '<span class="portfolio-gantt-empty">Planning nog niet beschikbaar</span>';
      }
      $html .= '</div></div>';
    }
    return $html.'</div></div>';
  }

  private static function date(mixed $value): ?\DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
  }
}
