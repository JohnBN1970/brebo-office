import XCTest
@testable import BreboMeasurePOC

final class ScanStatusTests: XCTestCase {
    func testGuidanceChangesWhenCoverageIsReady() {
        let collecting = ScanStatus(depthFrames: 5, meshAnchors: 0, verdict: .insufficientCoverage)
        let ready = ScanStatus(depthFrames: 50, meshAnchors: 3, verdict: .readyForGeometry)
        XCTAssertTrue(collecting.message.contains("Beweeg"))
        XCTAssertTrue(ready.message.contains("geometrie"))
    }
}
