# BREBO Data Intake

De centrale intake is de bron-neutrale voordeur van BREBO Office. Mail, upload en toekomstige adapters leveren hier brontraceerbare records aan; vakmodules schrijven niet rechtstreeks vanuit een bronadapter.

## Intakewerkbank

`/brebo-office/intake` toont uitsluitend records met status `review_required`. De werkbank laat de operator in mensentaal zien waar het item vandaan komt, wat er binnenkwam, wat Office denkt dat het is en welke koppeling nog ontbreekt.

Deze eerste slice is bewust alleen het leesmodel. Accepteren, corrigeren en afwijzen worden als aparte vervolgactie toegevoegd, zodat die beslissingen via de centrale intake/destination-contracten lopen en niet als directe database- of vakmodulewrite vanuit de UI ontstaan.

Het originele bronbestand of bronbericht blijft canoniek; de intake slaat alleen provenance, normalisatie en beslisinformatie op.
