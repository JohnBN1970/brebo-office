import Foundation

enum CrewPrivacyPolicy {
    static let storesRouteHistory = false
    static let storesContinuousCoordinates = false
    static let storesPresenceEvents = true
    static let locationPurpose = "project_presence"
    static let primaryRule = "registreer aanwezigheid op project, niet de route van de persoon"
}
