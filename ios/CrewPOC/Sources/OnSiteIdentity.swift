import Foundation

enum OnSiteLanguage: String, Codable, CaseIterable {
    case nl, en, ro, pl, uk

    static func bootstrap(from preferredLanguages: [String] = Locale.preferredLanguages) -> OnSiteLanguage {
        guard let code = preferredLanguages.first?.split(separator: "-").first else { return .nl }
        return OnSiteLanguage(rawValue: String(code)) ?? .nl
    }
}

enum OnSiteLinkState: Equatable {
    case unlinked
    case codeSent(phoneNumber: String)
    case linked(employeeId: String, displayName: String, language: OnSiteLanguage)
}

@MainActor
final class OnSiteIdentityStore: ObservableObject {
    @Published private(set) var state: OnSiteLinkState = .unlinked
    @Published private(set) var language: OnSiteLanguage = .bootstrap()

    func beginLink(phoneNumber: String) {
        let normalized = phoneNumber.filter { $0.isNumber || $0 == "+" }
        guard normalized.count >= 8 else { return }
        state = .codeSent(phoneNumber: normalized)
    }

    func applyVerifiedIdentity(employeeId: String, displayName: String, language: OnSiteLanguage) {
        self.language = language
        state = .linked(employeeId: employeeId, displayName: displayName, language: language)
    }

    func reset() {
        state = .unlinked
        language = .bootstrap()
    }
}
