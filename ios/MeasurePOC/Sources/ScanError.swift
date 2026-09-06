import Foundation

enum ScanError: Error, Equatable {
    case lidarUnavailable
    case insufficientCoverage
    case geometryNotFound
    case geometryUncertain

    var message: String {
        switch self {
        case .lidarUnavailable: return "LiDAR/mesh is niet beschikbaar op dit toestel"
        case .insufficientCoverage: return "Scan meer van de volledige sparing"
        case .geometryNotFound: return "Sparing nog niet betrouwbaar herkend"
        case .geometryUncertain: return "Geometrie onzeker - opnieuw scannen"
        }
    }
}
