# BREBO Office — Mail Intake Privacy & AVG-kader

Status: vastgesteld ontwikkelprincipe
Datum: 12 augustus 2026

## Doel

BREBO Office verwerkt e-mail uitsluitend binnen een aantoonbaar, doelgebonden en proportioneel kader. De inrichting volgt de AVG-beginselen van rechtmatigheid, transparantie, doelbinding, minimale gegevensverwerking, opslagbeperking, integriteit/vertrouwelijkheid en verantwoordingsplicht.

Dit document is een technische en organisatorische ontwerpregel. Voor formele juridische vaststelling van grondslagen, bewaartermijnen, werknemersbeleid en eventuele medezeggenschapsvereisten blijft afzonderlijke organisatorische beoordeling nodig.

## Mailboxcategorieën

### 1. Gedeelde functionele bedrijfsbox

Voorbeeld: `info@brebobv.nl`.

Een gedeelde functionele mailbox mag automatisch worden ingelezen wanneer:

- het zakelijke doel vooraf is vastgelegd;
- de mailbox expliciet als bedrijfsbron is aangewezen;
- alleen gegevens worden verwerkt die voor dat doel nodig zijn;
- toegang, rollen en logging zijn geregeld;
- bewaartermijnen en verwijder-/beperkingsprocessen zijn vastgesteld;
- bronmail read-only wordt benaderd waar de intake dat toelaat.

### 2. Persoonlijke zakelijke mailbox

Een persoonlijke mailbox wordt standaard **niet automatisch gepolld**.

De standaardmodus is **push-gestuurd**:

- de gebruiker start zelf de intake met een expliciete actie in BREBO Office;
- de actie is zichtbaar en herleidbaar;
- alleen een afgebakende nieuwe set berichten wordt verwerkt;
- per gebruiker/mailbox wordt een eigen voortgangsstatus bijgehouden;
- bronmail wordt niet verwijderd, verplaatst of als gelezen gemarkeerd door de intake;
- BREBO Office leest niet stilzwijgend de volledige persoonlijke mailbox uit.

Een eventuele permanente automatische intake van een persoonlijke mailbox is een afzonderlijk besluit en vereist voorafgaande beoordeling van doel, noodzaak, proportionaliteit, rechtsgrond, transparantie, toegang, bewaartermijn en werknemersprivacy.

## Push-knop — functioneel minimum

De gebruikersactie voor persoonlijke inboxen krijgt minimaal de betekenis:

`Nieuwe zakelijke e-mail naar BREBO Office verwerken`

De actie:

- toont of vermeldt welke mailbox wordt gebruikt;
- verwerkt uitsluitend berichten sinds de laatst geregistreerde intakepositie, tenzij de gebruiker bewust een andere begrensde selectie kiest;
- gebruikt een ingestelde maximum batchgrootte;
- maakt geen automatische permanente polling actief;
- registreert wie de intake startte, wanneer en voor welke mailbox;
- geeft achteraf een technisch resultaat, bijvoorbeeld aantal verwerkt / duplicaat / controle vereist;
- mag geen externe verzending veroorzaken.

## Dataminimalisatie

Niet ieder bericht hoeft permanent dossierdata te worden. De verwerkingslaag moet kunnen onderscheiden tussen:

- relevante zakelijke/dossierinformatie;
- administratieve informatie;
- persoonlijke of evident niet-relevante communicatie;
- bijzondere of gevoelige informatie die extra bescherming of beperkte toegang vereist.

Waar volledige inhoud niet noodzakelijk is voor het vastgestelde doel, moet BREBO Office technisch ruimte bieden voor beperktere opslag, afscherming, uitsluiting of latere verwijdering.

## Toegang en vertrouwelijkheid

- Toegang volgt het need-to-know-principe.
- Persoonlijke mailboxdata wordt niet automatisch zichtbaar voor alle BREBO Office-gebruikers.
- Rollen en permissies moeten mailboxbron, communicatieobject en eventuele gevoelige classificaties respecteren.
- Secrets en mailboxcredentials blijven buiten Git en exporteerbare Drupal-configuratie.
- Toegang en relevante beheerhandelingen worden auditable gemaakt.

## Transparantie en betrokkenenrechten

De organisatie moet kunnen uitleggen:

- welke mailboxen worden verwerkt;
- voor welk doel;
- welke gegevens worden opgeslagen;
- wie toegang heeft;
- hoe lang gegevens worden bewaard;
- hoe rechten van betrokkenen kunnen worden uitgeoefend, waaronder inzage, rectificatie, beperking en verwijdering waar van toepassing.

De software moet deze processen niet blokkeren en waar mogelijk ondersteunen.

## Bewaartermijnen

BREBO Office hanteert geen onbeperkte opslag als standaard. Bewaartermijnen moeten per gegevenscategorie/doel worden vastgesteld. Dossier-, contract-, fiscale, garantie- of wettelijke verplichtingen kunnen verschillende termijnen vereisen.

Technisch moet onderscheid mogelijk blijven tussen:

- bronmail;
- afgeleide dossierfeiten;
- tijdelijke AI-/classificatiegegevens;
- auditgegevens.

## DPIA / werknemersmonitoring

Wanneer een verwerking waarschijnlijk een hoog privacyrisico oplevert — bijvoorbeeld structurele monitoring van werknemers of grootschalige verwerking van persoonlijke mailboxen — wordt vóór ingebruikname beoordeeld of een DPIA vereist is.

Een systeem dat e-mailverkeer van werknemers registreert of controleert kan tevens onder werknemersprivacy- en medezeggenschapsregels vallen. Daarom wordt automatische persoonlijke-mailboxmonitoring niet als standaardfunctie gebouwd.

## Privacy by design/default

Voor persoonlijke inboxen is de veilige standaard:

- polling uit;
- gebruiker initieert intake;
- beperkte batch;
- read-only bron;
- minimale toegang;
- logging;
- geen automatische formele dossierkoppeling zonder voldoende grondslag/zekerheid;
- geen externe actie zonder afzonderlijk mandaat.

## Vaste ontwerpregel

**Gedeelde functionele bedrijfsbox = automatische intake mogelijk na expliciete aanwijzing en beleid.**

**Persoonlijke zakelijke mailbox = standaard uitsluitend push-gestuurde intake door de mailboxgebruiker; geen stille achtergrondpolling.**
