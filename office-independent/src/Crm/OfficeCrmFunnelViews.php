<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Server-side, read-only funnel views. Inputs are already access-scoped records. */
final class OfficeCrmFunnelViews {
    private static function h(mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public static function render(array $items, string $view, string $base = '/office-test/crm/funnel'): string {
        $view = OfficeCrmFunnel::normalizeView($view);
        $out = '<nav class="crm-view-switch" aria-label="CRM-weergave">';
        foreach (['kanban'=>'Kanban','list'=>'Lijst','calendar'=>'Kalender'] as $key=>$label) {
            $out .= '<a'.($key===$view?' aria-current="page"':'').' href="'.self::h($base).'?view='.$key.'">'.self::h($label).'</a> ';
        }
        $out .= '</nav>';
        if ($view === 'kanban') {
            $groups = array_fill_keys(array_keys(OfficeCrmFunnel::STAGES), []);
            foreach ($items as $item) {
                $stage = (string)($item['stage'] ?? '');
                if (isset($groups[$stage])) $groups[$stage][] = $item;
            }
            $out .= '<div class="crm-kanban" style="display:flex;gap:12px;overflow-x:auto">';
            foreach ($groups as $stage=>$rows) {
                $out .= '<section data-crm-stage="'.self::h($stage).'" style="min-width:210px;flex:1;border:1px solid #ddd;padding:12px;border-radius:8px"><h2>'.self::h(OfficeCrmFunnel::label($stage)).' ('.count($rows).')</h2>';
                foreach ($rows as $item) $out .= self::card($item);
                $out .= '</section>';
            }
            return $out.'</div>';
        }
        if ($view === 'list') {
            $out .= '<table><thead><tr><th>Relatie / lead</th><th>Fase</th><th>Volgende actie</th><th>Datum</th></tr></thead><tbody>';
            foreach ($items as $item) {
                $out .= '<tr><td>'.self::h($item['name']??'').self::action($item).'</td><td>'.self::h(OfficeCrmFunnel::label((string)($item['stage']??''))).'</td><td>'.self::h($item['next_action']??'').'</td><td>'.self::h($item['due_date']??'').'</td></tr>';
            }
            return $out.'</tbody></table>';
        }
        $calendar = [];
        foreach ($items as $item) {
            $date = (string)($item['due_date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) continue;
            $calendar[$date][] = $item;
        }
        ksort($calendar);
        $out .= '<div class="crm-calendar">';
        foreach ($calendar as $date=>$rows) {
            $out .= '<section><h2>'.self::h($date).'</h2>';
            foreach ($rows as $item) $out .= self::card($item);
            $out .= '</section>';
        }
        return $out.'</div>';
    }
    private static function action(array $item): string {
        $id=filter_var($item['id']??null,FILTER_VALIDATE_INT);
        if ($id===false || $id<1) return '';
        // Every Deal remains reachable from list, calendar and Kanban, even
        // after award or rejection. A project link never replaces Deal access.
        $dealLink=' <a href="/office-test/crm/verkoopkansen/fase?id='.$id.'">Open deal</a>';
        if ((string)($item['stage']??'')==='won') {
            $projectId=filter_var($item['project_id']??null,FILTER_VALIDATE_INT);
            return $dealLink.($projectId!==false && $projectId>0
                ? ' <a href="/office-test/projecten/detail?id='.$projectId.'">Open project</a>'
                : ' <span class="crm-project-handoff-pending">Projectoverdracht nog niet bevestigd</span>');
        }
        if ((string)($item['stage']??'')==='lost') return $dealLink;
        return $dealLink;
    }
    private static function card(array $item): string {
        return '<article draggable="true" data-crm-id="'.(int)($item['id']??0).'" data-crm-stage="'.self::h($item['stage']??'').'" style="padding:10px;margin:8px 0;background:#f4f6f9;border-radius:6px;cursor:grab"><strong>'.self::h($item['name']??'').'</strong>'.self::action($item).'<p>'.self::h($item['next_action']??'').'</p><small>'.self::h($item['due_date']??'').'</small></article>';
    }
}
