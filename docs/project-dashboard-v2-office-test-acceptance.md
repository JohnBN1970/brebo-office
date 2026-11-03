# Office-test projectdashboard V2 — veilige acceptatie

De GitHub-checks zijn groen, maar dat bewijst niet dat de actieve Hostinger-release dezelfde POST-acties bevat. De huidige release is `release-office-test-contractform-20261030/private`. **Kopieer de GitHub-versie van `office-project.php` niet blind over deze release.**

## Preflight (alleen lezen)

Voer op de Hostinger-server uit:

```sh
ROOT=/home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-contractform-20261030/private
FILE="$ROOT/public/office-project.php"
test -f "$FILE" || exit 1
php -l "$FILE" || exit 1
grep -nE "create_project_term|create_project_change|office_project_csrf|OfficeProjectTermService|OfficeProjectChangeOrderService" "$FILE" || true
sha256sum "$FILE"
```

Vergelijk de uitvoer met de branch `feat/project-dashboard-visual-20261103`. In de huidige GitHub-bron zijn de nieuwere contract-POST-acties niet aangetoond. Als die op Hostinger wél voorkomen, moet de dashboardpresentatie op de serverversie worden geport, **niet** het hele GitHub-bestand vervangen.

## Acceptatievolgorde

1. Backup van de huidige serverfile, met SHA256 en datum.
2. Vergelijk alle `POST`-acties, routes, autorisatie, CSRF en contractformulieren met de branch.
3. Port uitsluitend dashboard-CSS, demomodus en read-only weergave naar de actieve serverfile; behoud de huidige POST-handler.
4. `php -l` op de aangepaste serverfile.
5. Test projectoverzicht, vijf tabs, gebouwformulieren, termijnen, meer-/minderwerk en rechten van projectleider versus Finance.
6. Test `?tab=overzicht&demo=1` in Office-test; controleer zichtbare DEMO-labels, geen opslag, en terugkeer naar echte waarden.
7. Verifieer dat productie nooit demogegevens toont.
8. Rollback: herstel uitsluitend de geback-upte serverfile en controleer hash; database blijft ongewijzigd.

## Blokkerend

Geen SSH/server-tool in deze chatsessie. Daarom zijn Hostinger-vergelijking, plaatsing en browseracceptatie **nog niet uitgevoerd**. GitHub-groen is geen serveracceptatie.
