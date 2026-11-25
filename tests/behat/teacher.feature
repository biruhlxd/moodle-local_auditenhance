@local @local_auditenhance
Feature: Teachers see the accessibility issues of their course
  In order to fix inaccessible content
  As an editing teacher
  I need to see the accessibility issues of my course

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Biology  | BIO       |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Terry     | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BIO    | editingteacher |
      | student1 | BIO    | student        |

  Scenario: The teacher view says when no audit has run
    Given I am on the "BIO" "Course" page logged in as "teacher1"
    When I navigate to "Accessibility audit" in current page administration
    Then I should see "No audit has scanned this yet"

  Scenario: Students cannot see the accessibility audit
    Given I am on the "BIO" "Course" page logged in as "student1"
    Then "Accessibility audit" "link" should not exist
