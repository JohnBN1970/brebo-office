# Appendix A — Vastgestelde aanvullingen en ontwikkelbesluiten na Proceshandboek v1.0

## Status en doel

Dit document is de beheerste ontwikkelappendix bij het **BREBO Proceshandboek, compleet beheerst exemplaar, versie 1.0 van 7 augustus 2026**.

Het Proceshandboek blijft de normatieve functionele hoofdbron. Deze appendix registreert aanvullingen, interpretatiebesluiten en ontwikkelbesluiten die na de peildatum van het handboek tijdens de ontwikkeling van BREBO Office zijn vastgesteld of als beheerste ontwikkelregel zijn ingevoerd.

De appendix wijzigt PH-100 of andere vastgestelde procesdocumenten niet stilzwijgend. Wanneer een punt een normatieve wijziging van het Proceshandboek vereist, moet dit via het bestaande wijzigings- en versiebeheer worden verwerkt.

### Statuswaarden

- **Vastgesteld — ontwikkeltraject**: expliciet akkoord gegeven en leidend voor BREBO Office.
- **Bevestiging bestaande norm**: geen nieuwe norm; softwarematige concretisering van reeds vastgestelde BMS-regels.
- **Werkafspraak**: operationele ontwikkelregel; nog geen wijziging van het Proceshandboek.
- **Open verificatie**: beschikbaar bewijs is nog niet voldoende om het punt als volledig gereconstrueerd te beschouwen.

---

## A-001 — Gebouw is de permanente kern van BREBO Office

**Status:** Bevestiging bestaande norm + vastgesteld voor de softwarevertaling.

**Besluit**

Het gebouw staat centraal in de informatiearchitectuur. Een project is een tijdelijke uitvoeringseenheid rond werkzaamheden aan een of meer gebouwen en mag niet als permanente eigenaar van gebouwinformatie worden gemodelleerd.

**Gevolg voor CIM**

- Gebouw is een permanent kernobject.
- Project verwijst naar gebouw(en).
- Historie van inspecties, werkzaamheden, kwaliteit, klachten, garanties en onderhoud blijft over meerdere projecten aan het gebouw gekoppeld.

**Gevolg voor BREBO Office**

De eerder projectcentrisch ingezette Projectdossier-opzet wordt niet als leidend model doorgezet. Eerst wordt de gebouwgerichte objectstructuur geborgd; projectinformatie wordt daarop aangesloten.

**Bronnen / herleidbaarheid**

- PH-100: hoofdprincipe "Het gebouw staat centraal".
- BMS/CIM/domeinmodel in de beheerde bibliotheek.
- Ontwikkelbesluit in vervolgtraject BREBO Office, 11 augustus 2026.

---

## A-002 — BMS en CIM gaan vóór Drupal

**Status:** Bevestiging bestaande norm + vastgesteld voor de ontwikkelvolgorde.

**Besluit**

BREBO Office wordt niet vanuit beschikbare Drupal-contenttypen ontworpen. Het procesmodel en het Canonical Information Model zijn leidend; Drupal is de technische vertaling.

**Vaste ontwikkelvolgorde**

1. BMS / procesarchitectuur;
2. CIM en objectrelaties;
3. Drupal-contentmodel;
4. API's en integraties;
5. workflows;
6. AI-ondersteuning.

**Beheersregel**

Bestaande objecten worden eerst onderzocht en hergebruikt voordat parallelle objectmodellen worden toegevoegd. Dit geldt onder meer voor bestaande BREBO Office-objecten zoals cluster, dwelling en product position.

---

## A-003 — Eén beheerste dossierwaarheid; kanalen zijn aanvoer

**Status:** Bevestiging bestaande norm + vastgesteld voor de implementatie.

**Besluit**

E-mail, WhatsApp, telefoon, foto's, video, bestanden en andere communicatiekanalen zijn aanvoerkanalen. Zij vormen niet zelfstandig de blijvende primaire waarheid.

**Gevolg**

Materiële informatie wordt opgenomen in het centrale object- en dossiermodel met behoud van bron, datum, afzender, betrouwbaarheid, bewijsstatus en relaties naar gebouw, project, bouwdeel, partij en vervolgactie waar relevant.

Persoonlijke mailboxen en losse berichten mogen daardoor niet als schaduwdossier functioneren.

---

## A-004 — Migrerende Mail Intake

**Status:** Vastgesteld — ontwikkeltraject.

**Doel**

Bestaande en nieuwe e-mail gecontroleerd laten instromen naar de centrale BREBO Office-dossiers.

**Vaste functionele uitgangspunten**

- originele e-mail en bijlagen blijven als bronbewijs herleidbaar;
- afzender, onderwerp, datum, inhoud en bijlagen worden als bronmetadata verwerkt;
- het systeem herkent of stelt gebouw/project/partij voor;
- inhoud wordt geclassificeerd, bijvoorbeeld offerte, planning, klacht, foto, bewonersmelding, meerwerk, inkoop, oplevering of garantie;
- feiten, acties, risico's en benodigde controles worden als afzonderlijke objecten voorgesteld of gekoppeld;
- duplicaten worden voorkomen;
- bij onvoldoende zekerheid wordt geen koppeling als feit gepresenteerd maar een controlepunt aangemaakt;
- de mailbox wordt geen tweede waarheid naast het dossier.

**Volgorde**

Mail Intake wordt pas verder uitgebouwd nadat de centrale gebouw-/projectrelaties voldoende stabiel zijn.

---

