import XCTest
@testable import BreboMeasurePOC

final class MeasureObservationAdapterTests: XCTestCase {
    func testAutomaticScanPayloadKeepsEvidenceMetadata() {
        let evidence = ScanEvidence(key: "opening.width.middle", provenance: .detected, valueMm: 1234, uncertaintyMm: 2.5, confidence: 0.91)
        let payload = MeasureObservationAdapter.payloads(from: [evidence]).first!
        XCTAssertEqual(payload.key, "opening.width.middle")
        XCTAssertEqual(payload.provenance, "detected")
        XCTAssertEqual(payload.method, "ios_arkit_lidar_automatic_scan")
        XCTAssertEqual(payload.value.value, 1234)
        XCTAssertEqual(payload.uncertaintyMm, 2.5)
    }
}
