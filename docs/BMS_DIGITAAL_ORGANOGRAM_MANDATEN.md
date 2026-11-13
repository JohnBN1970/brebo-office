# BREBO BMS — Digitaal organogram en mandaten

**Status:** Ontwerpvertaling; geen normatieve wijziging van Proceshandboek v1.0.
**Bronhiërarchie:** beheerd Proceshandboek (PH-100) → formeel vastgestelde wijzigingen → CIM/BMS → Office-implementatie.

## Uitgangspunt

Het bestaande BREBO Proceshandboek beschrijft de normatieve rollen en Virtuele Staf.
De BMS/CIM-aansluitcontrole registreert onder O-007 dat de volledige rollen- en mandatenmatrix nog technisch moet worden aangesloten. Het organogram is een **weergave van dezelfde beheerde rollen**, geen nieuw handboek of tweede autorisatiesysteem.

## Canonieke relaties

- Medewerker: bestaande Office-personeelsidentiteit en actieve status.
- Office-operator: bestaande `office_operator_accounts`; niet elke medewerker heeft een login.
- Organisatorische functie: verwijst naar een vastgestelde rol uit het Proceshandboek, met bronreferentie en geldigheidsperiode.
- Positie in organogram: rapporteert aan een andere positie, inclusief vervanging/waarneming met geldigheidsperiode.
- Mandaat: expliciete actie, scope, grenzen, voorwaarden, onafhankelijkheidsregel en formele bron.
- Toewijzing: medewerker/operator ↔ positie/mandaat, met ingangsdatum, einddatum en bevoegde vaststeller.
- Audit: bij elk materieel besluit actor, besluit, mandaat, bronversie, tijdstip, motivatie en eventuele tweede beoordelaar.

**Geen rechten afleiden uit uitsluitend rangnummer, organogrampositie of projectlidmaatschap.**
Autorisatie is server-side en fail-closed. Een waarnemer krijgt alleen expliciet gedelegeerde mandaten.

## Urencontrole: besluitmodel

Mandaat `external_hours_approve` vereist geverifieerde Office-sessie, actieve operator, projectscope, geverifieerde claim/OnSite-/factuurbron en twee verschillende bevoegde beoordelaars.

Uitzondering `external_hours_single_approval` vereist daarnaast een **expliciet door het BMS vastgesteld hoogste-rolmandaat**; dit volgt niet automatisch uit projectbeheer of de naam 'directeur'. Zelfstandig goedkeuren vereist een gedocumenteerde reden. De audit bewaart `approval_mode=highest_role_single` en de oorspronkelijke urenbronnen blijven onveranderd.

De MySQL-auditmigratie is voorbereid; totdat de formele rolbron, autorisatieadapter, auditregistratie en end-to-end-tests zijn aangesloten, blijft zelfstandig goedkeuren functioneel uit.

## Implementatievolgorde

1. Identificeer de beheerste bronbestanden en exacte zestien rollen, rolcodes, mandaten en onafhankelijkheidsregels. **Geen rollen verzinnen.**
2. Controleer bestaande Office-personeel-, operator-, project- en permissietabellen; leg de canonieke sleutels vast.
3. Maak een read-only organogram met bronverwijzingen en geldigheid, zonder impliciete autorisatie.
4. Bouw één autorisatieadapter die expliciete mandaten uit beheerde BMS-bronnen en bestaande Office-sessie/projectrechten controleert.
5. Sluit de externe-urenbesluitservice op die adapter aan; registreer rol/mandaatbewijs en uitzonderingsreden in de audit.
6. Test bevoegd/onbevoegd, waarneming, verlopen mandaat, onafhankelijke tweede beoordelaar, racecondities en herhaalverzoeken; pas daarna gecontroleerde activering.

## Wijzigingsbeheer

Rollen, rangorde, onafhankelijkheidsregels en bevoegdheidsgrenzen worden niet via code of UI stilzwijgend als nieuw beleid ingevoerd. Wijzigingen lopen via het bestaande BMS-versiebeheer en worden daarna naar Office vertaald.
