# BREBO Office — Mail Intake Privacy & AVG-kader

Status: vastgesteld ontwikkelprincipe
Datum: 26 augustus 2026

## Doel

BREBO Office verwerkt e-mail uitsluitend binnen een aantoonbaar, doelgebonden en proportioneel kader. De inrichting volgt de AVG-beginselen van rechtmatigheid, transparantie, doelbinding, minimale gegevensverwerking, opslagbeperking, integriteit/vertrouwelijkheid en verantwoordingsplicht.

Dit document is een technische en organisatorische ontwerpregel. Voor formele juridische vaststelling van grondslagen, bewaartermijnen, werknemersbeleid en eventuele medezeggenschapsvereisten blijft afzonderlijke organisatorische beoordeling nodig.

## Mailboxcategorieën

### 1. Gedeelde functionele bedrijfsbox

Voorbeelden: `info@brebobv.nl`, `facturen@brebobv.nl`, `offerte@brebobv.nl` en `admin@brebobv.nl`.

Een gedeelde functionele mailbox mag automatisch via de aangewezen mailbron worden gesynchroniseerd wanneer:

- het zakelijke doel vooraf is vastgelegd;
- de mailbox expliciet als bedrijfsbron is aangewezen;
- alleen gegevens worden verwerkt die voor dat doel nodig zijn;
- toegang via rollen en permissies is geregeld;
- logging, bewaartermijnen en verwijder-/beperkingsprocessen zijn vastgesteld;
- bronmail read-only wordt benaderd waar de intake dat toelaat.

Functionele mailboxen zijn niet aan één natuurlijke persoon voor zichtbaarheid gekoppeld. Toegang volgt de toegekende Drupal-rollen en mailboxpermissies. Eén gebruiker kan meerdere rollen hebben en daardoor toegang hebben tot meerdere functionele mailboxen.

### 2. Persoonlijke zakelijke mailbox

Het zakelijke e-mailadres dat aan een BREBO Office-gebruiker is gekoppeld, geldt als diens persoonlijke zakelijke mailboxidentiteit.

Een persoonlijke zakelijke mailbox mag automatisch via de aangewezen mailbron worden gesynchroniseerd, maar de veilige standaard is **owner-only**:

- de mailbox is gekoppeld aan de betreffende BREBO Office-gebruiker/eigenaar;
- gesynchroniseerde persoonlijke mailboxdata is standaard uitsluitend zichtbaar voor die eigenaar;
- een algemene functie- of mailboxrol geeft nooit automatisch toegang tot de persoonlijke mailbox van een andere gebruiker;
- zoekresultaten, AI, projecttijdlijnen, documenten, bijlagen en andere afgeleide weergaven mogen deze toegangsregel niet omzeilen;
- bronmail wordt niet door de intake verwijderd, verplaatst of als gelezen gemarkeerd zolang daarvoor geen afzonderlijk vastgesteld proces bestaat;
- toegang en relevante beheerhandelingen zijn auditable.

Automatische synchronisatie betekent niet dat persoonlijke communicatie automatisch gedeelde bedrijfscommunicatie wordt. Persoonlijke mailboxinhoud blijft binnen de persoonlijke toegangscontext totdat de eigenaar bewust een zakelijke mail deelt/promoveert naar een functionele mailbox of een andere toegestane gedeelde context.

## Delen/promoveren vanuit persoonlijke mailbox

De eigenaar van een persoonlijke mailbox kan een werkgerelateerd bericht bewust beschikbaar maken binnen een functionele BREBO-mailbox waarvoor die handeling is toegestaan.

De gebruikersactie krijgt bijvoorbeeld de betekenis `Delen met BREBO` of `Naar functionele mailbox`.

De actie:

- laat de gebruiker bewust een toegestane functionele doelmailbox kiezen;
- maakt de mail vanaf dat moment zichtbaar volgens de rollen en permissies van die functionele mailbox;
- registreert wie de handeling uitvoerde, wanneer en naar welke mailbox/context;
- veroorzaakt niet automatisch een externe verzending;
- dupliceert de canonieke Communication niet: waar technisch passend wordt een extra mailboxprojectie/toegangscontext toegevoegd;
- kan dossiercontext voorstellen, maar maakt geen automatische formele dossierkoppeling zonder voldoende grondslag/zekerheid.

Het delen/promoveren van een persoonlijk bericht is daarmee een expliciete overgang van owner-only zichtbaarheid naar een gedeelde zakelijke context.

## Rollen en mailboxrechten

BREBO Office ondersteunt meerdere Drupal-rollen per gebruiker.

