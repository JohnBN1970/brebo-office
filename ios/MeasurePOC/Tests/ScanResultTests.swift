import XCTest
@testable import BreboMeasurePOC

final class ScanResultTests: XCTestCase {
    func testMissingGeometryIsExplicit() {
        let result = ScanResult(candidate: nil, depthFrames: 100, meshAnchors: 4, approval: .researchOnly)
        XCTAssertTrue(result.summary.contains("geen betrouwbare"))
    }
}