## A-005 — Digitale handelingsniveaus

**Status:** Vastgesteld — ontwikkeltraject.

Digitale rollen werken met drie handelingsniveaus:

1. **Signaleren** — autonoom toegestaan binnen de beschikbare betrouwbare informatie.
2. **Voorstellen / voorbereiden** — autonoom toegestaan als concept, inclusief onderbouwing en gevolgen.
3. **Beslissen / extern handelen** — alleen autonoom waar een aantoonbaar mandaat dat toelaat; materiële technische, financiële, contractuele, veiligheidskundige of formele besluiten vereisen waar van toepassing menselijke bevoegdheid en controle.

Deze indeling concretiseert de bestaande handboekregel dat digitale rollen en AI ondersteunen, signaleren en voorbereiden maar menselijke eindverantwoordelijkheid niet vervangen.

---

## A-006 — Tevredenheid en feedback als structurele leerdata

**Status:** Vastgesteld — ontwikkeltraject.

BREBO Office registreert niet alleen technisch/projectmatig resultaat, maar ook hoe werk en communicatie worden ervaren. Feedback moet bruikbaar zijn voor aantoonbaar leren.

**Minimale onderwerpen**

- communicatie en duidelijkheid;
- bereikbaarheid;
- reactiesnelheid;
- nakomen van afspraken;
- kwaliteit van uitvoering;
- ervaren overlast;
- klachtbehandeling;
- oplevering;
- algemeen oordeel.

**Leerkring**

Communicatie/gebeurtenis → reactie → tevredenheid → oorzaak → verbeteractie → gewijzigde werkwijze → opnieuw meten.

Feedback bevat waar mogelijk score én kwalitatieve reden, positief punt, negatief punt en verbeterpunt. Vervolgacties worden herleidbaar gekoppeld.

---

## A-007 — Centrale functionele bouwvolgorde BREBO Office

**Status:** Vastgesteld — ontwikkeltraject.

De functionele bouwvolgorde is na correctie van de projectcentrische aanpak:

1. gebouwendossier en fysieke objectstructuur;
2. projecten en projectrelaties aan gebouwen;
3. informatie- en bronregistratie;
4. Migrerende Mail Intake;
5. actie-, signaal- en controlemotor;
6. digitale rollen op dezelfde dossierwaarheid;
7. dashboards en managementinformatie;
8. verdere leer- en feedbackfuncties over projecten en gebouwen heen.

Een onderdeel wordt niet geïsoleerd gebouwd wanneer daarvoor een onderliggende objectrelatie nog niet stabiel is.

---

## A-008 — Duurzame broncode is GitHub

**Status:** Werkafspraak, vastgesteld binnen het ontwikkeltraject.

Belangrijke functionele implementatie geldt niet als duurzaam gerealiseerd zolang zij uitsluitend in een tijdelijke ontwikkel-/Codex-omgeving bestaat.

**Regel**

- GitHub is de duurzame technische bron;
- functionele wijzigingen worden op een echte branch vastgelegd en via pull request beoordeeld;
- tijdelijke code zonder GitHub-commit is reproduceerbaar werk, geen afgeronde implementatie;
- deployment naar de server vervangt bronbeheer niet.

Deze regel is ingevoerd nadat een tijdelijke Projectdossier-implementatie verloren ging en niet uit Git-objecten kon worden hersteld.

---

## A-009 — Integration API en HMAC v1 niet verzwakken

**Status:** Werkafspraak / gerealiseerde technische beheersregel.

De bestaande Integration API-client, request signer en HMAC-v1-authenticatie zijn de actuele technische beveiligingsbasis voor de testkoppeling en mogen bij functionele uitbreiding niet stilzwijgend worden omzeild of afgezwakt.

Het healthcheck-script gebruikt de bestaande client en signer en implementeert geen parallelle HMAC-logica.

De end-to-end healthcheck met echte shared secret blijft een afzonderlijke operationele verificatie zolang het secret niet veilig beschikbaar is in het uitvoerende proces.

---

## A-010 — Appendix A is een tussenlaag, geen tweede handboek

**Status:** Vastgesteld voor documentbeheer van het ontwikkeltraject.

Appendix A bewaart de herleidbaarheid tussen Proceshandboek v1.0 en de volgende formele revisie. Het document:

- dupliceert het Proceshandboek niet;
- maakt zichtbaar wat na 7 augustus 2026 is bevestigd, aangevuld of als ontwikkelregel is vastgesteld;
- vermeldt expliciet wanneer iets slechts een technische werkafspraak is;
- wordt bij een volgende formele handboekrevisie punt voor punt verwerkt, ingetrokken of als blijvende technische regel aangewezen;
- mag geen onbewezen reconstructie als vastgesteld besluit presenteren.

---

## Open reconstructiepunten

De huidige appendix is gereconstrueerd uit het beheerde Proceshandboek, de beschikbare BREBO-bibliotheek, de duurzame GitHub-geschiedenis en de beschikbare vervolgcontext van de ontwikkelchat.

Omdat de oorspronkelijke chat **"Geen lege resultaten"** niet in deze vervolgchat regel voor regel beschikbaar is, blijven eventuele besluiten die uitsluitend in die volle chat stonden **open verificatie** totdat zij uit een duurzame bron, bibliotheekbestand of andere beschikbare chatcontext kunnen worden bevestigd.

Bij nieuwe bevestiging wordt dit document aangevuld met datum, besluitstatus, geraakte proces-/CIM-objecten en technische gevolgen.
