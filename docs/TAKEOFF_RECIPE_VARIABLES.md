# Take-off recipe variables

Recipes may be placed against a BREBO calculation take-off row.

Geometry variables are **per element**. The element count is separate as `quantity` / `element_quantity`.

Available formula variables:

- `top_m`
- `bottom_m`
- `left_m`
- `right_m`
- `perimeter_m`
- `area_m2`
- `width_mm`
- `height_mm`
- `quantity` (number of elements)
- `element_quantity` (explicit alias for number of elements)
- `passes`

Examples:

- kit rondom, één gang voor alle elementen: `perimeter_m * quantity`
- kit binnen + buiten: `perimeter_m * quantity * passes`
- vensterbank: `bottom_m * quantity`
- kantelaaf drie zijden: `(left_m + top_m + right_m) * quantity`
- compriband drie zijden: `(left_m + top_m + right_m) * quantity * passes`
- plaatmateriaal op oppervlak: `area_m2 * quantity`

The take-off stores geometry only. The recipe decides which sides and how many passes are used.
