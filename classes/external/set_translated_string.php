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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_quicktranslate\local\lang_store;
use tool_quicktranslate\local\permission;
use tool_quicktranslate\local\string_id;

/**
 * External function tool_quicktranslate_set_translated_string.
 *
 * @package    tool_quicktranslate
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_translated_string extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_STRINGID, 'String reference as identifier/component'),
            'translations' => new external_multiple_structure(new external_single_structure([
                // PARAM_LANG rejects a code with no pack here, naming it; validate_lang() checks
                // again anyway.
                'lang' => new external_value(PARAM_LANG, 'Language code'),
                'string' => new external_value(PARAM_RAW, 'Value to use for that language'),
            ]), 'Values submitted for each language'),
        ]);
    }

    /**
     * Stores the submitted values, keeping the local language pack as small as possible.
     *
     * A value equal to the shipped one is not a customisation, so it removes rather than writes.
     * Otherwise saving one word would freeze that string against every future Moodle upgrade,
     * because a local pack always wins over the shipped one.
     *
     * @param string $reference String reference as identifier/component.
     * @param array $translations List of ['lang' => ..., 'string' => ...] entries.
     * @return array
     */
    public static function execute(string $reference, array $translations): array {
        [
            'reference' => $reference,
            'translations' => $translations,
        ] = self::validate_parameters(self::execute_parameters(), [
            'reference' => $reference,
            'translations' => $translations,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        permission::require_access(permission::CAP_EDIT, $context);

        $id = string_id::parse($reference);
        $store = new lang_store();

        $stored = 0;
        $removed = 0;
        foreach ($translations as $translation) {
            $value = trim($translation['string']);

            if ($value === trim($store->get_original($id, $translation['lang']))) {
                $store->remove($id, $translation['lang']);
                $removed++;
                continue;
            }

            $store->set($id, $translation['lang'], $value);
            $stored++;
        }

        if ($translations) {
            lang_store::purge_caches();
        }

        return ['stored' => $stored, 'removed' => $removed];
    }

    /**
     * Describes the data returned from the external function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'stored' => new external_value(PARAM_INT, 'Number of customisations written'),
            'removed' => new external_value(PARAM_INT, 'Number of customisations reset to the shipped value'),
        ]);
    }
}
