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

namespace tool_quicktranslate\output;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use tool_quicktranslate\collecting_string_manager;
use tool_quicktranslate\local\string_id;

/**
 * The quick translate sidebar.
 *
 * Turns the collected strings into template context. The list is a constructor argument rather
 * than something this class fetches, so a test can render it from a handful of known strings.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sidebar implements named_templatable, renderable {
    /**
     * Strings the sidebar's own templates resolve, as identifier => component.
     *
     * These are resolved as the template renders, after the collection has been handed over, so
     * they would otherwise be the only strings on the page this tool cannot translate. The
     * component is spelled out because not all of them are ours: the close button reuses core's
     * "Close".
     *
     * @var array<string, string>
     */
    protected const OWN_STRINGS = [
        'availablelangs' => 'tool_quicktranslate',
        'clearsearch' => 'tool_quicktranslate',
        'original' => 'tool_quicktranslate',
        'refresh' => 'tool_quicktranslate',
        'search' => 'tool_quicktranslate',
        'searchandtranslatethewords' => 'tool_quicktranslate',
        'setnewtranslate' => 'tool_quicktranslate',
        'stringwasupdated' => 'tool_quicktranslate',
        'togglemenu' => 'tool_quicktranslate',
        'closebuttontitle' => 'core',
    ];

    /**
     * Creates the sidebar.
     *
     * @param array[] $strings Strings to offer for translation, each with identifier, component and text keys.
     */
    public function __construct(
        /** @var array Strings to offer for translation. */
        protected array $strings,
    ) {
    }

    /**
     * Builds a sidebar from whatever the active string manager collected on this request.
     *
     * @return self
     */
    public static function create(): self {
        $manager = get_string_manager();

        return new self($manager instanceof collecting_string_manager ? $manager->get_collected_strings() : []);
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_quicktranslate/sidebar';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $strings = [];
        foreach ($this->strings as $string) {
            $strings[$string['identifier'] . '/' . $string['component']] = $string;
        }

        foreach (self::OWN_STRINGS as $identifier => $component) {
            $id = new string_id($identifier, $component);
            $strings[(string) $id] = [
                'identifier' => $id->identifier,
                'component' => $id->component,
                'text' => get_string($identifier, $component),
            ];
        }

        $langstrings = array_values($strings);

        return [
            'langstrings' => $langstrings,
            // Rows are built by the client on first open. JSON_HEX_TAG leaves no raw angle
            // bracket, so nothing here can be read as markup inside the script element.
            'stringsjson' => json_encode(
                $langstrings,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ),
        ];
    }
}
