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

/**
 * Every access decision the plugin makes.
 *
 * Two separate questions: may the caller translate (a trust boundary, checked on both web
 * services), and does the sidebar belong on this page (a display condition, checked in the hooks).
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class permission {

    /** @var string Capability required to read translations. */
    public const CAP_VIEW = 'tool/quicktranslate:view';

    /** @var string Capability required to store translations. */
    public const CAP_EDIT = 'tool/quicktranslate:edit';

    /** @var string[] Layouts with nowhere to put a fixed panel. */
    protected const EXCLUDED_LAYOUTS = ['embedded', 'popup', 'print', 'maintenance', 'redirect'];

    /**
     * Whether the administrator has switched the tool on.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('tool_quicktranslate', 'enabled');
    }

    /**
     * Asserts the current user may use the tool, or throws.
     *
     * The capability is the only gate: core's tool_customlang writes these same files under the
     * same RISK_CONFIG | RISK_XSS with no is_siteadmin() on top, and adding one here only made
     * a granted capability refuse.
     *
     * @param string $capability One of the CAP_* constants.
     * @param \context $context Context the capability is checked in.
     * @throws \moodle_exception When the tool is disabled.
     * @throws \required_capability_exception When the caller may not use the tool.
     */
    public static function require_access(string $capability, \context $context): void {
        require_capability($capability, $context);

        if (!self::is_enabled()) {
            throw new \moodle_exception('plugindisabled', 'tool_quicktranslate');
        }
    }

    /**
     * Whether this request should collect the strings it resolves.
     *
     * Asked during after_config, so it cannot touch $PAGE. Deliberately the looser condition:
     * collecting needlessly costs a method call per string, collecting too late cannot be fixed.
     *
     * @return bool
     */
    public static function should_collect(): bool {
        global $USER;

        // Edit mode is free and rules out almost every request; the capability check is not.
        return !empty($USER->editing)
            && self::is_enabled()
            && has_capability(self::CAP_VIEW, \context_system::instance());
    }

    /**
     * Whether the sidebar belongs on the page being rendered.
     *
     * Must agree with core's "editing" body class, which the whole stylesheet hangs off: disagree
     * and the panel renders unstyled. So ask user_is_editing() rather than reimplement it.
     *
     * @return bool
     */
    public static function can_show_sidebar(): bool {
        global $PAGE;

        return self::should_collect()
            && $PAGE->user_is_editing()
            && !in_array($PAGE->pagelayout, self::EXCLUDED_LAYOUTS, true);
    }
}
