<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

/**
 * Presentation-only form. A separately authenticated Office controller must
 * verify CSRF, ACL, scope, revision and dispatch to the repository.
 * Never render editable forms without a trusted action URL and CSRF token.
 */
final class OfficeProjectKickstartFormView
{
    private const ITEMS = [
        'bouwplaatsdocumenten'=>'Bouwplaatsdocumenten gecontroleerd',
        'werkinstructie'=>'Werkinstructie vastgesteld',
        'projectteam'=>'Projectteam samengesteld',
        'logistiek_deelleveringen'=>'Deelleveringen en logistiek afgestemd',
        'kickstart_verslag'=>'Kickstartverslag vastgelegd',
        'open_punten_beoordeeld'=>'Open punten beoordeeld',
    ];

    public static function render(
        array $dossier,
        string $trustedActionUrl,
        string $csrfToken,
        bool $canEdit,
        bool $canSubmit,
        bool $canApprove
    ): string {
        if ($trustedActionUrl === '' || $csrfToken === '') return '';
        $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = (int)($dossier['id'] ?? 0);
        $revision = (int)($dossier['revision'] ?? 0);
        if ($id < 1 || $revision < 1) return '';
        $status = (string)($dossier['status'] ?? '');
        $items = is_array($dossier['items'] ?? null) ? $dossier['items'] : [];
        $hidden = '<input type="hidden" name="csrf_token" value="'.$e($csrfToken).'">'
            .'<input type="hidden" name="dossier_id" value="'.$id.'">'
            .'<input type="hidden" name="expected_revision" value="'.$revision.'">';
        $form = '<section aria-label="Startdossier bewerken"><h3>Startdossier invullen</h3>';
        if ($canEdit) {
            $form .= '<form method="post" action="'.$e($trustedActionUrl).'">'.$hidden
                .'<input type="hidden" name="kickstart_action" value="save_checklist"><fieldset><legend>Controlepunten</legend>';
            foreach (self::ITEMS as $key => $label) {
                $checked = ($items[$key] ?? false) === true ? ' checked' : '';
                $form .= '<label><input type="checkbox" name="items['.$e($key).']" value="1"'.$checked.'> '.$e($label).'</label>';
            }
            $form .= '</fieldset><label>Kritieke open punten <input type="number" name="critical_open_points" min="0" step="1" value="'.max(0,(int)($dossier['critical_open_points'] ?? 0)).'" required></label>'
                .'<button type="submit">Dossier opslaan</button></form>';
            $form .= '<h3>Kickstartvraag of besluit toevoegen</h3><form method="post" action="'.$e($trustedActionUrl).'">'.$hidden
                .'<input type="hidden" name="kickstart_action" value="add_question">'
                .'<label>Vraag <textarea name="question" required maxlength="2000"></textarea></label>'
                .'<label>Antwoord <textarea name="answer" maxlength="10000"></textarea></label>'
                .'<label>Besluit <textarea name="decision" maxlength="10000"></textarea></label>'
                .'<label>Verantwoordelijke (Office-gebruikers-ID) <input type="number" name="owner_user_id" min="1"></label>'
                .'<label>Deadline <input type="date" name="due_date"></label>'
                .'<label><input type="checkbox" name="critical" value="1"> Kritiek punt</label>'
                .'<button type="submit">Vastleggen</button></form>';
        }
        if ($canSubmit && in_array($status, ['draft','changes_requested'], true)) {
            $form .= '<form method="post" action="'.$e($trustedActionUrl).'">'.$hidden
                .'<input type="hidden" name="kickstart_action" value="submit_for_approval">'
                .'<button type="submit">Ter goedkeuring aanbieden</button></form>';
        }
        if ($canApprove && in_array($status, ['awaiting_first_approval','awaiting_second_approval'], true)) {
            $form .= '<form method="post" action="'.$e($trustedActionUrl).'">'.$hidden
                .'<label>Toelichting <textarea name="note" maxlength="2000"></textarea></label>'
                .'<button type="submit" name="kickstart_action" value="approve">Goedkeuren</button>'
                .'<button type="submit" name="kickstart_action" value="reject">Afwijzen</button></form>';
        }
        return $form.'</section>';
    }
}
