# BREBO Office-roadmap

## Doel

BREBO Office gefaseerd ontwikkelen tot een betrouwbaar digitaal bedrijfs- en gebouwmanagementsysteem waarin het gebouw de permanente informatiekern vormt, projecten tijdelijke uitvoeringseenheden zijn en informatie, bewijs, risico's, besluiten en leerpunten beheerst en herleidbaar worden vastgelegd.

## Peildatum

11 augustus 2026.

## Leidende bronnen

De roadmap volgt de volgende bronvolgorde:

1. het vastgestelde BREBO Proceshandboek;
2. het Canonical Information Model (CIM) en het domein-/objectmodel;
3. Appendix A voor vastgestelde aanvullingen en ontwikkelbesluiten na de peildatum van het Proceshandboek;
4. de actuele Drupal-implementatie en technische documentatie.

Bij strijdigheid wordt niet vanuit de software teruggeredeneerd naar de bedrijfsnorm. De software wordt aangepast aan de vastgestelde functionele bron.

## Fase 0 — Procesfundering en governance

**Status: gerealiseerd als functionele bron**

- Proceshandboek v1.0 is beschikbaar als inhoudelijk vastgestelde procesarchitectuur.
- Hoofdprincipe: het gebouw staat centraal.
- De BREBO Lens Inzicht — Regie — Realisatie is leidend.
- Eén keer vastleggen, overal hergebruiken geldt als vast werkprincipe.
- Digitale rollen en AI ondersteunen; formele bevoegdheid en menselijke eindverantwoordelijkheid blijven bestaan.
- Appendix A bewaakt aanvullingen en ontwikkelbesluiten na de handboekpeildatum zonder het handboek stilzwijgend te wijzigen.

## Fase 1 — Technische basis en Integration API

**Status: grotendeels gereed**

- Beveiligde Drupal ↔ Integration API-koppeling is gerealiseerd.
- HMAC v1-authenticatie blijft de geldende beveiligingslaag.
- Fictieve communicatie is end-to-end via de Integration API verwerkt.
- Deployment via GitHub Actions naar de SBOFFICE-runtime is ingericht.
- De Worker healthcheck is als veilig Drush-script in de deployment opgenomen.
- De laatste echte end-to-end healthcheck met `BREBO_SHARED_SECRET` staat geparkeerd totdat het secret weer veilig beschikbaar is.

Deze geparkeerde controle blokkeert de functionele ontwikkeling niet.

## Fase 2 — Canoniek gebouw- en projectmodel

**Status: grotendeels gebouwd; nu consolideren**

De huidige Drupal-code bevat reeds:

- `brebo_building` als permanent gebouwobject;
- `brebo_building_zone` voor permanente technische gebouwzones;
- `brebo_cluster`;
- `brebo_dwelling`;
- `brebo_product_position`;
- `brebo_project` als tijdelijke opdracht;
- `brebo_project_scope` als tijdelijke selectie van objecten binnen een gebouw voor een project.

De canonieke richting is:

```text
Gebouw
  -> gebouwzone / cluster
      -> woning / ruimte
          -> productpositie

Project
  -> projectscope per gebouw
      -> selectie van permanente gebouwobjecten
```

### Huidige consolidatiepunten

1. De historische verplichte relatie `Cluster -> Project` moet veilig worden ontwarren van de permanente gebouwstructuur.
2. `field_brebo_location` op Project moet als legacy-/weergaveveld worden beoordeeld tegenover het permanente Gebouw.
3. Relaties moeten technisch worden beschermd tegen koppelingen naar objecten uit een ander gebouw.
4. Bestaande gegevens moeten zonder verlies of dubbel model worden gemigreerd.

**Eerstvolgende technische stap:** deze vier punten gericht analyseren en via een veilige update/migratie canoniek maken.

## Fase 3 — Centrale dossierlaag

**Status: gedeeltelijk aanwezig; samenhang toetsen**

Bestaande bouwstenen omvatten onder andere:

- communicatie;
- controles en verificaties;
- afwijkingen;
- werkpakketten;
- vrijgavepoorten;
- projectroutes en procesvereisten;
- organisaties en contactpersonen;
- calculatie, werkbegroting, prijsaanvragen en leveranciersoffertes.

Voor verdere uitbreiding wordt eerst vastgesteld of onderstaande functies al canoniek aanwezig zijn of nog moeten worden aangevuld:

- acties en termijnen;
- signalen;
- risico's en kansen;
- besluiten;
- kwaliteitscontroles en bewijs;
- garanties en nazorg;
- feedback en tevredenheid;
- leer- en verbeterinformatie.

