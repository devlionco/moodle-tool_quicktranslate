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
use tool_quicktranslate\local\permission;
use tool_quicktranslate\output\sidebar;

/**
 * Hook callbacks for tool_quicktranslate.
 *
 * Glue only: every decision belongs to the classes these callbacks delegate to.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Starts collecting the strings this page resolves.
     *
     * @param after_config $hook
     */
    public static function after_config(after_config $hook): void {
        // Replacing the string manager throws away caches that CLI upgrades and cron may keep.
        if (CLI_SCRIPT || AJAX_SCRIPT || during_initial_install()) {
            return;
        }

        if (permission::should_collect()) {
            collecting_string_manager::install();
        }
    }

    /**
     * Adds the sidebar to the page footer.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $OUTPUT;

        if (!permission::can_show_sidebar()) {
            return;
        }

        $hook->add_html($OUTPUT->render(sidebar::create()));
    }
}
