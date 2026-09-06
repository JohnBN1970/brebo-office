import XCTest
@testable import BreboMeasurePOC

final class ScanQualityGateTests: XCTestCase {
    func testRequiresDepthAndMeshCoverage() {
        let gate = ScanQualityGate(minimumDepthFrames: 30, minimumMeshAnchors: 1)
        XCTAssertEqual(gate.evaluate(depthFrames: 29, meshAnchors: 1), .insufficientCoverage)
        XCTAssertEqual(gate.evaluate(depthFrames: 30, meshAnchors: 0), .insufficientCoverage)
        XCTAssertEqual(gate.evaluate(depthFrames: 30, meshAnchors: 1), .readyForGeometry)
    }
}
