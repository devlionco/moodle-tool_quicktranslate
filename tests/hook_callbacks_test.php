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

use core\hook\after_config;
use core\hook\output\before_footer_html_generation;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the plugin's entry points.
 *
 * What matters here is not the html but the decision of whether to produce any: every page an
 * administrator loads passes through these two callbacks.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\hook_callbacks::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Puts the current request in the state the sidebar requires.
     */
    private function set_up_editing_admin(): void {
        global $PAGE, $USER;

        set_config('enabled', 1, 'tool_quicktranslate');
        $this->setAdminUser();
        $USER->editing = 1;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_pagelayout('standard');
    }

    /**
     * Builds a footer hook that reports what was added to it.
     *
     * @return before_footer_html_generation
     */
    private function footer_hook(): before_footer_html_generation {
        global $PAGE;

        return new before_footer_html_generation($PAGE->get_renderer('core'));
    }

    /**
     * An administrator in edit mode gets the sidebar in the footer.
     */
    public function test_footer_adds_the_sidebar(): void {
        $this->resetAfterTest();
        $this->set_up_editing_admin();

        $hook = $this->footer_hook();
        hook_callbacks::before_footer_html_generation($hook);

        $output = $hook->get_output();
        $this->assertStringContainsString('quicktranslate_menu', $output);
        $this->assertStringContainsString('quicktranslate_search', $output);
        // The javascript only unhides the status region, never creates it.
        $this->assertStringContainsString('quicktranslate_status', $output);
    }

    /**
     * A page nobody is editing gets nothing, so the tool stays out of the way.
     */
    public function test_footer_adds_nothing_outside_edit_mode(): void {
        global $USER;
        $this->resetAfterTest();
        $this->set_up_editing_admin();
        $USER->editing = 0;

        $hook = $this->footer_hook();
        hook_callbacks::before_footer_html_generation($hook);

        $this->assertSame('', $hook->get_output());
    }

    /**
     * Collecting never starts on a request that is not rendering a page.
     *
     * after_config guards on CLI_SCRIPT because replacing the string manager discards caches a
     * CLI upgrade may keep. A test run is a CLI run, so this exercises the guard by being one.
     */
    public function test_after_config_leaves_a_cli_request_alone(): void {
        $this->resetAfterTest();
        $this->set_up_editing_admin();

        $this->assertTrue(CLI_SCRIPT, 'this test only means anything on a CLI request');

        hook_callbacks::after_config(new after_config());

        $this->assertNotInstanceOf(collecting_string_manager::class, get_string_manager());
    }
}
