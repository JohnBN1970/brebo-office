# BREBO Help

BREBO Help is de centrale interne help- en kennislaag van BREBO Office.

## Toegang

Help is bereikbaar vanuit de vaste BREBO Office-sidebar, direct boven Instellingen. De Help-groep bevat:

- Helpcentrum
- Dagelijkse taken
- Begrippen & beslisregels

De eerdere zwevende contextuele `?`-knop is bewust verwijderd om dubbele navigatie te voorkomen.

## Inhoud

De module bevat taakgerichte handleidingen, rolgerichte werkwijzen, processen en begrippen. Office blijft de bron van waarheid; Help introduceert geen tweede bedrijfsregistratie.

## Architectuur

De huidige implementatie is schema-loos. Inhoud is onderdeel van de module en kan later worden gekoppeld aan beheerbare kenniscontent zonder bestaande bedrijfsobjecten te dupliceren.

## Rechten

Toegang wordt beschermd met de Drupal-permissie `access brebo help`.

## Routes

- `/brebo/help`
- `/brebo/help/taken`
- `/brebo/help/begrippen`

## Ontwerpregel

Help beschrijft niet alleen welke knop moet worden gebruikt, maar ook waarom BREBO een proces op die manier uitvoert. Daarmee is de module zowel gebruikershulp als interne werkinstructie.
