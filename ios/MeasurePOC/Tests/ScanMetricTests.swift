import XCTest
@testable import BreboMeasurePOC

final class ScanMetricTests: XCTestCase {
    func testValidationTracksAccuracyAndWorkflowCost() {
        XCTAssertTrue(ScanMetric.allCases.contains(.widthDeviationMm))
        XCTAssertTrue(ScanMetric.allCases.contains(.humanCorrections))
        XCTAssertTrue(ScanMetric.allCases.contains(.revisitRequired))
    }
}
