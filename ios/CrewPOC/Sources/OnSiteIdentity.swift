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
    @Published private(set) var assignedProjects: [OnSiteBootstrapProject] = []
    @Published private(set) var officeApprovals: [UUID: Date]?

    private let apiClient: OnSiteAPIClient
    private let defaults: UserDefaults
    private let deviceToken: () -> String?
    private let clearDeviceToken: () -> Void
    private var activationInProgress = false
    private var redeemedActivationToken: String?
    private var refreshing = false
    private let cacheKey = "brebo.onsite.bootstrap.v1"

    init(apiClient: OnSiteAPIClient = OnSiteAPIClient(), defaults: UserDefaults = .standard,
         deviceToken: @escaping () -> String? = OnSiteSecureStore.deviceToken,
         clearDeviceToken: @escaping () -> Void = OnSiteSecureStore.clear) {
        self.apiClient = apiClient
        self.defaults = defaults
        self.deviceToken = deviceToken
        self.clearDeviceToken = clearDeviceToken
        restoreCachedIdentity()
    }

    func restoreCachedIdentity() {
        guard deviceToken() != nil,
              let data = defaults.data(forKey: cacheKey),
              let bootstrap = try? JSONDecoder().decode(OnSiteBootstrap.self, from: data) else { return }
        apply(bootstrap)
    }

    private func apply(_ bootstrap: OnSiteBootstrap) {
        let officeLanguage = OnSiteLanguage(rawValue: bootstrap.employee.language) ?? .dutch
        language = officeLanguage
        assignedProjects = bootstrap.projects
        // Nil means the Office endpoint has not provided an authoritative snapshot.
        // Do not treat an omitted field as a revocation or as approval.
        if let approvals = bootstrap.approvedEvents {
            officeApprovals = Dictionary(uniqueKeysWithValues: approvals.compactMap { key, value in
                guard let id = UUID(uuidString: key) else { return nil }
                return (id, value)
            })
        }
        state = .linked(employeeId: bootstrap.employee.id,
                        displayName: bootstrap.employee.displayName, language: officeLanguage)
    }

    func activate(activationToken: String) async {
        let token = activationToken.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !token.isEmpty, !activationInProgress else { return }
        if redeemedActivationToken == token, deviceToken() != nil {
            await refreshBootstrap()
            return
        }
        activationInProgress = true
        defer { activationInProgress = false }

        errorMessage = nil
        do {
            let activated = try await apiClient.activate(activationToken: token)
            try OnSiteSecureStore.saveDeviceToken(activated.deviceToken)
            defaults.removeObject(forKey: cacheKey)
            assignedProjects = []
            state = .unlinked
            redeemedActivationToken = token
            await refreshBootstrap()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

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
            try OnSiteSecureStore.saveDeviceToken(verified.deviceToken)
            let officeLanguage = OnSiteLanguage(rawValue: verified.language) ?? .dutch
            language = officeLanguage
            state = .linked(
                employeeId: verified.employeeId,
                displayName: verified.displayName,
                language: officeLanguage
            )
            await refreshBootstrap()
        } catch {
            state = .codeSent(phoneNumber: phoneNumber, challengeId: challengeId)
            errorMessage = error.localizedDescription
        }
    }

    func restoreLinkedDevice() async {
        restoreCachedIdentity()
        await refreshBootstrap()
    }

    func refreshBootstrap() async {
        guard !refreshing, let token = deviceToken() else { return }
        refreshing = true
        defer { refreshing = false }
        do {
            let bootstrap = try await apiClient.bootstrap(deviceToken: token)
            // Do not apply an old request after reset or a new activation.
            guard token == deviceToken() else { return }
            if let data = try? JSONEncoder().encode(bootstrap) {
                defaults.set(data, forKey: cacheKey)
            }
            apply(bootstrap)
            errorMessage = nil
        } catch OnSiteAPIError.unauthorized {
            guard token == deviceToken() else { return }
            reset()
            errorMessage = "Dit apparaat is niet meer gekoppeld."
        } catch {
            // Preserve cached identity and zones on network/server failures.
            errorMessage = error.localizedDescription
        }
    }

    func reset() {
        defaults.removeObject(forKey: cacheKey)
        clearDeviceToken()
        assignedProjects = []
        officeApprovals = nil
        state = .unlinked
        language = .bootstrap()
        redeemedActivationToken = nil
        errorMessage = nil
    }
}
