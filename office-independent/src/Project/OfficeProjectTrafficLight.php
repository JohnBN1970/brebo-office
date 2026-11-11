<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/** Read-only traffic light; unknown planning never becomes green. */
final class OfficeProjectTrafficLight {
  public static function assess(array $project): array {
    $status = strtolower(trim((string)($project['status'] ?? '')));
    $critical = [];
    $attention = [];
    foreach (($project['plan_items'] ?? []) as $phase) {
      $state = strtolower(trim((string)($phase['status'] ?? '')));
      $note = trim((string)($phase['critical_note'] ?? ''));
      $title = trim((string)($phase['title'] ?? 'Planningsfase'));
      $reason = $title . ': ' . ($note !== '' ? $note : 'Status ' . ($state !== '' ? $state : 'onbekend'));
      if (in_array($state, ['critical','kritiek'], true) || $note !== '') $critical[] = $reason;
      elseif (in_array($state, ['waiting','in wacht','on hold'], true)) $attention[] = $reason;
    }
    if ($critical !== [] || in_array($status, ['critical','kritiek'], true)) {
      return ['color'=>'red','label'=>'Kritiek','reason'=>implode(' | ', array_unique($critical)) ?: 'Projectstatus kritiek'];
    }
    if ($attention !== [] || in_array($status, ['waiting','in wacht','on hold'], true)) {
      return ['color'=>'orange','label'=>'Aandacht','reason'=>implode(' | ', array_unique($attention)) ?: 'Projectstatus vraagt aandacht'];
    }
    if (in_array($status, ['gereed','completed','afgevoerd','vervallen'], true)) {
      return ['color'=>'gray','label'=>'Afgesloten','reason'=>'Project is afgesloten'];
    }
    $items = $project['plan_items'] ?? [];
    if ($items !== [] && count(array_filter($items, static fn(array $p): bool => !in_array(strtolower(trim((string)($p['status'] ?? ''))), ['ontrack','op schema','done','gereed','planned','gepland'], true))) === 0) {
      return ['color'=>'green','label'=>'Op schema','reason'=>'Geen geregistreerde knelpunten in de planning'];
    }
    return ['color'=>'gray','label'=>'Onbekend','reason'=>'Planning of risicostatus onvoldoende vastgesteld'];
  }
  public static function render(array $project): string {
    $light = self::assess($project);
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $colors = ['red'=>'#b91c1c','orange'=>'#b45309','green'=>'#15803d','gray'=>'#64748b'];
    return '<span class="office-traffic-light" title="'.$e($light['reason']).'" aria-label="'.$e($light['label'].': '.$light['reason']).'" style="display:inline-flex;align-items:center;gap:7px;cursor:help"><span aria-hidden="true" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:'.$colors[$light['color']].'"></span><span>'.$e($light['label']).'</span></span>';
  }
}
