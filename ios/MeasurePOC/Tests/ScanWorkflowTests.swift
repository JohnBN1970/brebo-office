import XCTest
@testable import BreboMeasurePOC

final class ScanWorkflowTests: XCTestCase {
    func testPOCWorkflowDoesNotJumpFromCaptureToProduction() {
        XCTAssertTrue(ScanWorkflow.poc.contains(.analyse))
        XCTAssertTrue(ScanWorkflow.poc.contains(.reviewDetectedGeometry))
        XCTAssertTrue(ScanWorkflow.poc.contains(.saveEvidence))
    }
}
