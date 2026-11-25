@local @local_auditenhance
Feature: Audit overview and area pages
  In order to know what to fix first on my site
  As an administrator
  I need to see the overall score, the area scores and the findings

  Scenario: The overview shows the areas and the top problems
    Given I log in as "admin"
    When I navigate to "Reports > Audit & Enhance > Site audit" in site administration
    Then I should see "Overall score"
    And I should see "Security and configuration"
    And I should see "Accessibility"
    And I should see "Top things to fix"
    And "Run audit now" "button" should exist

  Scenario: An administrator queues an audit
    Given I log in as "admin"
    And I navigate to "Reports > Audit & Enhance > Site audit" in site administration
    When I press "Run audit now"
    Then I should see "The audit has been queued"
    And I should see "Queued"

  Scenario: An administrator mutes and unmutes a check
    Given I log in as "admin"
    And I navigate to "Reports > Audit & Enhance > Site audit" in site administration
    And I follow "Security and configuration"
    And I click on "Mute" "link" in the "#local-auditenhance-local_auditenhance_defaultadmin" "css_element"
    And I set the field "Reason" to "Kept on purpose for the demo site"
    And I press "Mute"
    Then I should see "is muted."
    And I should see "Kept on purpose for the demo site"
    When I navigate to "Reports > Audit & Enhance > Site audit" in site administration
    And I follow "Muted checks"
    Then I should see "Default \"admin\" username"
    And I click on "Unmute" "link"
    And I should see "is no longer muted"

  Scenario: Screens are listed as tabs
    Given I log in as "admin"
    When I navigate to "Reports > Audit & Enhance > Site audit" in site administration
    Then I should see "Accessibility by course"
    And I should see "Plugin risk by plugin"
    And I should see "Muted checks"
