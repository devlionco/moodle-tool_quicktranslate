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

namespace tool_quicktranslate\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the plugin's access decisions.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\local\permission::class)]
final class permission_test extends \advanced_testcase {
    /**
     * Only an enabled tool and a site administrator in edit mode start the collection.
     */
    public function test_should_collect(): void {
        global $USER;
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_quicktranslate');

        $this->setUser($this->getDataGenerator()->create_user());
        $USER->editing = 1;
        $this->assertFalse(permission::should_collect(), 'a user without the capability must not see the sidebar');

        $this->setAdminUser();
        $USER->editing = 0;
        $this->assertFalse(permission::should_collect(), 'edit mode is off');

        $USER->editing = 1;
        $this->assertTrue(permission::should_collect());

        set_config('enabled', 0, 'tool_quicktranslate');
        $this->assertFalse(permission::should_collect(), 'the tool is switched off');
    }

    /**
     * The sidebar additionally needs a page core would render the "editing" body class on, and a
     * layout with room for a fixed panel.
     */
    public function test_can_show_sidebar_depends_on_the_page(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_quicktranslate');
        $this->setAdminUser();
        $USER->editing = 1;

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_pagelayout('standard');
        $this->assertTrue(permission::can_show_sidebar());

        $PAGE->set_pagelayout('embedded');
        $this->assertFalse(permission::can_show_sidebar(), 'an embedded layout has nowhere to put it');

        $USER->editing = 0;
        $PAGE->set_pagelayout('standard');
        $this->assertFalse(permission::can_show_sidebar(), 'edit mode is off');
    }

    /**
     * Without the capability the standard Moodle error is raised.
     */
    public function test_require_access_needs_capability(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('enabled', 1, 'tool_quicktranslate');

        $this->expectException(\required_capability_exception::class);
        permission::require_access(permission::CAP_VIEW, \context_system::instance());
    }

    /**
     * The capability on its own is not enough, because storing a translation generates PHP.
     */
    public function test_require_access_allows_a_granted_role(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_quicktranslate');

        $user = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($managerrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $this->assertTrue(has_capability(permission::CAP_EDIT, \context_system::instance()));

        // A role holding the capability must not be refused; an is_siteadmin() check here used
        // to make it meaningless.
        permission::require_access(permission::CAP_EDIT, \context_system::instance());
    }

    /**
     * A role granted only :view is still refused the write capability.
     */
    public function test_require_access_distinguishes_the_two_capabilities(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_quicktranslate');

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability(permission::CAP_VIEW, CAP_ALLOW, $roleid, \context_system::instance()->id);
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        permission::require_access(permission::CAP_VIEW, \context_system::instance());

        $this->expectException(\required_capability_exception::class);
        permission::require_access(permission::CAP_EDIT, \context_system::instance());
    }

    /**
     * A disabled tool refuses everyone, administrators included.
     */
    public function test_require_access_needs_enabled_plugin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 0, 'tool_quicktranslate');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/disabled/');
        permission::require_access(permission::CAP_VIEW, \context_system::instance());
    }
}
