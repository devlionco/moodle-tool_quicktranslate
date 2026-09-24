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

use tool_quicktranslate\local\string_id;

/**
 * A string manager that remembers which strings the current page asked for.
 *
 * Moodle offers exactly one extension point: the customstringmanager entry of
 * $CFG->config_php_settings, which {@see install()} claims from the after_config hook. The
 * collection lives on the instance rather than in $CFG, so a test can build one and inspect it.
 *
 * @package    tool_quicktranslate
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collecting_string_manager extends \core_string_manager_standard {
    /**
     * Components never offered for translation.
     *
     * langconfig holds machine-readable settings - locale, text direction, strftime formats.
     * Editing them breaks the site rather than translating it.
     *
     * @var string[]
     */
    protected const IGNORED_COMPONENTS = ['langconfig', 'core_langconfig'];

    /** @var array<string, array{identifier: string, component: string, text: string}> */
    protected array $collected = [];

    /**
     * Makes this class the active string manager for the rest of the request.
     *
     * Must run during after_config, before anything has resolved a string.
     */
    public static function install(): void {
        global $CFG;

        // A site that configured its own manager had no other option either. Claiming it would
        // break that site's strings for the whole request, so step aside and say so.
        $configured = $CFG->config_php_settings['customstringmanager'] ?? null;
        if ($configured !== null && $configured !== self::class) {
            debugging(
                'tool_quicktranslate did not start collecting: $CFG->config_php_settings'
                    . "['customstringmanager'] is already set to " . $configured,
                DEBUG_DEVELOPER
            );

            return;
        }

        $CFG->config_php_settings['customstringmanager'] = self::class;

        // Discard the manager built during early setup so the replacement takes over.
        get_string_manager(true);
    }

    /**
     * Returns the requested string, recording it on the way out.
     *
     * @param string $identifier The identifier of the string to search for.
     * @param string|null $component The component the string belongs to; null and '' mean core.
     * @param string|object|array|null $a Placeholder values for the string.
     * @param string|null $lang Language code; null means the current language.
     * @return string The resolved string.
     */
    public function get_string($identifier, $component = '', $a = null, $lang = null) {
        $result = parent::get_string($identifier, $component, $a, $lang);

        // Core resolves some of its own strings with a null component, e.g.
        // get_string('fullnamedisplay', null, $user).
        $this->collect((string) $identifier, (string) $component, (string) $result);

        return $result;
    }

    /**
     * Returns every translatable string resolved so far, in the order the page asked for them.
     *
     * @return array<int, array{identifier: string, component: string, text: string}>
     */
    public function get_collected_strings(): array {
        return array_values($this->collected);
    }

    /**
     * Records one resolved string, unless it is not something a human should be editing.
     *
     * @param string $identifier
     * @param string $component
     * @param string $text The resolved value, with placeholders already substituted.
     */
    protected function collect(string $identifier, string $component, string $text): void {
        try {
            $id = new string_id($identifier, $component);
        } catch (\invalid_parameter_exception) {
            // Not something this tool could write back; ignoring beats failing the page.
            return;
        }

        if (in_array($id->component, self::IGNORED_COMPONENTS, true)) {
            return;
        }

        $this->collected[(string) $id] = [
            'identifier' => $id->identifier,
            'component' => $id->component,
            'text' => $text,
        ];
    }
}
