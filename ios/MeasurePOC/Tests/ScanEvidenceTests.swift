import XCTest
@testable import BreboMeasurePOC

final class ScanEvidenceTests: XCTestCase {
    func testDetectedGeometryKeepsDetectedProvenance() {
        let evidence = ScanEvidence(
            key: "opening.width.middle",
            provenance: .detected,
            valueMm: 1200,
            uncertaintyMm: 2,
            confidence: 0.95
        )
        XCTAssertEqual(evidence.provenance, .detected)
    }
}
