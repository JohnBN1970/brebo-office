# Measure API security boundary

The first API uses the existing authenticated BREBO Office `access content` boundary, matching the current building-data API convention.

Before external/mobile distribution, replace this coarse boundary with dedicated Measure permissions and authenticated device/session rules. Production release must never be authorized solely by a capture client.
