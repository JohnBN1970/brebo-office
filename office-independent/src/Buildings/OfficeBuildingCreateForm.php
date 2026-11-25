<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/** Embedded in the host form; host owns CSRF, authorization and submission. */
final class OfficeBuildingCreateForm {
    public static function render(array $values=[]): string {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<fieldset><legend>Nieuw gebouw</legend>';
        foreach (['building_name'=>['Gebouwnaam',160],'street_address'=>['Straat en huisnummer',255],'postal_code'=>['Postcode',32],'city'=>['Plaats',160]] as $key=>[$label,$limit]) {
            $value=$values[$key]??'';
            $html.='<label>'.$h($label).'<input name="new_building['.$key.']" maxlength="'.$limit.'" value="'.$h(is_string($value)?$value:'').'"></label>';
        }
        return $html.'<button type="submit" name="building_action" value="create" formnovalidate>Gebouw toevoegen</button></fieldset>';
    }
}
