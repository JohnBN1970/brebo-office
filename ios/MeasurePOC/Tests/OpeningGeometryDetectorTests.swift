import XCTest
@testable import BreboMeasurePOC

final class OpeningGeometryDetectorTests: XCTestCase {
    func testRejectsInsufficientGeometry() {
        let detector = OpeningGeometryDetector()
        XCTAssertThrowsError(try detector.detect(from: [])) { error in
            guard case OpeningGeometryDetector.DetectionError.insufficientGeometry = error else {
                return XCTFail("Expected insufficientGeometry")
            }
        }
    }

    func testUnclassifiedGeometryFailsClosed() {
        let plane = DetectedPlane(
            normal: SIMD3<Float>(0, 0, 1),
            point: SIMD3<Float>(0, 0, 0),
            sampleCount: 100,
            residualMm: 2
        )
        let detector = OpeningGeometryDetector()
        XCTAssertThrowsError(try detector.detect(from: [plane, plane, plane, plane])) { error in
            guard case OpeningGeometryDetector.DetectionError.noOpeningCandidate = error else {
                return XCTFail("Expected noOpeningCandidate")
            }
        }
    }
}
