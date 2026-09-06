# BREBO Measure API v0.1

This is the first Office-side contract for a mobile Measure client.

## Workflow

1. Office already knows the canonical opening.
2. Create a measurement assignment.
3. Start a versioned capture for a source such as `apple_lidar`.
4. Add observations while the operator measures and confirms geometry.
5. Office keeps provenance, confidence and uncertainty with every observation.

## Endpoints

### GET opening
`GET /brebo-office/api/measure/openings/{opening_id}`

### Create assignment
`POST /brebo-office/api/measure/openings/{opening_id}/assignments`

```json
{
  "assigned_uid": 12,
  "requirements": {
    "reference_side": "inside",
    "control_dimensions_required": true
  }
}
```

### Create capture
`POST /brebo-office/api/measure/assignments/{assignment_id}/captures`

```json
{
  "source_type": "apple_lidar",
  "device_id": "device-local-id",
  "device_model": "iPhone Pro",
  "software_version": "measure-ios/0.1",
  "operator_uid": 12,
  "geometry": {},
  "quality": {}
}
```

### Add observation
`POST /brebo-office/api/measure/captures/{capture_id}/observations`

```json
{
  "key": "opening.width.middle",
  "provenance": "measured",
  "method": "lidar",
  "value": {"value": 1843, "unit": "mm"},
  "confidence": 0.92,
  "uncertainty_mm": 2.0
}
```

A physical control measurement is another observation and never overwrites the LiDAR observation. This lets BREBO quantify the measurement error of its own system.

## Deliberate limits

This contract does not yet release production dimensions, run AI validation, reconstruct a full mesh, or implement mobile authentication. Those are later increments after the first Office-to-device workflow is proven.
