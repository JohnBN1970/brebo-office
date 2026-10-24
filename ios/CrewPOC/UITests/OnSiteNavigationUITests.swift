import XCTest

final class OnSiteNavigationUITests: XCTestCase {
    @MainActor
    func testEmployeeNavigationAndProjectDetails() {
        let app = XCUIApplication()
        app.launchArguments = ["--onsite-menu-preview", "-AppleLanguages", "(nl)", "-AppleLocale", "nl_NL"]
        app.launch()

        XCTAssertTrue(app.navigationBars["Vandaag"].waitForExistence(timeout: 15))
        XCTAssertTrue(app.staticTexts["Hallo, Alex"].exists)
        capture("01-vandaag")

        app.tabBars.buttons["Projecten"].tap()
        XCTAssertTrue(app.navigationBars["Projecten"].exists)
        capture("02-projecten")
        app.staticTexts["Voorbeeldproject"].tap()
        XCTAssertTrue(app.navigationBars["Project"].waitForExistence(timeout: 5))
        XCTAssertTrue(app.buttons["Route naar projectzone"].exists)
        capture("03-projectdetail")

        app.tabBars.buttons["Registraties"].tap()
        XCTAssertTrue(app.navigationBars["Registraties"].exists)
        XCTAssertTrue(app.staticTexts["IN"].exists)
        capture("04-registraties")

        app.tabBars.buttons["Mijn profiel"].tap()
        XCTAssertTrue(app.navigationBars["Mijn profiel"].exists)
        let employeeName = app.descendants(matching: .any).matching(identifier: "onsite.profile.name").firstMatch
        XCTAssertTrue(employeeName.waitForExistence(timeout: 5))
        XCTAssertEqual(employeeName.value as? String, "Alex")
        capture("05-profiel")

        app.tabBars.buttons["Vandaag"].tap()
        XCTAssertTrue(app.staticTexts["Hallo, Alex"].exists)
    }

    @MainActor
    private func capture(_ name: String) {
        let attachment = XCTAttachment(screenshot: XCUIApplication().screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }
}
