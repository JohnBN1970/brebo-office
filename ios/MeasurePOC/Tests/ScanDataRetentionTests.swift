import XCTest
@testable import BreboMeasurePOC

final class ScanDataRetentionTests: XCTestCase {
    func testRawFramesAreNotPersistedByDefault() {
        XCTAssertFalse(ScanDataRetention.persistRawDepthFrames)
        XCTAssertFalse(ScanDataRetention.persistRawCameraFrames)
        XCTAssertTrue(ScanDataRetention.persistDerivedEvidence)
    }
}
