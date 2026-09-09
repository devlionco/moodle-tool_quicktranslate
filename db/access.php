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
 * Defines the capabilities used by Quick translate admin tool
 *
 * @package    tool_quicktranslate
 * @copyright  2021 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

        // Same grants and risk profile as core's tool/customlang:view and :edit, which write the
        // very same <lang>_local files. The capability is the whole gate; nothing adds
        // is_siteadmin() on top, which would refuse a role deliberately granted it.
        'tool/quicktranslate:view' => [
                'riskbitmask' => RISK_CONFIG,
                'captype' => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],

        'tool/quicktranslate:edit' => [
                'riskbitmask' => RISK_CONFIG | RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
];
