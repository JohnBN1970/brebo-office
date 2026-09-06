# BREBO Data Intake

De centrale intake is de bron-neutrale voordeur van BREBO Office. Mail, upload en toekomstige adapters leveren hier brontraceerbare records aan; vakmodules schrijven niet rechtstreeks vanuit een bronadapter.

## Intakewerkbank

`/brebo-office/intake` toont uitsluitend records met status `review_required`. De werkbank laat de operator in mensentaal zien waar het item vandaan komt, wat er binnenkwam, wat Office denkt dat het is en welke koppeling nog ontbreekt.

Vanuit `Beoordelen` kan een bevoegde operator de classificatie en canonieke koppelingen corrigeren, het item afwijzen of het item accepteren. Correcties blijven in `review_required` totdat een expliciet menselijk acceptatiebesluit is genomen. Accepteren routeert uitsluitend via een geregistreerd `IntakeDestinationInterface`; de review-UI schrijft dus niet rechtstreeks naar Finance, Projecten of andere vakmodules.

Alle menselijke beslissingen worden onveranderlijk vastgelegd in `brebo_data_intake_decision`, inclusief actor, tijdstip, oude/nieuwe status, classificatie, canonieke koppeling en optionele toelichting. Per record voorkomt een lock dubbele gelijktijdige uitvoering; daarnaast wordt een revision-hash gecontroleerd zodat een verouderd formulier geen nieuwere menselijke correctie kan overschrijven.

Het originele bronbestand of bronbericht blijft canoniek; de intake slaat alleen provenance, normalisatie en beslisinformatie op.