Doel is één samenhangende dossierlaag, niet meerdere concurrerende registers voor hetzelfde begrip.

## Fase 4 — Migrerende Mail Intake

**Status: eerstvolgende grote functionele workflow na consolidatie van fase 2 en 3**

De bestaande communicatiestructuur wordt gebruikt als basis voor gecontroleerde invoer vanuit e-mail en later andere kanalen.

Doelketen:

```text
bronbericht ontvangen
-> origineel en bijlagen als bron bewaren
-> afzender, datum en onderwerp bepalen
-> gebouw herkennen
-> project en projectscope herkennen indien van toepassing
-> communicatietype classificeren
-> feiten, acties, risico's en besluiten voorstellen
-> betrouwbaarheid/confidence vastleggen
-> naar juiste rol routeren
-> menselijke controle waar vereist
-> gecontroleerd opnemen in het dossier
```

Persoonlijke mailboxen, WhatsApp, telefoon en andere kanalen blijven aanvoerkanalen en worden geen tweede dossierwaarheid.

## Fase 5 — Actie-, signaal- en controlemotor

**Status: gepland**

Eén centrale motor voor onder andere:

- deadlines en verlopen termijnen;
- ontbrekende bewijsstukken;
- open afwijkingen en hercontroles;
- keurings- en hold points;
- risico's en escalaties;
- ontbrekende reacties of bevestigingen;
- fase- en vrijgavevoorwaarden.

De motor werkt risicogestuurd en houdt bron, eigenaar, termijn, status en afsluitbewijs herleidbaar.

## Fase 6 — Digitale rollen

**Status: functioneel voorbereid; verdere operationalisering volgt op betrouwbare dossierdata**

De digitale rollen gebruiken dezelfde centrale informatiebron. Minimaal worden de reeds vastgestelde rollen ondersteund, waaronder Projectleider, Werkvoorbereider, Uitvoerder, Calculator/Inkoper en KAM-/Kwaliteitsmanager.

Voor digitale handelingen gelden drie niveaus:

1. **Signaleren** — autonoom mogelijk binnen regels en bewijsstatus.
2. **Voorstellen / voorbereiden** — autonoom conceptueel mogelijk.
3. **Beslissen / extern handelen** — uitsluitend binnen aantoonbaar mandaat en waar vereist met menselijke goedkeuring.

## Fase 7 — Feedback, tevredenheid en organisatorisch leren

**Status: functionele richting vastgesteld; implementatie nog te realiseren/toetsen**

BREBO Office moet niet alleen registreren wat is gedaan, maar ook meten hoe uitvoering en communicatie worden ervaren.

De leerkring wordt:

```text
gebeurtenis / communicatie
-> reactie en ervaring
-> feedback / tevredenheid
-> oorzaak
-> verbeteractie
-> aangepaste werkwijze
-> effectmeting
```

Feedback wordt gekoppeld aan gebouw, project, relevante gebeurtenis en opvolging en voedt structurele procesverbetering.

## Fase 8 — Dashboards en managementsturing

**Status: later**

Dashboards worden pas leidend wanneer de onderliggende dossierdata betrouwbaar en voldoende compleet zijn.

Beoogde inzichten omvatten onder andere:

- gebouwen en onderhoudshistorie;
- projectvoortgang;
- planning en capaciteit;
- financiën en prognose;
- kwaliteit en veiligheid;
- open acties, risico's en besluiten;
- klachten, garanties en feedback;
- faalkosten en organisatiebrede leerpatronen.

## Actuele positie

BREBO Office bevindt zich nu **tussen fase 2 en fase 3**.

Het permanente gebouwmodel en de tijdelijke projectscope bestaan reeds. De eerstvolgende ontwikkelopgave is daarom niet het bouwen van een nieuw Projectdossier, maar het canoniek maken van de bestaande objectrelaties en het vaststellen welke centrale dossierfuncties al bestaan, waar doublures zitten en welke echte hiaten nog moeten worden ingevuld.

Pas daarna wordt de Migrerende Mail Intake als eerste grote end-to-end bedrijfsworkflow verder gerealiseerd.

## Continuïteitsregel

Belangrijke besluiten, actuele technische stand, open punten en eerstvolgende acties mogen niet uitsluitend in een chat bestaan. Zij worden duurzaam vastgelegd in de repositorydocumentatie en, wanneer functioneel relevant, in Appendix A of het wijzigingsregister.

Bij overgang naar een nieuwe chat of ontwikkelsessie wordt eerst de actuele continuïteitsdocumentatie, Appendix A, CIM, roadmap en GitHub-stand gelezen voordat nieuwe architectuur of implementatie wordt voorgesteld.
