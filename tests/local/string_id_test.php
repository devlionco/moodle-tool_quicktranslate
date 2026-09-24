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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the string reference value object.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\local\string_id::class)]
final class string_id_test extends \basic_testcase {
    /**
     * References are parsed into an identifier and a component.
     *
     * @param string $reference
     * @param string $identifier
     * @param string $component
     */
    #[DataProvider('parse_provider')]
    public function test_parse(string $reference, string $identifier, string $component): void {
        $id = string_id::parse($reference);

        $this->assertSame($identifier, $id->identifier);
        $this->assertSame($component, $id->component);
        $this->assertSame("$identifier/$component", (string) $id);
    }

    /**
     * Data for {@see test_parse()}.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function parse_provider(): array {
        return [
            'plugin string' => ['pluginname/mod_forum', 'pluginname', 'mod_forum'],
            'core string' => ['yes/core', 'yes', 'core'],
            'core as moodle' => ['yes/moodle', 'yes', 'core'],
            'trailing slash means core' => ['yes/', 'yes', 'core'],
            'no component means core' => ['yes', 'yes', 'core'],
            'identifier may contain dots' => ['privacy:metadata/core', 'privacy:metadata', 'core'],
            // Core stores every timezone this way: the component is the last segment.
            'identifier may contain slashes' => ['europe/london/core_timezones', 'europe/london', 'core_timezones'],
            'deeper identifier' => ['america/argentina/salta/core_timezones', 'america/argentina/salta', 'core_timezones'],
            'surrounding space is ignored' => [' yes / core ', 'yes', 'core'],
        ];
    }

    /**
     * References Moodle would not accept are rejected rather than sanitised.
     *
     * @param string $reference
     */
    #[DataProvider('invalid_provider')]
    public function test_parse_rejects_invalid(string $reference): void {
        $this->expectException(\invalid_parameter_exception::class);
        string_id::parse($reference);
    }

    /**
     * Data for {@see test_parse_rejects_invalid()}.
     *
     * @return array<string, array{string}>
     */
    public static function invalid_provider(): array {
        return [
            'empty' => [''],
            'component only' => ['/core'],
            // The quote is what an attacker needs to break out of the generated PHP file.
            'quote in identifier' => ["yes'] = 1; /*/core"],
            'space in identifier' => ['two words/core'],
            'identifier starting with a digit' => ['1yes/core'],
            'path traversal in component' => ['yes/../../evil'],
            'uppercase component' => ['yes/Mod_Forum'],
        ];
    }
}
