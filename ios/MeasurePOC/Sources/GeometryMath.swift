import Foundation
import simd

enum GeometryMath {
    static func distanceMillimetres(from point: SIMD3<Float>, toPlane plane: DetectedPlane) -> Double {
        let normalLength = simd_length(plane.normal)
        guard normalLength > 0 else { return .infinity }
        let unitNormal = plane.normal / normalLength
        return Double(abs(simd_dot(point - plane.point, unitNormal)) * 1000.0)
    }

    static func areApproximatelyParallel(
        _ lhs: DetectedPlane,
        _ rhs: DetectedPlane,
        cosineThreshold: Float = 0.98
    ) -> Bool {
        let lhsLength = simd_length(lhs.normal)
        let rhsLength = simd_length(rhs.normal)
        guard lhsLength > 0, rhsLength > 0 else { return false }
        let cosine = abs(simd_dot(lhs.normal / lhsLength, rhs.normal / rhsLength))
        return cosine >= cosineThreshold
    }
}
