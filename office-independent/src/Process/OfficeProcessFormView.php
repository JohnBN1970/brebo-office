<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/**
 * Safe shared catalog/instance view. Host controller supplies authenticated
 * permission decisions and CSRF token; no permissions inferred from UI state.
 * Field definitions remain document-specific and must be source-verified.
 */
final class OfficeProcessFormView
{
    public static function catalog(array $permittedCodes): string
    {
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        $html = '<section class="office-process-forms"><h2>Proceshandboekformulieren</h2><ul>';
        foreach (OfficeProcessFormCatalog::all() as $code => [$title,$module]) {
            if (!in_array($code,$permittedCodes,true)) continue;
            $html .= '<li><strong>'.self::esc($code).'</strong> '.self::esc($title).' <small>'.self::esc($module).'</small></li>';
        }
        return $html.'</ul></section>';
    }

    public static function instance(array $form, array $fields, string $csrf, bool $canEdit): string
    {
        $code=(string)($form['document_code']??'');
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        OfficeProcessFormCatalog::requireCode($code);
        $status=(string)($form['status']??'concept');
        $revision=(int)($form['revision']??0);
        $data=$form['data']??[];
        if (!is_array($data)) throw new \InvalidArgumentException('Invalid form data');
        $html='<section class="office-process-form"><h2>'.self::esc($code).' — '.self::esc(OfficeProcessFormCatalog::requireCode($code)[0]).'</h2>';
        $html.='<p>Status: '.self::esc($status).' | Revisie: '.$revision.'</p>';
        if ($canEdit && $status==='concept') {
            $html.='<form method="post"><input type="hidden" name="csrf_token" value="'.self::esc($csrf).'"><input type="hidden" name="form_id" value="'.(int)($form['id']??0).'"><input type="hidden" name="expected_revision" value="'.$revision.'">';
        }
        foreach ($fields as $key=>$label) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,79}$/',$key) || !is_string($label)) continue;
            $value=(string)($data[$key]??'');
            $html.='<label>'.self::esc($label).' ';
            if ($canEdit && $status==='concept') {
                $html.='<textarea name="fields['.self::esc($key).']">'.self::esc($value).'</textarea>';
            } else {
                $html.='<span>'.nl2br(self::esc($value),false).'</span>';
            }
            $html.='</label>';
        }
        if ($canEdit && $status==='concept') {
            $html.='<label>Reden wijziging <input name="change_reason" required maxlength="500"></label><button type="submit">Concept opslaan</button></form>';
        }
        return $html.'</section>';
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    }
}
