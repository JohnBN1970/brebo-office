# BREBO Office - vaste ontwikkel- en testafspraak

Status: vastgesteld op 15 november 2026. Geldt voor alle volgende Office-ontwikkelwerkzaamheden.

## 1. Omgevingen
- **Office-test** is de ontwikkel-, integratie- en acceptatieomgeving. Hier mogen zonder herhaalde toestemming fictieve projecten, medewerkers, relaties, calculaties, startdossiers, documenten en andere noodzakelijke testrecords worden aangemaakt.
- **Productie** ontvangt alleen geteste code, noodzakelijke database-schema's, migraties, rollen en configuratie. Fictieve testrecords worden niet meegeleverd. Een lege bedrijfsdataset bij eerste uitrol is toegestaan.
- Productiegegevens en externe diensten blijven beschermd. Geen echte betalingen, berichten, factuurverzending of destructieve acties door een testseed. Gebruik duidelijk herkenbare fictieve gegevens.
- Testdata nooit impliciet kopieren naar productie. Scheid testseed/migratie expliciet en technisch.

## 2. Uitvoering in een gebundelde run
1. Bestaande architectuur, status en afhankelijkheden inspecteren; dubbel werk voorkomen.
2. Alle samenhangende wijzigingen bouwen en integreren in de bestaande Office-modules.
3. Noodzakelijk test-schema installeren en fictieve testdata aanmaken op Office-test, met idempotente en begrensde scripts.
4. Automatische tests uitvoeren en daarna de volledige gebruikersketen via de echte Office-test-routes en rollen beproeven.
5. Fouten herstellen en dezelfde keten opnieuw testen; niet bij iedere kleine commit stoppen.
6. Pas daarna compact rapporteren: **werkt / getest / blokkeert**, met concrete run-, PR- en schermlinks.

## 3. Acceptatie
- Groene CI of geslaagde deployment is **geen** bewijs dat de gebruikersfunctie werkt.
- Een onderdeel is pas 'gereed' na aantoonbare end-to-end acceptatie op Office-test.
- Ontbrekende testrecords zijn geen blokkade: maak ze aan. Ontbrekende echte integraties, rechten of secrets mogen niet worden gefingeerd.
- Meld eerlijk wat niet is uitgevoerd; claim geen installatie, live test of werkende functie zonder bewijs.
- Geen onnodige nieuwe workflows, losse tussenrapporten of herhaalde toestemming voor gewone Office-testdata.

## 4. Projectvoorbereiding - verplichte acceptatieketen
Projectleider expliciet toewijzen -> werkvoorbereider en team aanwijzen -> startdossier aanmaken -> bouwplaatsdocumenten, werkinstructies, vragen, besluiten en deelleveringen vastleggen -> kickstart -> eerste en onafhankelijke tweede goedkeuring -> uitvoeringsstatus vrijgeven.

Verifieer ook: afkeuren met reden; wijziging/revisie maakt oude goedkeuringen ongeldig; onbevoegden worden geweigerd; uitvoeringsstart wordt server-side geblokkeerd zonder geldige vrijgave. Alle gegevens zijn terug te vinden in de bestaande projectmodule en werkbegroting wordt niet dubbel bijgehouden.

## 5. Releasegrens
- Scheid schema-migraties van testseed. Seed alleen bij aantoonbare Office-testomgeving en expliciete testmodus.
- Behoud autorisatie, audittrail, CSRF, backups en terugdraaimogelijkheden waar relevant.
- Geen automatische productie-uitrol op basis van een groene test; productie vraagt afzonderlijke release-acceptatie.
