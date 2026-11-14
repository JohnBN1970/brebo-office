<?php
declare(strict_types=1);
// Included only by the authenticated workbench; never register as a public route.
if(!isset($collaboration,$draft,$actor,$h)||$collaboration===null)return;
$formFields=static function()use($draft,$csrf,$expectedRevision,$h):string{return '<input type="hidden" name="draft_id" value="'.$draft.'"><input type="hidden" name="csrf" value="'.$h($csrf).'"><input type="hidden" name="expected_revision" value="'.$h($expectedRevision).'">';};
echo '<section class="panel"><h2>Revisiemoment vastleggen</h2><p class="muted">Bewaar de huidige conceptregels met een reden. Een revisiemoment blijft ongewijzigd beschikbaar; het stelt geen offerte of werkbegroting vast.</p><form method="post" action="/office-test/calculatie">'.$formFields().'<input type="hidden" name="action" value="checkpoint"><label>Reden <input name="reason" maxlength="1000" required placeholder="Bijvoorbeeld: controle na nieuwe kozijnstaat"></label><button type="submit">Revisiemoment bewaren</button></form></section>';
echo '<section class="panel"><h2>Bevoegde gebruikers</h2><p class="muted">De maker beheert de bewerkrechten voor deze calculatie. Elk account moet ook actief zijn en CRM-bewerkrecht hebben.</p><ul>';
foreach($members as$member){echo '<li>'.$h((string)$member['username']).' (#'.$h((string)$member['operator_id']).')'.((int)$member['active']===1?'':' · inactief');if($canManageCollaborators)echo '<form method="post" action="/office-test/calculatie">'.$formFields().'<input type="hidden" name="action" value="remove"><input type="hidden" name="operator_id" value="'.$h((string)$member['operator_id']).'"><button type="submit">Bewerkrecht intrekken</button></form>';echo '</li>';}
echo '</ul>';
if($canManageCollaborators){echo '<form method="post" action="/office-test/calculatie">'.$formFields().'<input type="hidden" name="action" value="grant"><label>Gebruiker <select name="operator_id" required><option value="">Kies een bevoegde gebruiker</option>';foreach($eligible as$person){if((string)$person['id']===(string)$actor||!ctype_digit((string)$person['id']))continue;echo '<option value="'.$h((string)$person['id']).'">'.$h((string)$person['username']).' (#'.$h((string)$person['id']).')</option>';}echo '</select></label><button type="submit">Bewerkrecht geven</button></form>';}
echo '</section><section class="panel"><h2>Wijzigingen en revisiemomenten</h2><p class="muted">De laatste 50 registraties. Open een registratie voor de vastgelegde regels en het verschil.</p><table><thead><tr><th>Registratie</th><th>Gebruiker</th><th>Datum</th><th>Reden</th></tr></thead><tbody>';
$kinds=['line_saved'=>'Regel gewijzigd','collaborator_granted'=>'Bewerkrecht gegeven','collaborator_removed'=>'Bewerkrecht ingetrokken','revision_saved'=>'Revisiemoment'];
foreach($history as$event){$time=(new DateTimeImmutable((string)$event['created_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s');echo '<tr><td><a href="/office-test/calculatie?draft_id='.$draft.'&amp;change_id='.(int)$event['id'].'#wijzigingsdetail">#'.(int)$event['id'].' · '.$h($kinds[$event['event_kind']]??(string)$event['event_kind']).'</a></td><td>'.$h((string)($event['username']??'Office-gebruiker')).' (#'.$h((string)$event['actor_id']).')</td><td>'.$h($time).'</td><td>'.$h((string)$event['reason']).'</td></tr>';}
if($history===[])echo '<tr><td colspan="4">Nog geen wijzigingen geregistreerd sinds de activering.</td></tr>';
echo '</tbody></table></section>';
if($auditDetail!==null){
 $before=json_decode((string)$auditDetail['before_json'],true,64,JSON_THROW_ON_ERROR);$after=json_decode((string)$auditDetail['after_json'],true,64,JSON_THROW_ON_ERROR);
 $old=[];$new=[];foreach($before['lines']??[]as$line)$old[(string)($line['code']??$line['id'])]=$line;foreach($after['lines']??[]as$line)$new[(string)($line['code']??$line['id'])]=$line;
 echo '<section class="panel" id="wijzigingsdetail"><h2>Vastgelegde registratie #'.(int)$auditDetail['id'].'</h2><p>'.$h((string)$auditDetail['reason']).'</p><p class="muted">Controlecode '.$h((string)$auditDetail['content_hash']).'</p>';
 if($old!==[]||$new!==[]){
  $cost=static function(?array $line):string{if($line===null)return '—';$total=0.0;foreach(['labour_unit_cost','material_unit_cost','equipment_unit_cost','subcontracting_unit_cost','other_unit_cost']as$column)$total+=(float)($line[$column]??0);return '€ '.number_format($total,4,',','.');};
  echo '<div style="overflow:auto"><table><thead><tr><th>Omschrijving</th><th>Aantal was</th><th>Aantal wordt</th><th>Eenheidskosten was</th><th>Eenheidskosten wordt</th></tr></thead><tbody>';
  foreach(array_unique(array_merge(array_keys($old),array_keys($new)))as$key){$a=$old[$key]??null;$b=$new[$key]??null;if($auditDetail['event_kind']!=='revision_saved'&&$a===$b)continue;echo '<tr><td>'.$h((string)($b['description']??$a['description']??$key)).'</td><td>'.$h((string)($a['quantity']??'—')).'</td><td>'.$h((string)($b['quantity']??'—')).'</td><td>'.$h($cost($a)).'</td><td>'.$h($cost($b)).'</td></tr>';}
  echo '</tbody></table></div>';
 }else echo '<p>'.$h(json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)).'</p>';
 echo '</section>';
}
