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

namespace tool_quicktranslate;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the collecting string manager.
 *
 * The class is built directly rather than installed through the hook: installing it means writing
 * to $CFG->config_php_settings, which happens during after_config, long before PHPUnit boots.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\collecting_string_manager::class)]
final class collecting_string_manager_test extends \advanced_testcase {
    /**
     * Builds a manager configured exactly the way get_string_manager() would build it.
     *
     * @return collecting_string_manager
     */
    private function create_manager(): collecting_string_manager {
        global $CFG;

        return new collecting_string_manager($CFG->langotherroot, $CFG->langlocalroot, ['en'], []);
    }

    /**
     * Resolved strings are still returned, and are recorded on the way out.
     */
    public function test_get_string_collects(): void {
        $manager = $this->create_manager();

        $this->assertSame('Yes', $manager->get_string('yes', 'core'));
        $this->assertSame('Forum', $manager->get_string('pluginname', 'mod_forum'));

        $this->assertSame([
            ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
            ['identifier' => 'pluginname', 'component' => 'mod_forum', 'text' => 'Forum'],
        ], $manager->get_collected_strings());
    }

    /**
     * A null component means core, the same as '' and 'moodle'.
     *
     * Core resolves some of its own strings that way, e.g. get_string('fullnamedisplay', null, $user)
     * in core\user::get_fullname(), so this runs on nearly every page.
     */
    public function test_null_component_is_accepted(): void {
        $manager = $this->create_manager();

        $manager->get_string('fullnamedisplay', null, (object) ['firstname' => 'A', 'lastname' => 'B']);

        $this->assertSame(
            [['identifier' => 'fullnamedisplay', 'component' => 'core', 'text' => 'A B']],
            $manager->get_collected_strings()
        );
    }

    /**
     * The same string resolved twice is offered once.
     */
    public function test_repeated_strings_are_collected_once(): void {
        $manager = $this->create_manager();

        $manager->get_string('yes', 'core');
        $manager->get_string('yes', 'core');

        $this->assertCount(1, $manager->get_collected_strings());
    }

    /**
     * langconfig holds machine settings, not prose: editing them breaks the site.
     */
    public function test_langconfig_is_never_offered(): void {
        $manager = $this->create_manager();

        $manager->get_string('thisdirection', 'langconfig');
        $manager->get_string('locale', 'langconfig');
        $manager->get_string('strftimedate', 'langconfig');
        $manager->get_string('yes', 'core');

        $this->assertSame([
            ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
        ], $manager->get_collected_strings());
    }

    /**
     * A string the tool could never write back does not break the page it appears on.
     */
    public function test_unwritable_identifier_is_skipped_silently(): void {
        $manager = $this->create_manager();

        $this->assertDebuggingNotCalled();
        $manager->get_string('1invalid identifier', 'core');

        $this->assertSame([], $manager->get_collected_strings());
        // The missing string itself is reported by core, which is the correct owner of that error.
        $this->assertDebuggingCalled();
    }

    /**
     * A site that has configured its own string manager keeps it.
     */
    public function test_install_does_not_claim_a_foreign_string_manager(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->config_php_settings['customstringmanager'] = \core_string_manager_standard::class;

        collecting_string_manager::install();

        $this->assertSame(
            \core_string_manager_standard::class,
            $CFG->config_php_settings['customstringmanager']
        );
        $this->assertDebuggingCalled();
    }
}
