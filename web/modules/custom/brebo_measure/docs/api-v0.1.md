# BREBO Measure API v0.1

Purpose: prove one vertical measurement flow before building the mobile app.

## Flow

1. `GET /brebo-office/api/measure/openings/{opening_id}`
2. `POST /brebo-office/api/measure/openings/{opening_id}/assignments`
3. `POST /brebo-office/api/measure/assignments/{assignment_id}/captures`
4. `POST /brebo-office/api/measure/captures/{capture_id}/observations`

All routes require `use brebo measure api`.

## Create assignment

```json
{
  "requirements": {
    "purpose": "validation",
    "reference_required": true
  }
}
```

## Create capture

```json
{
  "source_type": "apple_lidar",
  "context": {
    "device_model": "iPhone Pro",
    "software_version": "measure-poc",
    "geometry": {},
    "quality": {}
  }
}
```

## Add observation

```json
{
  "key": "opening.width.middle",
  "provenance": "measured",
  "value": {"value": 1843, "unit": "mm"},
  "method": "lidar_depth",
  "confidence": 0.93,
  "uncertainty_mm": 3.0
}
```

The API is intentionally small. It does not yet approve production sizes, reconstruct geometry or perform AI review.
