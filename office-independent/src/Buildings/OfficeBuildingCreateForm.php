<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/** Embedded in the host form; host owns CSRF, authorization and submission. */
final class OfficeBuildingCreateForm {
    public static function searchQuery(array $values): string {
        $parts=[];
        foreach (['street_address','postal_code','city'] as $key) {
            $v=$values[$key]??'';
            if (!is_string($v)) throw new \DomainException('invalid_address');
            if (trim($v)!=='') $parts[]=trim($v);
        }
        return implode(' ',$parts);
    }
    public static function render(array $values=[]): string {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<fieldset><legend>Nieuw gebouw</legend>';
        foreach (['building_name'=>['Gebouwnaam',160],'street_address'=>['Straat en huisnummer',255],'postal_code'=>['Postcode',32],'city'=>['Plaats',160]] as $key=>[$label,$limit]) {
            $value=$values[$key]??'';
            $html.='<p><label>'.$h($label).'<br><input name="new_building['.$key.']" maxlength="'.$limit.'" value="'.$h(is_string($value)?$value:'').'"></label></p>';
        }
        if (getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED')==='enabled') {
            $bag=$values['bag_id']??'';
            $html.='<p><button type="submit" name="building_action" value="search_pdok" formnovalidate>Zoeken in BAG op ingevuld adres</button></p>';
            if (is_array($values['pdok_results']??null)) {
                foreach ($values['pdok_results'] as $row) {
                    $html.='<label><input type="radio" name="new_building[bag_id]" value="'.$h($row['bag_id']).'">'.$h($row['street_address'].' '.$row['postal_code'].' '.$row['city']).'</label>';
                }
            }
            if (empty($values['pdok_results'])) $html.='<label>Officiële BAG-adresidentificatie (optioneel)<input name="new_building[bag_id]" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" value="'.$h(is_string($bag)?$bag:'').'"></label>';
            $html.='<p>Bij een BAG-identificatie worden de officiële adresgegevens bij opslag gecontroleerd en overgenomen.</p>';
        }
        return $html.'<button type="submit" name="building_action" value="create" formnovalidate>Gebouw toevoegen</button></fieldset>';
    }
}
