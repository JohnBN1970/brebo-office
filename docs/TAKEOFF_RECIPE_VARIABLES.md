# Take-off recipe variables

Recipes may be placed against a BREBO calculation take-off row.

Available formula variables:

- `top_m`
- `bottom_m`
- `left_m`
- `right_m`
- `perimeter_m`
- `area_m2`
- `width_mm`
- `height_mm`
- `quantity`
- `passes`

Examples:

- kit rondom, één gang: `perimeter_m`
- kit binnen + buiten: `perimeter_m * 2` or `perimeter_m * passes`
- vensterbank: `bottom_m`
- kantelaaf drie zijden: `left_m + top_m + right_m`
- compriband drie zijden: `(left_m + top_m + right_m) * passes`
- plaatmateriaal op oppervlak: `area_m2`

The take-off stores geometry only. The recipe decides which sides and how many passes are used.
