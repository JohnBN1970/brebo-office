import XCTest
@testable import BreboMeasurePOC

final class MeasureModeTests: XCTestCase {
    func testAutomaticScanIsPrimaryInteraction() {
        XCTAssertEqual(MeasureMode.primary, .automaticScan)
    }
}
