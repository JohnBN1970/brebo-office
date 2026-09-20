import Foundation

/// Language is Office-owned and transported as a BCP-47 language tag.
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
    case requestingCode
    case codeSent(phoneNumber: String, challengeId: String)
    case verifying(phoneNumber: String, challengeId: String)
    case linked(employeeId: String, displayName: String, language: OnSiteLanguage)
}

@MainActor
final class OnSiteIdentityStore: ObservableObject {
    @Published private(set) var state: OnSiteLinkState = .unlinked
    @Published private(set) var language: OnSiteLanguage = .bootstrap()
    @Published private(set) var errorMessage: String?

    private let apiClient = OnSiteAPIClient()

    func requestCode(phoneNumber: String) async {
        let normalized = phoneNumber.filter { $0.isNumber || $0 == "+" }
        guard normalized.count >= 8 else {
            errorMessage = "Controleer het mobiele nummer."
            return
        }

        state = .requestingCode
        errorMessage = nil

        do {
            let challenge = try await apiClient.requestCode(mobile: normalized)
            state = .codeSent(phoneNumber: normalized, challengeId: challenge.challengeId)
        } catch {
            state = .unlinked
            errorMessage = error.localizedDescription
        }
    }

    func verifyCode(phoneNumber: String, challengeId: String, code: String) async {
        guard code.filter({ $0.isNumber }).count == 6 else {
            errorMessage = "Vul de 6-cijferige verificatiecode in."
            return
        }

        state = .verifying(phoneNumber: phoneNumber, challengeId: challengeId)
        errorMessage = nil

        do {
            let verified = try await apiClient.verifyCode(challengeId: challengeId, code: code)
            OnSiteSecureStore.saveDeviceToken(verified.deviceToken)
            let officeLanguage = OnSiteLanguage(rawValue: verified.language) ?? .dutch
            language = officeLanguage
            state = .linked(
                employeeId: verified.employeeId,
                displayName: verified.displayName,
                language: officeLanguage
            )
        } catch {
            state = .codeSent(phoneNumber: phoneNumber, challengeId: challengeId)
            errorMessage = error.localizedDescription
        }
    }

    func reset() {
        OnSiteSecureStore.clear()
        state = .unlinked
        language = .bootstrap()
        errorMessage = nil
    }
}
