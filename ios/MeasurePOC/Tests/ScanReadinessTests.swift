import XCTest
@testable import BreboMeasurePOC

final class ScanReadinessTests: XCTestCase {
    func testReadinessDoesNotOverclaimUnprovenGeometryOrAccuracy() {
        XCTAssertTrue(ScanReadiness.hasTapFreeCaptureShell)
        XCTAssertTrue(ScanReadiness.hasCloudBuildDefinition)
        XCTAssertFalse(ScanReadiness.hasAutomaticPlaneExtraction)
        XCTAssertFalse(ScanReadiness.hasPhysicalAccuracyEvidence)
    }
}
