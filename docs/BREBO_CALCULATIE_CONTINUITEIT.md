# BREBO Office Calculatie – Continuiteit

Laatst bijgewerkt: 2026-08-18

## Vastgestelde richting

De calculatiemodule wordt doorontwikkeld als BREBO Office-werkbank, niet als klassiek Drupal-formulier.

Vaste gebruikersflow:

`Nieuwe calculatie` → compacte popup → naam → direct werkbank.

BREBO Office vult systeemvelden automatisch, waaronder calculatiecode, eerste versie, conceptstatus, prijspeildatum en auteur. Werkpakket is geen verplichte tussenlaag in de primaire calculatieflow.

## Calculatiestructuur

Vast model:

Calculatie → hoofdgroep → paragraaf → gemengde blokken → recept of losse regel → onderliggende calculatieregels → materiaal-/prijsbron → kostprijs.

Losse calculatieregels en recepten moeten in dezelfde paragraaf volledig door elkaar kunnen worden gebruikt en later ook in één sorteerstroom kunnen worden geplaatst.

## Recepten

Recepten zijn een kernonderdeel van BREBO Office Calculatie.

Een recept:

- bestaat uit meerdere regels;
- kan materiaal, arbeid, materieel, onderaanneming en overige regels bevatten;
- heeft een recepthoeveelheid;
- kan meerdere invoer- en afgeleide parameters bevatten;
- berekent onderliggende hoeveelheden parametrisch;
- kan binnen een calculatie worden aangepast;
- kan binnen de calculatie extra eigen regels krijgen;
- wordt bij plaatsing als versievaste snapshot opgeslagen;
- mag door wijzigingen in de centrale receptbibliotheek nooit stilzwijgend bestaande calculaties wijzigen.

Voorbeeld: `Kozijn vervangen` kan regels bevatten voor kozijn, stelkozijn, flexpur, bevestigingsmateriaal, klein materiaal, arbeid montage, kitwerk en materieel.

## Formulemotor

De formulemotor ondersteunt gecontroleerde rekenexpressies met variabelen, basisoperatoren, haakjes en toegestane functies. Er wordt bewust geen `eval()` gebruikt.

De recepthoeveelheid en parameters voeden de formules van onderliggende regels. Custom regels blijven bij herberekenen intact.

## Materialen en prijslijsten

De centrale artikelstam uit `brebo_article` is de enige materiaalbron voor calculaties en recepten. Er wordt geen tweede artikelwereld naast gebouwd.

De bestaande artikelstam bevat onder andere:

- BREBO-artikel;
- leverancier;
- leveranciersartikel;
- catalogusimport;
- prijsversies/staffels;
- prijsdatum.

Een materiaalregel in een recept kan via de centrale artikelpicker een concreet artikel en leverancier kiezen. De gekozen artikel-/prijscombinatie wordt gevalideerd en als referentie op de receptregel vastgelegd. De prijsdatum is de datum van de daadwerkelijk gekozen prijs/catalogusimport en niet automatisch de aanmaakdatum van de calculatie.

Dezelfde picker wordt gebruikt voor bestaande materiaalregels en nieuw toegevoegde custom materiaalregels.

## Huidige implementatie

Op `develop` aanwezig:

- receptdatamodel met bibliotheek, versies, parameters, regels, instanties en snapshots;
- `RecipeFormulaEvaluator`;
- `RecipeManager`;
- formule-smoketest;
- recept plaatsen vanuit de calculatiewerkbank;
- receptinstanties en onderliggende regels zichtbaar in de werkbank;
- recepteditor;
- recepthoeveelheid wijzigen en parametrisch herberekenen;
- custom regels aan een geplaatst recept toevoegen;
- `RecipeMaterialSelector`;
- centrale artikelpicker gekoppeld aan receptmateriaalregels;
- automatische opslag na artikelkeuze;
- artikelpicker ook beschikbaar bij het toevoegen van custom materiaalregels.

Belangrijke recente commits:

- `93cb37e4` documentatie receptarchitectuur;
- `cf92824b` receptdatamodel;
- `1ef3a322` veilige formulemotor;
- `0776bd6b` RecipeManager;
- `dab65036` receptinstanties in werkbank;
- `e999ea90` recepthoeveelheid en herberekening;
- `27310b61` recepteditor;
- `38c32757` materiaal-selector;
- `9e361481` artikelpicker in recepteditor;
- `90f4fb01` automatische opslag artikelkeuze;
- `f37015b2` artikelpicker voor custom materiaalregels.

## Eerstvolgende ontwikkellijn

1. Invoerparameters van geplaatste recepten bewerkbaar maken en daarna automatisch herberekenen.
2. Custom en gegenereerde receptregels verder inline bewerkbaar maken waar functioneel nodig.
3. Losse regels en recepten samenbrengen in één echte sorteerstroom per paragraaf.
4. Receptbibliotheek-UX verder uitbouwen.
5. Nieuwe-calculatie-popup als primaire aanmaakroute afmaken.
6. AI-laag toevoegen voor calculatienaam, receptvoorstellen, parameterassistentie en materiaalalternatieven.

## Harde regels

- Geen parallel calculatiemodel introduceren.
- Bestaande calculatie-, locking-, audit- en financiële invarianten blijven leidend.
- Een gelockte/niet-draft calculatieversie mag niet worden gewijzigd.
- Receptsnapshots blijven reproduceerbaar.
- Materiaalprijzen blijven herleidbaar naar leverancier, prijsversie/catalogusimport en prijsdatum.
- Kwaliteit en controleerbaarheid gaan voor snelheid.