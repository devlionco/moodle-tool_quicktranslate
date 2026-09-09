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

namespace tool_quicktranslate\external;

use core\lang_string;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_quicktranslate\local\lang_store;
use tool_quicktranslate\local\permission;
use tool_quicktranslate\local\string_id;

/**
 * External function tool_quicktranslate_get_translated_string.
 *
 * @package    tool_quicktranslate
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_translated_string extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_STRINGID, 'String reference as identifier/component'),
        ]);
    }

    /**
     * Returns the value in use and the shipped value of one string, in every installed language.
     *
     * @param string $reference String reference as identifier/component.
     * @return array
     */
    public static function execute(string $reference): array {
        ['reference' => $reference] = self::validate_parameters(
            self::execute_parameters(),
            ['reference' => $reference]
        );

        $context = \context_system::instance();
        self::validate_context($context);
        permission::require_access(permission::CAP_VIEW, $context);

        $id = string_id::parse($reference);
        $store = new lang_store();

        $text = [];
        // Keyed by code, valued with the display name. The code identifies the field, the name
        // labels it.
        foreach (get_string_manager()->get_list_of_translations() as $lang => $langname) {
            $text[] = [
                'lang' => $lang,
                'langname' => $langname,
                'string' => (new lang_string($id->identifier, $id->component, null, $lang))->out(),
                'original' => $store->get_original($id, $lang),
            ];
        }

        return [
            'currentlang' => current_language(),
            'identifier' => $id->identifier,
            'component' => $id->component,
            'text' => $text,
        ];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'currentlang' => new external_value(PARAM_LANG, 'Language of the current session'),
            'identifier' => new external_value(PARAM_STRINGID, 'String identifier'),
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component owning the string'),
            'text' => new external_multiple_structure(new external_single_structure([
                'lang' => new external_value(PARAM_LANG, 'Language code'),
                'langname' => new external_value(PARAM_TEXT, 'Language name as shown to the user'),
                'string' => new external_value(PARAM_RAW, 'Value in use, including any customisation'),
                'original' => new external_value(PARAM_RAW, 'Value shipped by the language pack'),
            ]), 'One entry per installed language'),
        ]);
    }
}
