# Drupal 11 update 10001 compatibility

`brebo_document_data_update_10001()` must pass the complete table specification as the fourth argument to MySQL `Schema::addIndex()`.

The update now reuses `brebo_document_data_schema()` for the `brebo_document` and `brebo_document_source` table specifications, so MySQL can normalize the affected indexes correctly.