Mailboxtoegang volgt waar mogelijk rollen en fijnmazige mailboxpermissies. Denk aan:

- mailbox bekijken;
- behandelen;
- verzenden als;
- verzenden namens;
- archiveren;
- verwijderen;
- permanent verwijderen;
- mailbox beheren.

Persoonlijke mailboxeigendom blijft daarvan gescheiden: de eigenaar heeft toegang tot zijn eigen persoonlijke mailbox; andere rollen geven niet impliciet toegang tot persoonlijke mailboxen van collega's.

## Dataminimalisatie

Niet ieder bericht hoeft permanent dossierdata te worden. De verwerkingslaag moet kunnen onderscheiden tussen relevante zakelijke/dossierinformatie, administratieve informatie, persoonlijke of evident niet-relevante communicatie en bijzondere of gevoelige informatie die extra bescherming of beperkte toegang vereist.

Waar volledige inhoud niet noodzakelijk is voor het vastgestelde doel, moet BREBO Office technisch ruimte bieden voor beperktere opslag, afscherming, uitsluiting of latere verwijdering.

## Toegang en vertrouwelijkheid

- Toegang volgt het need-to-know-principe.
- Persoonlijke mailboxdata is standaard owner-only.
- Functionele mailboxdata wordt zichtbaar op basis van rollen en mailboxpermissies.
- Rollen en permissies moeten mailboxbron, communicatieobject en eventuele gevoelige classificaties respecteren.
- Secrets en mailboxcredentials blijven buiten Git en exporteerbare Drupal-configuratie.
- Toegang en relevante beheerhandelingen worden auditable gemaakt.

## Transparantie en betrokkenenrechten

De organisatie moet kunnen uitleggen welke mailboxen worden verwerkt, voor welk doel, welke gegevens worden opgeslagen, wie toegang heeft, hoe lang gegevens worden bewaard en hoe rechten van betrokkenen kunnen worden uitgeoefend, waaronder inzage, rectificatie, beperking en verwijdering waar van toepassing.

De software moet deze processen niet blokkeren en waar mogelijk ondersteunen.

## Bewaartermijnen

BREBO Office hanteert geen onbeperkte opslag als standaard. Bewaartermijnen moeten per gegevenscategorie/doel worden vastgesteld. Dossier-, contract-, fiscale, garantie- of wettelijke verplichtingen kunnen verschillende termijnen vereisen.

Technisch moet onderscheid mogelijk blijven tussen bronmail, afgeleide dossierfeiten, tijdelijke AI-/classificatiegegevens en auditgegevens.

## DPIA / werknemersmonitoring

Wanneer een verwerking waarschijnlijk een hoog privacyrisico oplevert — bijvoorbeeld structurele monitoring van werknemers of grootschalige verwerking waarbij persoonlijke mailboxinhoud buiten de eigenaar beschikbaar wordt gemaakt — wordt vóór ingebruikname beoordeeld of een DPIA vereist is.

Automatische synchronisatie van een persoonlijke zakelijke mailbox mag niet worden gebruikt om die mailbox stilzwijgend voor leidinggevenden, collega's of algemene rollen toegankelijk te maken.

## Privacy by design/default

Voor persoonlijke zakelijke mailboxen is de veilige standaard:

- automatische synchronisatie toegestaan binnen het vastgestelde zakelijke mailproces;
- mailboxidentiteit gekoppeld aan de eigenaar/gebruiker;
- owner-only zichtbaarheid;
- read-only bron zolang geen ander proces is vastgesteld;
- minimale toegang;
- logging;
- expliciete gebruikersactie voor overgang naar gedeelde/functionele zichtbaarheid;
- geen automatische formele dossierkoppeling zonder voldoende grondslag/zekerheid;
- geen externe actie zonder afzonderlijk mandaat.

Voor functionele mailboxen is de veilige standaard:

- automatische synchronisatie mogelijk na expliciete aanwijzing;
- toegang via rollen en mailboxpermissies;
- meerdere rollen per gebruiker toegestaan;
- geen impliciete toegang tot persoonlijke mailboxen.

## Vaste ontwerpregel

**Gedeelde functionele bedrijfsbox = automatische synchronisatie mogelijk; zichtbaarheid en handelingen zijn rol- en permissiegestuurd.**

**Persoonlijke zakelijke mailbox = automatische synchronisatie mogelijk, maar standaard uitsluitend zichtbaar voor de gekoppelde eigenaar.**

**Werkgerelateerde mail uit een persoonlijke mailbox wordt pas gedeeld wanneer de eigenaar deze bewust promoveert/deelt naar een functionele mailbox of andere toegestane gedeelde context.**
