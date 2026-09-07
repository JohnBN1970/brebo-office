import XCTest
@testable import BreboMeasurePOC

final class ScanFeatureFlagsTests: XCTestCase {
    func testPOCIsAutomaticButCannotUploadOrApproveProduction() {
        XCTAssertTrue(ScanFeatureFlags.automaticScan)
        XCTAssertFalse(ScanFeatureFlags.manualPointMeasurement)
        XCTAssertFalse(ScanFeatureFlags.officeUpload)
        XCTAssertFalse(ScanFeatureFlags.productionApproval)
    }
}
