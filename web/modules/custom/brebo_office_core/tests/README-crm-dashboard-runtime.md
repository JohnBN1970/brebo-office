# CRM dashboard runtime regression

The dashboard stage aggregates are keyed by stage labels. PHP argument unpacking of associative arrays passes string keys as named arguments, so aggregate maxima must be computed from a single values array rather than unpacked directly into `max()`.

Regression target: `/relaties/funnel` must render without `ArgumentCountError: max() does not accept unknown named parameters`.
