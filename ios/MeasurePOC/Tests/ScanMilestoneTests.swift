import XCTest
@testable import BreboMeasurePOC

final class ScanMilestoneTests: XCTestCase {
    func testCurrentMilestoneIsCloudCompile() {
        XCTAssertEqual(ScanMilestone.current, .cloudCompile)
    }
}
