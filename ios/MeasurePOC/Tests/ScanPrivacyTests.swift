import XCTest
@testable import BreboMeasurePOC

final class ScanPrivacyTests: XCTestCase {
    func testPOCDoesNotUploadRawCapture() {
        XCTAssertFalse(ScanPrivacy.rawCaptureUploadEnabled)
        XCTAssertFalse(ScanPrivacy.automaticOfficeUploadEnabled)
    }
}
