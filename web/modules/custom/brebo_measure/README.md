# BREBO Measure

BREBO Measure is the canonical measurement domain for BREBO Office.

## Domain chain

`building object -> opening -> assignment -> capture -> observation -> validation`

## Provenance

Every observation is classified as exactly one of:

- `measured`: directly observed by a measurement source;
- `detected`: derived from sensor or vision recognition;
- `selected`: explicitly chosen by a user or project rule;
- `calculated`: deterministically derived from other data.

These classes must not be silently overwritten into one another.

## Source neutrality

Captures are deliberately device-agnostic. `source_type`, device metadata, geometry and quality payloads can later represent Apple LiDAR, a laser, a BREBO precision tool or FrameBot without changing the opening identity.

## First milestone

This module only establishes the persistent domain foundation. Mobile capture UI, LiDAR ingestion, validation rules and AI review are separate follow-up increments.
