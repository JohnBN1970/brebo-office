import XCTest
@testable import BreboMeasurePOC

final class OpeningCandidateQualityTests: XCTestCase {
    func testAllFourBoundariesNeedUsablePlaneFits() {
        func plane(_ normal: SIMD3<Float>, samples: Int = 100) -> DetectedPlane {
            DetectedPlane(normal: normal, point: .zero, sampleCount: samples, residualMm: 2)
        }
        let good = OpeningBoundarySet(
            left: plane(SIMD3<Float>(1, 0, 0)), right: plane(SIMD3<Float>(-1, 0, 0)),
            top: plane(SIMD3<Float>(0, 1, 0)), bottom: plane(SIMD3<Float>(0, -1, 0))
        )
        XCTAssertTrue(OpeningCandidateQuality.isUsable(good))

        let weak = OpeningBoundarySet(
            left: plane(SIMD3<Float>(1, 0, 0), samples: 10), right: plane(SIMD3<Float>(-1, 0, 0)),
            top: plane(SIMD3<Float>(0, 1, 0)), bottom: plane(SIMD3<Float>(0, -1, 0))
        )
        XCTAssertFalse(OpeningCandidateQuality.isUsable(weak))
    }
}
