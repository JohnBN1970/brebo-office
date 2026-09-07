import XCTest
@testable import BreboMeasurePOC

final class OpeningCandidateTests: XCTestCase {
    func testCandidateMeasuresParallelBoundarySeparation() {
        func plane(normal: SIMD3<Float>, point: SIMD3<Float>) -> DetectedPlane {
            DetectedPlane(normal: normal, point: point, sampleCount: 100, residualMm: 2)
        }
        let boundaries = OpeningBoundarySet(
            left: plane(normal: SIMD3<Float>(1, 0, 0), point: SIMD3<Float>(0, 0, 0)),
            right: plane(normal: SIMD3<Float>(-1, 0, 0), point: SIMD3<Float>(1.2, 0, 0)),
            top: plane(normal: SIMD3<Float>(0, 1, 0), point: SIMD3<Float>(0, 2.1, 0)),
            bottom: plane(normal: SIMD3<Float>(0, -1, 0), point: SIMD3<Float>(0, 0, 0))
        )
        let candidate = OpeningCandidate.make(from: boundaries)
        XCTAssertNotNil(candidate)
        XCTAssertEqual(candidate?.widthMm ?? 0, 1200, accuracy: 0.1)
        XCTAssertEqual(candidate?.heightMm ?? 0, 2100, accuracy: 0.1)
        XCTAssertEqual(candidate?.uncertaintyMm ?? 0, 2, accuracy: 0.001)
    }
}
