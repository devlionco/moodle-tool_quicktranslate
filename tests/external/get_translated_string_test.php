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

use PHPUnit\Framework\Attributes\CoversClass;
use core_external\external_api;
use tool_quicktranslate\local\lang_store;
use tool_quicktranslate\local\string_id;

/**
 * Tests for tool_quicktranslate_get_translated_string.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\external\get_translated_string::class)]
final class get_translated_string_test extends \core_external\tests\externallib_testcase {

    /**
     * Calls the function and validates the result against its own declared structure.
     *
     * @param string $query
     * @return array
     */
    private function call(string $query): array {
        return external_api::clean_returnvalue(
            get_translated_string::execute_returns(),
            get_translated_string::execute($query)
        );
    }

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'tool_quicktranslate');
    }

    /**
     * Every language entry carries a name a human can read, not only its code.
     */
    public function test_execute_returns_language_names(): void {
        $result = $this->call('yes/core');

        $this->assertNotEmpty($result['text']);
        foreach ($result['text'] as $entry) {
            $this->assertArrayHasKey('langname', $entry);
            $this->assertNotSame('', $entry['langname']);
            // The name is what labels the field in the form; the bare code is not enough.
            $this->assertNotSame($entry['lang'], $entry['langname']);
        }
    }

    /**
     * The reference is echoed back in its normalised form, with one entry per installed language.
     */
    public function test_execute(): void {
        $result = $this->call('yes/core');

        $this->assertSame('yes', $result['identifier']);
        $this->assertSame('core', $result['component']);
        $this->assertSame(current_language(), $result['currentlang']);

        $langs = array_column($result['text'], 'lang');
        // Only 'en' is guaranteed to be installed, so never assert on the number of languages.
        $this->assertContains('en', $langs);
        $this->assertSame(
            array_keys(get_string_manager()->get_list_of_translations()),
            $langs
        );
    }

    /**
     * The value in use and the shipped value are reported separately.
     */
    public function test_execute_reports_customisation_and_original(): void {
        $id = new string_id('yes', 'core');
        $original = get_string_manager()->get_string('yes', 'core', null, 'en');

        (new lang_store())->set($id, 'en', 'Aye');
        lang_store::purge_caches();

        $english = $this->english_entry($this->call('yes/core'));
        $this->assertSame('Aye', $english['string']);
        $this->assertSame($original, $english['original']);
    }

    /**
     * A reference that could never be written back is rejected as a parameter error.
     */
    public function test_execute_rejects_an_invalid_reference(): void {
        $this->expectException(\invalid_parameter_exception::class);
        get_translated_string::execute("yes'] = 1; /*/core");
    }

    /**
     * A user without the capability is refused.
     */
    public function test_execute_requires_the_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        get_translated_string::execute('yes/core');
    }

    /**
     * Returns the entry describing the English value.
     *
     * @param array $result
     * @return array{lang: string, string: string, original: string}
     */
    private function english_entry(array $result): array {
        foreach ($result['text'] as $entry) {
            if ($entry['lang'] === 'en') {
                return $entry;
            }
        }

        $this->fail('The result has no entry for English');
    }
}
