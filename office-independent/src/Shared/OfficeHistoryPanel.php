<?php
declare(strict_types=1);

namespace Brebo\Office\Shared;

/**
 * Shared read-only history panel. Modules supply authorized, normalized events.
 * This renderer never queries data or changes workflow state.
 */
final class OfficeHistoryPanel
{
    /**
     * @param list<array{actor:string,action:string,occurred_at:string,detail?:string}> $events
     */
    public static function render(array $events, string $heading = 'Historie'): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<aside class="office-history-panel" aria-label="'.$escape($heading).'">';
        $html .= '<h2>'.$escape($heading).'</h2>';
        if ($events === []) {
            return $html.'<p class="office-history-empty">Nog geen gebeurtenissen.</p></aside>';
        }
        $html .= '<ol class="office-history-list">';
        foreach ($events as $event) {
            $actor = $escape((string) ($event['actor'] ?? 'Systeem'));
            $action = $escape((string) ($event['action'] ?? 'Gebeurtenis'));
            $date = $escape((string) ($event['occurred_at'] ?? ''));
            $detail = $escape((string) ($event['detail'] ?? ''));
            $html .= '<li class="office-history-event"><strong>'.$action.'</strong>';
            $html .= '<span class="office-history-meta">'.$actor.' · '.$date.'</span>';
            if ($detail !== '') {
                $html .= '<p>'.$detail.'</p>';
            }
            $html .= '</li>';
        }
        return $html.'</ol></aside>';
    }
}
