# Native CRM-calculatie: samenwerking en revisiemomenten

Deze uitbreiding gebruikt de bestaande `calculations`, `calculation_versions` en `calculation_lines`. Er komt geen tweede bewerkbare calculatie of gekopieerd gebouwmodel. De bestaande financiële totalisering blijft leidend; het scherm leest de opgeslagen directe kosten en toont eenheidsprijzen met vier decimalen.

De maker kan bestaande, actieve Office-accounts met CRM-bewerkrecht expliciet toegang tot één concept geven. Toegang vervalt direct bij intrekking, deactivering, verlies van CRM-recht of wanneer de bestaande CRM-draft-policy het concept vergrendelt. Er worden geen accounts aangemaakt, uitnodigingen verzonden of automatische rechten toegekend.

Elke opslag controleert de geladen canonieke toestand onder dezelfde draft-rijlock als de bestaande opslagadapter. Ook de auditvolgorde telt mee, zodat terugzetten naar dezelfde inhoud geen oud formulier weer geldig maakt. Een conflict geeft HTTP 409 en behoudt de eigen formulierinvoer; de actuele regels blijven zichtbaar voor vergelijking. Mutatie en auteur-/was-wordt-registratie gebruiken dezelfde transactie.

`office_calc_crm_draft_collaborators` bevat uitsluitend Office-rechten. `office_calc_crm_draft_changes` bevat uitsluitend append-only bewijsmomenten en auditgegevens. Een expliciet revisiemoment bewaart de actuele canonieke conceptregels met een reden en checksum. Latere wijzigingen veranderen dit bewijs niet. Dit moment is geen vastgestelde offerteversie, geaccepteerde calculatie of vergrendelde werkbegroting; die bestaande goedkeuringsketen wordt niet overgeslagen.

De nieuwe native ingang is `/office-test/calculatie/samenwerken`. Deze werkt uitsluitend met de bestaande Office-cookiesessie en CRM-draft-toegang. De oude Node-start- en SSO-flags worden niet aangezet. De nieuwe ingang gebruikt dezelfde regels als de oorspronkelijke CRM-calculator. Tijdens activering wordt ook de oorspronkelijke native editor met dezelfde opslagcontrole bijgewerkt; er blijft geen oud formulier over dat de controle stilzwijgend omzeilt.

De installer verifieert de bestaande editor en opslagadapter tegen exact bekende baselines of het laatste private installatiemanifest. Onverwachte serverwijzigingen blokkeren plaatsing. Alleen twee Office-tabellen worden toegevoegd. Bestaande calculatieregels, gebruikerstoekenningen, configuratie en samengestelde routerbestanden worden niet vervangen door een hele repositoryrelease. Back-ups, beperkte route-aanvulling, PHP-validatie en herstel bij een mislukte routecontrole horen bij de plaatsing.

Validatie omvat het bestaande CRM-contract, rechten per concept, directe intrekking, onveranderde revisiemomenten, vier-decimale prijzen, auditrollback, echte HTTP-formulieren en twee onafhankelijke MySQL-schrijvers met één winnende opslag.

De externe Node-werkbank en de tekeningen-/e-mailketen naar canonieke gebouwelementen blijven afzonderlijk werk; deze uitbreiding claimt geen volledige ketenacceptatie daarvan. Geschiedenis en revisiemomenten in deze eerste native uitbreiding volgen de bestaande toegang tot bewerkbare CRM-concepten.

## Materiaalkeuze

De native werkbank kan met afzonderlijk `materials/read`-recht zoeken in de al aangesloten canonieke prijslijst. Bij opnemen controleert de server het leveranciersartikel én de getoonde actuele prijsversie opnieuw. Een browserprijs wordt niet overgenomen. De gekozen gebruikseenheid, prijs, leverancier, artikel-/prijsreferentie, datum en volledige bronomschrijving worden in de bestaande Calc-prijsbronvelden bewaard (het contract uit Calc-migraties 002/003).

Een volgende prijslijstimport wijzigt bestaande calculatieregels niet. Alleen de hoeveelheid aanpassen behoudt de oorspronkelijke prijsreferentie. Handmatig een andere prijs, eenheid of kostensoort invoeren maakt de prijsbron expliciet handmatig; de oorspronkelijke bron blijft in de audit terug te vinden. Een nieuwe prijs tijdens de selectie geeft een conflict en vraagt opnieuw kiezen. Uitval van de prijslijst blokkeert alleen de materiaalkeuze, niet het bewerken van bestaande calculatieregels.

Deze wijziging activeert geen acceptatie naar werkbegroting en rondt geen vier-decimale eenheidsprijzen of fractiehoeveelheden stilzwijgend af om een oudere overdrachtsinterface passend te maken.

Niet-ingelogde browserbezoekers gaan naar het bestaande Office-aanmeldscherm. Anonieme POST-verzoeken blijven geweigerd; aanmelden geeft op zichzelf geen calculatie- of materialenrecht.

Selecteerbare materiaalprijzen vereisen een expliciete Office-reviewregistratie met auteur en tijdstip. Een oudere actieve SALES-import zonder die registratie blijft zichtbaar in het algemene prijslijstoverzicht, maar wordt niet als beoordeeld calculatiemateriaal geselecteerd. Wanneer de nieuwste prijs nog niet beoordeeld is, wordt ook niet stilzwijgend teruggevallen op een oudere beoordeelde prijs.
