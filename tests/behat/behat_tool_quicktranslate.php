<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat step definitions for the quick translate tool.
 *
 * @package   tool_quicktranslate
 * @category  test
 * @copyright 2024 Devlion <info@devlion.co>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Steps and scenario isolation for the quick translate tool.
 *
 * The tool stores its customisations as generated PHP files in dataroot rather than in the
 * database, so Behat's transaction rollback does not undo them: without the hook below, a
 * scenario that renames "Yes" would leave every later scenario running against a site where
 * "Yes" is called something else.
 *
 * @package   tool_quicktranslate
 * @category  test
 * @copyright 2024 Devlion <info@devlion.co>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_quicktranslate extends behat_base {
    /**
     * Discards every local language customisation before each of this plugin's scenarios.
     *
     * @BeforeScenario @tool_quicktranslate
     */
    public function reset_language_customisations(): void {
        global $CFG;

        foreach (glob($CFG->langlocalroot . '/*_local') ?: [] as $directory) {
            fulldelete($directory);
        }

        \tool_quicktranslate\local\lang_store::purge_caches();
    }

    /**
     * Opens the sidebar and waits for the string list to be there.
     *
     * @Given I open the quick translate sidebar
     */
    public function i_open_the_in_place_translate_sidebar(): void {
        $this->execute('behat_general::i_click_on', [
            get_string('togglemenu', 'tool_quicktranslate'), 'button',
        ]);

        // The rows are rendered on the client when the panel opens, so wait for one of them.
        $this->ensure_element_is_visible('.quicktranslate-item', 'css_element');
    }

    /**
     * Filters the sidebar and waits for the filter to have been applied.
     *
     * The search box is debounced, so asserting straight after typing is a race. The clear
     * button is only revealed by the debounced handler, which makes it a reliable signal that
     * the list has been filtered.
     *
     * @When I search for :query in the quick translate sidebar
     * @param string $query
     */
    public function i_search_for_in_the_in_place_translate_sidebar(string $query): void {
        $this->execute('behat_forms::i_set_the_field_to', [
            get_string('search', 'tool_quicktranslate'), $query,
        ]);

        $this->ensure_element_is_visible('#quicktranslate_clear_search_btn', 'css_element');
    }

    /**
     * Opens the translation modal for one string.
     *
     * @When I translate the string :reference
     * @param string $reference String reference as identifier/component.
     */
    public function i_translate_the_string(string $reference): void {
        $this->execute('behat_general::i_click_on', [
            ".quicktranslate-item[data-id='{$reference}']", 'css_element',
        ]);

        $this->ensure_element_is_visible('#translates_form', 'css_element');
    }

    /**
     * Checks whether a string has a stored customisation, without going through the caches.
     *
     * @Then the string :reference should be customised in :lang
     * @param string $reference String reference as identifier/component.
     * @param string $lang Language code.
     */
    public function the_string_should_be_customised_in(string $reference, string $lang): void {
        if ($this->stored_customisation($reference, $lang) === null) {
            throw new ExpectationException(
                "The string {$reference} has no stored customisation in {$lang}",
                $this->getSession()
            );
        }
    }

    /**
     * Checks a string has fallen back to the value shipped by the language pack.
     *
     * @Then the string :reference should not be customised in :lang
     * @param string $reference String reference as identifier/component.
     * @param string $lang Language code.
     */
    public function the_string_should_not_be_customised_in(string $reference, string $lang): void {
        $stored = $this->stored_customisation($reference, $lang);
        if ($stored !== null) {
            throw new ExpectationException(
                "The string {$reference} is still customised in {$lang} as '{$stored}'",
                $this->getSession()
            );
        }
    }

    /**
     * Returns the stored customisation of a string, or null when there is none.
     *
     * @param string $reference String reference as identifier/component.
     * @param string $lang Language code.
     * @return string|null
     */
    protected function stored_customisation(string $reference, string $lang): ?string {
        $id = \tool_quicktranslate\local\string_id::parse($reference);
        $stored = (new \tool_quicktranslate\local\lang_store())->get_customised($id->component, $lang);

        return $stored[$id->identifier] ?? null;
    }
}
