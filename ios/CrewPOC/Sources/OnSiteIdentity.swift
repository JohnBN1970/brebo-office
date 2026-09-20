import Foundation

/// Language is Office-owned and transported as a BCP-47 language tag.
/// OnSite deliberately does not hard-code a closed list so the product can be
/// sold internationally without changing identity or presence logic.
struct OnSiteLanguage: RawRepresentable, Codable, Equatable, Hashable {
    let rawValue: String

    init?(rawValue: String) {
        let normalized = rawValue.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !normalized.isEmpty else { return nil }
        self.rawValue = normalized
    }

    static let dutch = OnSiteLanguage(rawValue: "nl")!

    static func bootstrap(from preferredLanguages: [String] = Locale.preferredLanguages) -> OnSiteLanguage {
        guard let preferred = preferredLanguages.first,
              let language = OnSiteLanguage(rawValue: preferred) else {
            return .dutch
        }
        return language
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

    func applyVerifiedIdentity(employeeId: String, displayName: String, languageTag: String) {
        let officeLanguage = OnSiteLanguage(rawValue: languageTag) ?? .dutch
        language = officeLanguage
        state = .linked(employeeId: employeeId, displayName: displayName, language: officeLanguage)
    }

    func reset() {
        state = .unlinked
        language = .bootstrap()
    }
}
