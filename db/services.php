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
 * External functions provided by tool_quicktranslate.
 *
 * Declared ajax => true and deliberately not published to any service: a token holder has no
 * business writing language files.
 *
 * @package    tool_quicktranslate
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_quicktranslate_get_translated_string' => [
        'classname' => tool_quicktranslate\external\get_translated_string::class,
        'description' => 'Get the value of one string in every installed language',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'tool/quicktranslate:view',
    ],
    'tool_quicktranslate_set_translated_string' => [
        'classname' => tool_quicktranslate\external\set_translated_string::class,
        'description' => 'Store the translations of one string',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/quicktranslate:edit',
    ],
];
