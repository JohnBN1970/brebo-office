import XCTest
@testable import BreboMeasurePOC

final class ScanCoverageTests: XCTestCase {
    func testCoverageNeedsBothDepthAndMesh() {
        XCTAssertEqual(ScanCoverage(depthFrames: 30, meshAnchors: 0).progress(), 0)
        XCTAssertEqual(ScanCoverage(depthFrames: 15, meshAnchors: 1).progress(), 0.5, accuracy: 0.001)
        XCTAssertEqual(ScanCoverage(depthFrames: 30, meshAnchors: 1).progress(), 1, accuracy: 0.001)
    }
}
