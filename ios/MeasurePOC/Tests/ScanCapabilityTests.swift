import XCTest
@testable import BreboMeasurePOC

final class ScanCapabilityTests: XCTestCase {
    func testPOCRequiresDepthAndMesh() {
        XCTAssertFalse(ScanCapability(supportsSceneDepth: false, supportsMeshReconstruction: true).isSupportedForMeasurePOC)
        XCTAssertFalse(ScanCapability(supportsSceneDepth: true, supportsMeshReconstruction: false).isSupportedForMeasurePOC)
        XCTAssertTrue(ScanCapability(supportsSceneDepth: true, supportsMeshReconstruction: true).isSupportedForMeasurePOC)
    }
}
