import XCTest
@testable import BreboMeasurePOC

final class ScanStopDecisionTests: XCTestCase {
    func testStopDecisionFollowsCoverageGate() {
        XCTAssertEqual(ScanStopDecision.make(depthFrames: 5, meshAnchors: 0), .keepScanning)
        XCTAssertEqual(ScanStopDecision.make(depthFrames: 30, meshAnchors: 1), .mayStopAndAnalyse)
    }
}
