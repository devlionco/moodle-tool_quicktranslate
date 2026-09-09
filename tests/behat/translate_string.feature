@tool @tool_quicktranslate @javascript
Feature: Translate the strings of the page being viewed
  In order to adapt the wording of the site without editing language packs
  As a site administrator
  I need to translate strings from the page they appear on

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | tool_quicktranslate |
    And I log in as "admin"
    And I am on site homepage

  Scenario: The sidebar stays out of the way until edit mode is on
    Then "Toggle the quick translate menu" "button" should not exist
    When I turn editing mode on
    Then "Toggle the quick translate menu" "button" should exist

  Scenario: The sidebar is not offered while the tool is disabled
    Given the following config values are set as admin:
      | enabled | 0 | tool_quicktranslate |
    When I turn editing mode on
    Then "Toggle the quick translate menu" "button" should not exist

  Scenario: Searching narrows the list of strings down
    Given I turn editing mode on
    And I open the quick translate sidebar
    Then ".quicktranslate-item[data-id='search/tool_quicktranslate']" "css_element" should be visible
    When I search for "Search & Translate" in the quick translate sidebar
    Then ".quicktranslate-item[data-id='searchandtranslatethewords/tool_quicktranslate']" "css_element" should be visible
    And ".quicktranslate-item[data-id='search/tool_quicktranslate']" "css_element" should not be visible

  Scenario: Translating a string changes what the page says
    Given I turn editing mode on
    And I open the quick translate sidebar
    When I translate the string "searchandtranslatethewords/tool_quicktranslate"
    And I set the field "en" in the "Set new translation" "dialogue" to "Find and reword"
    And I click on "Save changes" "button" in the "Set new translation" "dialogue"
    Then I should see "was changed"
    And the string "searchandtranslatethewords/tool_quicktranslate" should be customised in "en"
    When I reload the page
    And I open the quick translate sidebar
    Then I should see "Find and reword"
    And I should not see "Search & Translate the words"

  Scenario: Restoring the original wording drops the customisation
    Given I turn editing mode on
    And I open the quick translate sidebar
    And I translate the string "searchandtranslatethewords/tool_quicktranslate"
    And I set the field "en" in the "Set new translation" "dialogue" to "Find and reword"
    And I click on "Save changes" "button" in the "Set new translation" "dialogue"
    And I reload the page
    And I open the quick translate sidebar
    When I translate the string "searchandtranslatethewords/tool_quicktranslate"
    And I set the field "en" in the "Set new translation" "dialogue" to "Search & Translate the words"
    And I click on "Save changes" "button" in the "Set new translation" "dialogue"
    Then the string "searchandtranslatethewords/tool_quicktranslate" should not be customised in "en"
    When I reload the page
    And I open the quick translate sidebar
    Then I should see "Search & Translate the words"
