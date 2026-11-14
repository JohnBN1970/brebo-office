<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/**
 * Read-only, escaped project preparation view. No persistence or actions.
 * The controller must supply project-authorized data from Office sources.
 */
final class OfficeProjectKickstartView
{
    private const LABELS = [
        'bouwplaatsdocumenten'=>'Bouwplaatsdocumenten',
        'werkinstructie'=>'Werkinstructie',
        'projectteam'=>'Projectteam',
        'logistiek_deelleveringen'=>'Logistiek en deelleveringen',
        'kickstart_verslag'=>'Kickstartoverleg en verslag',
        'open_punten_beoordeeld'=>'Open punten beoordeeld',
    ];
    private const STATUSES = [
        'draft'=>'Concept',
        'changes_requested'=>'Aanpassen na beoordeling',
        'awaiting_first_approval'=>'Wacht op projectleider',
        'awaiting_second_approval'=>'Wacht op tweede goedkeurder',
        'released'=>'Uitvoering vrijgegeven',
    ];
    public static function render(array $dossier, array $questions = [], array $approvals = []): string
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $status = (string)($dossier['status'] ?? 'draft');
        $items = is_array($dossier['items'] ?? null) ? $dossier['items'] : [];
        $revision = (int)($dossier['revision'] ?? 0);
        $html = '<section class="office-kickstart" aria-label="Startdossier en kickstart">'
            . '<h2>Startdossier &amp; Kickstart</h2>'
            . '<p>Status: <strong>'.$e(self::STATUSES[$status] ?? 'Onbekend').'</strong> · Revisie '.$revision.'</p>'
            . '<h3>Voorbereidingscontrole</h3><ul>';
        foreach (self::LABELS as $key => $label) {
            $complete = ($items[$key] ?? false) === true;
            $html .= '<li>'.$e($label).': <strong>'.($complete ? 'Gereed' : 'Openstaand').'</strong></li>';
        }
        $html .= '</ul><h3>Kickstartvragen en besluiten</h3>';
        if ($questions === []) $html .= '<p>Er zijn nog geen vragen of besluiten vastgelegd.</p>';
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $html .= '<article><strong>'.$e($question['question'] ?? '').'</strong>'
                . '<p>Antwoord: '.$e(trim((string)($question['answer'] ?? '')) !== '' ? $question['answer'] : 'Nog open').'</p>'
                . '<p>Besluit: '.$e(trim((string)($question['decision'] ?? '')) !== '' ? $question['decision'] : 'Nog niet vastgelegd').'</p>'
                . '<p>Verantwoordelijke: '.$e($question['owner_label'] ?? 'Niet toegewezen').'</p>'
                . '<p>Deadline: '.$e($question['due_date'] ?: 'Niet vastgesteld').'</p>'
                . (($question['is_critical'] ?? false) && trim((string)($question['decision'] ?? '')) === '' ? '<p><strong>Kritiek punt: besluit ontbreekt</strong></p>' : '')
                . '</article>';
        }
        $html .= '<h3>Goedkeuringshistorie</h3>';
        if ($approvals === []) $html .= '<p>Er zijn nog geen goedkeuringen geregistreerd.</p>';
        $decisions = ['approved_first'=>'Eerste goedkeuring','approved_second'=>'Tweede goedkeuring','rejected'=>'Afgewezen'];
        foreach ($approvals as $approval) {
            if (!is_array($approval)) continue;
            $html .= '<p>'.$e($approval['actor_label'] ?? ('Office-gebruiker #'.(int)($approval['actor_user_id'] ?? 0))).' · '
                .$e($approval['decision_label'] ?? ($decisions[(string)($approval['decision'] ?? '')] ?? 'Onbekend')).' · '
                .$e($approval['created_at'] ?? '').' · Revisie '.(int)($approval['revision'] ?? 0).'</p>';
        }
        return $html.'</section>';
    }
}
