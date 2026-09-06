import Foundation

struct OpeningBoundarySet {
    let left: DetectedPlane
    let right: DetectedPlane
    let top: DetectedPlane
    let bottom: DetectedPlane

    var hasParallelOpposites: Bool {
        GeometryMath.areApproximatelyParallel(left, right) &&
        GeometryMath.areApproximatelyParallel(top, bottom)
    }
}
