import Foundation

struct ScanExperiment: Codable {
    let openingReference: String
    let referenceWidthMm: Double?
    let referenceHeightMm: Double?
    let diagnostics: ScanDiagnostics
    let detectedWidthMm: Double?
    let detectedHeightMm: Double?
    let uncertaintyMm: Double?

    var widthDeviationMm: Double? {
        guard let referenceWidthMm, let detectedWidthMm else { return nil }
        return detectedWidthMm - referenceWidthMm
    }

    var heightDeviationMm: Double? {
        guard let referenceHeightMm, let detectedHeightMm else { return nil }
        return detectedHeightMm - referenceHeightMm
    }
}
