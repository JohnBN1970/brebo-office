# BREBO Office — actuele technische overdracht

Bijgewerkt: 12 augustus 2026

## Doel

Dit bestand is de korte technische overdracht tussen BREBO Office-sessies. Lees dit bij een nieuwe chat vóór nieuwe diagnose of wijziging. Geen secrets opnemen.

## Canonieke bronvolgorde

1. BREBO Proceshandboek v1.0 (primaire functionele bron)
2. CIM / objectmodel
3. BREBO Office / Drupal technische implementatie

Hoofdprincipe: het gebouw staat centraal. De permanente gebouwstructuur is leidend; projectscope is tijdelijk en afgeleid.

## Repository

- Repository: `JohnBN1970/brebo-office`
- Hoofdbranch: `develop`
- PR #176: `Enforce canonical context relations`, mergecommit `dd066578a61f42a27f871fae85160dd09502d06b`
- PR #177: `Harden scope guard SSH setup`, mergecommit `1d7c3541105ec79b2db6aea36d0ec08c22cda2cb`
- PR #178: `Use confirmed Hostinger SSH endpoint for scope guard acceptance`, mergecommit `8628c8a3440b06b0f19199e7d65d49cb2a54588d`

## Hostinger / SBOFFICE

- SSH host: `77.37.34.160`
- SSH port: `65002`
- SSH user: `u213420663`
- Hostinger toont dit endpoint expliciet als `ssh -p 65002 u213420663@77.37.34.160`
- GitHub Actions SSH-key `BREBO Office GitHub Actions` staat in Hostinger.
- Composer-projectroot: `public_html/sboffice_app`
- Drupal root voor Drush: `web`
- Publieke symlink `public_html/sboffice` hoort naar de Composer-projectroot `sboffice_app` te wijzen.
- Een geneste `sboffice_app/web/web` is legacy/vervuiling en wordt door de deployguard geweigerd.

## Mailstatus

- Bedrijfsmail is productie-kritisch.
- MX is op 12 augustus 2026 tijdelijk teruggezet naar Zoho EU omdat medewerkers nog op Zoho werken.
- Zoho EU MX-set: `mx.zoho.eu` (10), `mx2.zoho.eu` (20), `mx3.zoho.eu` (50).
- Definitieve migratie naar Hostinger pas uitvoeren nadat alle mailboxen/aliassen en historische mail gereed en getest zijn; MX pas als laatste omschakelen.
- Hostinger account recovery is nu gekoppeld aan een extern Gmail-adres.

## Scope Guard — bewezen status vóór huidige fix

De acceptatierun `31644286598` bereikte Hostinger succesvol via `77.37.34.160:65002` en voerde de functionele test uit.

Bestaande data was schoon:

- `EXISTING_SCOPE_VIOLATIONS=0`
- `SYNTHETIC_INVALID_SCOPE_VIOLATIONS=1`
- `PERMANENT_ZONES=1`
- `PERMANENT_CLUSTERS=1`
- `PERMANENT_DWELLINGS=1`
- `PERMANENT_POSITIONS=24`
- `SYNTHETIC_INVALID_HIERARCHY_VIOLATIONS=1`
- `CONTEXT_COMMUNICATIONS=90`
- `CONTEXT_WORK_PACKAGES=1`
- `CONTEXT_VERIFICATIONS=4`
- `CONTEXT_DEVIATIONS=1`

De enige inhoudelijke fout was:

- `SYNTHETIC_INVALID_CONTEXT_VIOLATIONS=0`
- foutmelding: `Synthetic invalid project/building context was not rejected.`

Oorzaak: een ingevulde entity-reference met een niet-bestaand target-ID resolveerde naar `NULL` en werd bij optionele communicatiecontext ten onrechte hetzelfde behandeld als een leeg veld.

## Huidige werkstap

Branch `fix/context-guard-invalid-references` bevat commit `3a3e54868d29cd1f9e49af6ce59e17574cdd4228`. De wijziging maakt onderscheid tussen een leeg referenceveld en een ingevuld maar onoplosbaar target-ID, ook voor relevante optionele contextrelaties.

Volgende stap: deze fix via PR mergen naar `develop`, automatische Scope Guard-acceptatie uitlezen en alleen afronden als `SYNTHETIC_INVALID_CONTEXT_VIOLATIONS` groter dan 0 is en `CANONICAL_CONTEXT_GUARD=PASS` verschijnt.
