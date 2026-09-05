# BREBO Office — publiek projectpublicatiecontract

## Status

Eerste begrensde fundering voor publicatie van projectinformatie vanuit BREBO Office naar de publieke BREBO-website.

Dit contract introduceert **geen tweede projectobject** en **geen parallelle projectadministratie**. `brebo_project` blijft het canonieke tijdelijke projectobject binnen Office; het permanente gebouwmodel blijft leidend voor gebouwidentiteit en -historie.

## Keten

`BREBO Office -> expliciete publieke vrijgave -> Integration API -> publieke BREBO-website`

De website is uitsluitend publicatiekanaal/consumer. Office blijft bron van waarheid.

## Publicatieregel

Een project mag uitsluitend via de publieke integratie worden aangeboden wanneer het in Office expliciet voor externe publicatie is vrijgegeven.

Default is: **niet publiceren**.

Een algemene Drupal-publicatiestatus, projectstatus of aanwezigheid van projectdata geldt nooit als toestemming voor externe publicatie.

## Publieke projectprojectie

De publieke projectie bevat uitsluitend expliciet vrijgegeven presentatiegegevens:

- stabiele publieke projectidentificatie;
- publieke titel;
- publieke introductie;
- gebouwvraag: wat speelde er aan het gebouw?;
- gekozen aanpak: waarom deze keuze?;
- aantoonbaar gerealiseerde resultaten;
- toepasselijke BREBO Lens-rollen: Inzicht, Regie en/of Realisatie;
- projectstatus voor zover geschikt voor publieke presentatie;
- uitsluitend expliciet goedgekeurde publieke media;
- publieke detail-URL/slug indien van toepassing.

## Niet publiek

De projectie bevat nooit automatisch:

- calculaties, begrotingen, marges of interne prijsinformatie;
- inkoop- of leveranciersinformatie;
- interne communicatie, e-mail of dossiernotities;
- persoonsgegevens of contactgegevens;
- interne risico-, afwijkings-, kwaliteits- of mandaatinformatie;
- niet-vrijgegeven documenten of bewijsstukken;
- interne gebouw- of projectmetadata die niet voor presentatie nodig is.

## Resultaatregel

Een publiek resultaat wordt alleen opgenomen wanneer het resultaat aantoonbaar gerealiseerd is. Begrote, geoffreerde, optionele, voorlopige of niet-bewezen werkzaamheden worden niet als gerealiseerd resultaat gepubliceerd.

## Mediaregel

Media worden afzonderlijk vrijgegeven. Projectpublicatie impliceert niet dat alle aan project of gebouw gekoppelde bestanden/foto's publiek zijn.

## API-grens

De Integration API publiceert uitsluitend een vooraf opgebouwde publieke projectie. De API krijgt geen generieke mogelijkheid om willekeurige Office-projectvelden naar buiten door te geven.

Beoogde read-only contractvorm:

- `GET /v1/public/projects`
- `GET /v1/public/projects/{public_id}`

Deze endpoints leveren uitsluitend expliciet vrijgegeven projecties. Mutaties blijven in Office.

## Intrekking en cachecontract

Externe vrijgave is herroepbaar. Intrekking van een projectpublicatie of afzonderlijke media-goedkeuring moet ook reeds gecachete publieke kopieën laten verdwijnen.

Daarom geldt voor de websiteconsumer:

- elke publieke projectie bevat een monotone `publication_version` en `updated_at`;
- lijst- en detailresponses krijgen een begrensde cachelevensduur van maximaal 5 minuten;
- de website mag een project of media-item na het verstrijken van die termijn niet uit een oude cache blijven tonen zonder de actuele publicatiestatus opnieuw te hebben gecontroleerd;
- een ingetrokken project verdwijnt uit `GET /v1/public/projects` en `GET /v1/public/projects/{public_id}` retourneert daarna `404`;
- wanneer alleen een media-item wordt ingetrokken, stijgt `publication_version` en verdwijnt dat item uit de volgende projectie;
- `brebo-platform` moet bij een gewijzigde of ontbrekende projectie zijn lokale render-/data-cache invalideren;
- tijdelijke onbereikbaarheid van Office/API is geen toestemming om een verlopen, mogelijk ingetrokken publicatie onbeperkt te blijven tonen.

Een toekomstige push/purge-hook mag intrekking versnellen, maar is geen vervanging voor deze maximale cachelevensduur en hercontrole. Daarmee blijft intrekking ook veilig wanneer een webhook faalt.

## Website

De publieke website mag de projectie binnen het cachecontract cachen en presenteren, maar wordt geen canonieke eigenaar van projectinhoud. Website-specifieke layout, masonry-volgorde en responsive presentatie blijven presentatielogica en worden niet teruggeschreven als projectwaarheid.

## Eerste praktijkproject

Bilderdijkstraat / Da Costakade kan na invoering van de vrijgavevelden als eerste echte project door deze keten worden gepubliceerd. Alleen werkelijk uitgevoerde en aantoonbare resultaten worden daarbij vrijgegeven.

## Vervolgimplementatie

1. Controleer bestaande `brebo_project`-velden en hergebruik waar semantisch correct.
2. Voeg alleen aantoonbaar ontbrekende publicatievelden toe aan het bestaande projectobject of een begrensde publicatieprojectie; geen nieuw projectcontenttype.
3. Voeg expliciete externe vrijgave toe met default `false`.
4. Bouw de read-only publieke projectie in Office, inclusief `publication_version` en intrekkingsgedrag.
5. Expose uitsluitend die projectie via de bestaande Integration API met het cachecontract hierboven.
6. Laat `brebo-platform` deze projectie consumeren, cache-intrekking respecteren en verwijder daarna website-eigenaarschap van canonieke projectinhoud.
7. Voeg Bilderdijkstraat / Da Costakade via Office toe en publiceer pas na inhoudelijke vrijgave.
