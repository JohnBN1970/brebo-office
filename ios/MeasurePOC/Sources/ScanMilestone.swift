import Foundation

enum ScanMilestone: String {
    case cloudCompile = "cloud_compile"
    case testFlightInstall = "testflight_install"
    case physicalCapture = "physical_capture"
    case automaticPlaneExtraction = "automatic_plane_extraction"
    case openingDetection = "opening_detection"
    case accuracyValidation = "accuracy_validation"

    static let current: ScanMilestone = .cloudCompile
}
