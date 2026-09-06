import XCTest
@testable import BreboMeasurePOC

final class ScanResearchConfigTests: XCTestCase {
    func testResearchThresholdsAreExplicit() {
        XCTAssertGreaterThan(ScanResearchConfig.minimumDepthFrames, 0)
        XCTAssertGreaterThan(ScanResearchConfig.minimumPlaneSamples, 0)
        XCTAssertEqual(ScanResearchConfig.repeatedPhysicalScans, 10)
    }
}
