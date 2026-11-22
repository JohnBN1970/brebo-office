<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * FR-201 supplement form for the existing CRM lead.
 * Controller must authenticate, authorize lead access, validate CSRF and
 * dispatch to OfficeFr201Repository. This renderer performs NO writes.
 */
final class OfficeFr201FormView
{
    /** Lead master fields are displayed by CRM, never duplicated here. */
    private const MASTER_FIELDS = [
        'channel', 'source_reference', 'contact_name', 'contact_email',
        'customer_question', 'relation_id', 'owner_user_id', 'received_at',
    ];

    public static function render(
        int $leadId,
        array $supplement,
        int $revision,
        string $trustedActionUrl,
        string $csrfToken,
        bool $canEdit
    ): string {
        if ($leadId < 1 || $revision < 0 || $trustedActionUrl === '' || $csrfToken === '') return '';
        require_once __DIR__.'/OfficeFr201Intake.php';
        $h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="office-fr201" aria-label="FR-201 klantvraag en intake">'
            .'<h3>FR-201 · Klantvraag en intake <small>v1.0</small></h3>'
            .'<p>Vul alleen de ontbrekende intakegegevens aan. De oorspronkelijke lead en herkomst blijven leidend.</p>';
        if (!$canEdit) {
            $html .= '<p>Alleen lezen; geen bewerkrechten.</p><dl>';
            foreach (OfficeFr201Intake::fields() as $key => $field) {
                if (in_array($key, self::MASTER_FIELDS, true)) continue;
                $value=$supplement[$key]??'';
                if (!is_string($value) || trim($value)==='') continue;
                $html .= '<dt>'.$h($field['label']).'</dt><dd>'.nl2br($h($value),false).'</dd>';
            }
            return $html.'</dl></section>';
        }
        $html .= '<form method="post" action="'.$h($trustedActionUrl).'">'
            .'<input type="hidden" name="csrf_token" value="'.$h($csrfToken).'">'
            .'<input type="hidden" name="lead_id" value="'.$leadId.'">'
            .'<input type="hidden" name="expected_revision" value="'.$revision.'">'
            .'<input type="hidden" name="fr201_action" value="save_supplement">';
        foreach (OfficeFr201Intake::fields() as $key => $field) {
            if (in_array($key, self::MASTER_FIELDS, true)) continue;
            $value = $supplement[$key] ?? '';
            if (!is_string($value)) $value = '';
            $html .= '<label>'.$h($field['label'])
                .'<textarea name="fr201['.$h($key).']" rows="2" maxlength="10000">'.$h($value).'</textarea></label>';
        }
        $html .= '<label>Reden van wijziging <input type="text" name="change_reason" maxlength="500" required></label>'
            .'<button type="submit">Aanvullingen opslaan</button></form></section>';
        return $html;
    }
}
