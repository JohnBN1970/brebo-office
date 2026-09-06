import XCTest
@testable import BreboMeasurePOC

final class MeasurementApprovalGateTests: XCTestCase {
    func testProductionReleaseIsOffByDefault() {
        func plane(normal: SIMD3<Float>, point: SIMD3<Float>) -> DetectedPlane {
            DetectedPlane(normal: normal, point: point, sampleCount: 1000, residualMm: 0.5)
        }
        let boundaries = OpeningBoundarySet(
            left: plane(normal: SIMD3<Float>(1, 0, 0), point: .zero),
            right: plane(normal: SIMD3<Float>(-1, 0, 0), point: SIMD3<Float>(1, 0, 0)),
            top: plane(normal: SIMD3<Float>(0, 1, 0), point: SIMD3<Float>(0, 2, 0)),
            bottom: plane(normal: SIMD3<Float>(0, -1, 0), point: .zero)
        )
        let candidate = OpeningCandidate.make(from: boundaries)!
        XCTAssertEqual(MeasurementApprovalGate().evaluate(candidate), .researchOnly)
    }
}
