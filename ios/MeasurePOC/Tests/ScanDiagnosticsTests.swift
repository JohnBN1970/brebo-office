import XCTest
@testable import BreboMeasurePOC

final class ScanDiagnosticsTests: XCTestCase {
    func testDiagnosticsRecordAutomaticMode() {
        let diagnostics = ScanDiagnostics.make(depthFrames: 42, meshAnchors: 3, durationSeconds: 5)
        XCTAssertEqual(diagnostics.depthFrames, 42)
        XCTAssertEqual(diagnostics.meshAnchors, 3)
        XCTAssertEqual(diagnostics.mode, .automaticScan)
    }
}
