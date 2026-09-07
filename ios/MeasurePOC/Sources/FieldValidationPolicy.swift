import Foundation

struct FieldValidationPolicy {
    let requiredRepeatedScans: Int
    let maximumMeanAbsoluteDeviationMm: Double
    let maximumSpreadMm: Double

    init(
        requiredRepeatedScans: Int = 10,
        maximumMeanAbsoluteDeviationMm: Double = 3,
        maximumSpreadMm: Double = 5
    ) {
        self.requiredRepeatedScans = requiredRepeatedScans
        self.maximumMeanAbsoluteDeviationMm = maximumMeanAbsoluteDeviationMm
        self.maximumSpreadMm = maximumSpreadMm
    }

    // These are research gates, not a declaration of production accuracy.
}
