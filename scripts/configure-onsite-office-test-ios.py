#!/usr/bin/env python3
"""Configure only XcodeGen's generated OnSite plist for the explicit test workflow."""
from pathlib import Path
import plistlib

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / "ios/CrewPOC/Resources/Info.plist"


def configure(path: Path) -> None:
    with path.open("rb") as handle:
        data = plistlib.load(handle)
    if data.get("CFBundleDisplayName") not in ("BREBO OnSite", "OnSite Test"):
        raise ValueError("Unexpected OnSite plist; refusing to alter another app")
    if data.get("OnSiteAPIBaseURL") not in (
        "https://sboffice.brebobv.nl", "https://brebobv.nl/office-test"
    ):
        raise ValueError("Unexpected API base URL; refusing to alter build")
    data["OnSiteAPIBaseURL"] = "https://brebobv.nl/office-test"
    data["OnSiteEnvironment"] = "office-test"
    data["CFBundleDisplayName"] = "OnSite Test"
    with path.open("wb") as handle:
        plistlib.dump(data, handle)


if __name__ == "__main__":
    configure(PATH)
    print("OnSite Office-test endpoint configured; production project spec unchanged")
