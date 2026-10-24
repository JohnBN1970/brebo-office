# OnSite voor Android

Eerste native Android-testversie op hetzelfde Office API-contract als de bestaande iOS-app.
Android 8.0 (API 26) of nieuwer, met Google Play-services. Doelversie: Android 16 (API 36).

## Gebruik

1. Installeer de debug-APK op een testtoestel. Deze heet OnSite en heeft pakketnaam `nl.brebobv.onsite.test`.
2. Gebruik een persoonlijke, nog geldige Office-activatielink: `brebo-onsite://activate?token=…`.
   Tik op de link of plak deze in de app. Bevestig met **Toestel koppelen**.
3. Kies **Automatische aanwezigheid instellen**, lees de uitleg, geef nauwkeurige locatie en daarna achtergrondlocatie (**Altijd toestaan**).
4. Controleer dat de toegewezen projectzones verschijnen en de app bewaking actief meldt.
5. Verlaat een zone en kom terug. Controleer de in-/uitmeldingen in Office.
6. Herhaal zonder internet, herstel internet en controleer dat de wachtrij leegloopt zonder dubbele Office-events.
7. Controleer na herstart, ingetrokken locatierechten, pauzeren en intrekking van het toestel in Office.

**Deze APK gebruikt de bestaande Office-host https://sboffice.brebobv.nl.**
De app schrijft aanwezigheid zodra een gekoppeld testtoestel een zoneovergang waarneemt.
De Android-implementatie wijzigt geen serverroutes, iOS-code of productie-instellingen.

## Werking en grenzen

- Activatie: POST `/api/onsite/v1/activate` met `activation_token`.
- Bootstrap: GET `/api/onsite/v1/bootstrap` met Bearer-apparaattoken.
- Aanwezigheid: POST `/api/onsite/v1/presence` met `event_id` en `event` met project_id, zone_id, kind (in/out), occurred_at.
- Het apparaattoken staat versleuteld met een sleutel in Android Keystore. App-back-up staat uit.
- Alleen zoneovergangen worden verstuurd, zonder medewerker-ID, GPS-punten of routehistorie.
- SQLite bewaart events en UUID's duurzaam; retries behouden dezelfde UUID.
- Alleen een expliciet `ok: true` verwijdert een event. Afgewezen events blijven zichtbaar als aantal; 401 stopt bewaking en vraagt nieuwe activatie. Tijdelijke fouten worden opnieuw geprobeerd.
- Ontkoppelen vraagt bevestiging voordat lokale niet-verstuurde meldingen worden verwijderd.
- Een volgende activatie kan nooit stilzwijgend events van een vorige koppeling naar een andere medewerker sturen.
- Projectzones worden volledig gevalideerd, maximaal 100 per toestel; een overschrijding wordt niet stilzwijgend afgekapt.
- Geofences verlopen na 24 uur. Office wordt bij openen en periodiek (circa 6 uur, door Android ingepland) ververst. Zonder verse Office-toewijzing stopt het verwerken van nieuwe overgangen.
- Bewaking wordt opnieuw aangevraagd na herstart of app-update. Zonder netwerk wordt gewacht op een verse toewijzing.
- De eerste vaststelling dat iemand al in een zone staat maakt geen verzonnen aankomsttijd; pas echte overgangen worden gemeld.
- Android kan geofence-meldingen vertragen. Force-stop, energiebesparing, uitgeschakelde locatie, ontbrekende Play-services of ingetrokken rechten kunnen bewaking onderbreken.
- De interface is in deze eerste testversie Nederlands. Office-taal wordt niet als volledige vertaling gepresenteerd.

## Bouwen en controleren

Benodigd: JDK 17, Android SDK platform 36 en Build Tools 35.0.0.
Stel `ANDROID_HOME` in of gebruik een lokale, niet ingecheckte `local.properties`.
Open deze map in Android Studio, of voer uit:

```sh
./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

Uitvoer: `app/build/outputs/apk/debug/app-debug.apk`.
Gradle 8.13 en Android Gradle Plugin 8.11.1 zijn vastgezet. De workflow
`onsite-android.yml` bouwt en controleert Android apart van de server.

Een distributieversie vereist een vaste releasehandtekening en een gekozen verspreidingskanaal.
De debughandtekening is uitsluitend voor deze testversie. Nog geen Play Store-publicatie
of verificatie van Android App Links; de bestaande custom activatielink wordt hergebruikt.

