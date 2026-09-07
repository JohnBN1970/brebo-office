import XCTest
@testable import BreboMeasurePOC

final class ScanSessionModelTests: XCTestCase {
    func testScanCountersOnlyAdvanceWhileScanning() {
        var model = ScanSessionModel()
        model.registerDepthFrame()
        XCTAssertEqual(model.depthFrameCount, 0)

        model.start()
        model.registerDepthFrame()
        model.updateMeshAnchorCount(3)
        XCTAssertEqual(model.state, .scanning)
        XCTAssertEqual(model.depthFrameCount, 1)
        XCTAssertEqual(model.meshAnchorCount, 3)

        model.stopForAnalysis()
        model.registerDepthFrame()
        XCTAssertEqual(model.state, .analysing)
        XCTAssertEqual(model.depthFrameCount, 1)
    }
}
