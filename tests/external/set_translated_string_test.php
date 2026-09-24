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
 * Tests for tool_quicktranslate_set_translated_string.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\external\set_translated_string::class)]
final class set_translated_string_test extends \core_external\tests\externallib_testcase {
    /** @var string The value the English language pack ships for yes/core. */
    private string $original;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'tool_quicktranslate');
        $this->original = get_string_manager()->get_string('yes', 'core', null, 'en');
    }

    /**
     * Calls the function and validates the result against its own declared structure.
     *
     * @param string $identifier
     * @param array $translations
     * @return array{stored: int, removed: int}
     */
    private function call(string $identifier, array $translations): array {
        return external_api::clean_returnvalue(
            set_translated_string::execute_returns(),
            set_translated_string::execute($identifier, $translations)
        );
    }

    /**
     * A submitted value is stored and takes effect immediately.
     */
    public function test_execute(): void {
        $result = $this->call('yes/core', [['lang' => 'en', 'string' => 'Aye']]);

        $this->assertSame(['stored' => 1, 'removed' => 0], $result);
        $this->assertSame('Aye', get_string_manager()->get_string('yes', 'core', null, 'en'));
    }

    /**
     * Surrounding whitespace is not part of a translation.
     */
    public function test_execute_trims_the_value(): void {
        $this->call('yes/core', [['lang' => 'en', 'string' => "  Aye  \n"]]);

        $this->assertSame('Aye', get_string_manager()->get_string('yes', 'core', null, 'en'));
    }

    /**
     * A value equal to the shipped one is not a customisation and is not stored.
     *
     * The modal submits every language, including the ones nobody touched. Storing those would
     * freeze them at the current release: a local pack always wins, so a Moodle upgrade could
     * never update them again.
     */
    public function test_execute_does_not_store_the_shipped_value(): void {
        $result = $this->call('yes/core', [['lang' => 'en', 'string' => $this->original]]);

        $this->assertSame(['stored' => 0, 'removed' => 1], $result);
        $this->assertSame([], (new lang_store())->get_customised('core', 'en'));
    }

    /**
     * Submitting the shipped value again resets a string that had been customised.
     */
    public function test_execute_resets_a_customised_string(): void {
        (new lang_store())->set(new string_id('yes', 'core'), 'en', 'Aye');
        lang_store::purge_caches();
        $this->assertSame('Aye', get_string_manager()->get_string('yes', 'core', null, 'en'));

        $this->call('yes/core', [['lang' => 'en', 'string' => $this->original]]);

        $this->assertSame([], (new lang_store())->get_customised('core', 'en'));
        $this->assertSame($this->original, get_string_manager()->get_string('yes', 'core', null, 'en'));
    }

    /**
     * A reference that would break out of the generated PHP is rejected before any write.
     */
    public function test_execute_rejects_an_invalid_reference(): void {
        try {
            set_translated_string::execute("yes'] = 1; system('id'); \$string['x/core", [
                ['lang' => 'en', 'string' => 'pwned'],
            ]);
            $this->fail('Expected the reference to be rejected');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('reference', $e->getMessage());
        }

        $this->assertSame([], (new lang_store())->get_customised('core', 'en'));
    }

    /**
     * A language pack that is not installed is rejected by the parameter description.
     */
    public function test_execute_rejects_an_unknown_language(): void {
        $this->expectException(\invalid_parameter_exception::class);
        set_translated_string::execute('yes/core', [['lang' => '../../evil', 'string' => 'Aye']]);
    }

    /**
     * Nothing is writable while the tool is switched off.
     */
    public function test_execute_requires_an_enabled_plugin(): void {
        set_config('enabled', 0, 'tool_quicktranslate');

        $this->expectException(\moodle_exception::class);
        set_translated_string::execute('yes/core', [['lang' => 'en', 'string' => 'Aye']]);
    }

    /**
     * A user without the edit capability is refused.
     */
    public function test_execute_requires_the_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        set_translated_string::execute('yes/core', [['lang' => 'en', 'string' => 'Aye']]);
    }

    /**
     * Holding the capability without being a site administrator is not enough.
     */
    public function test_execute_allows_a_manager(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($managerrole->id, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        // The capability is the gate. While the tool also demanded is_siteadmin(), this failed
        // and the capability shown against the role was a lie.
        $result = $this->call('yes/core', [['lang' => 'en', 'string' => 'Aye']]);

        $this->assertSame(1, $result['stored']);
        $this->assertSame(['yes' => 'Aye'], (new lang_store())->get_customised('core', 'en'));
    }
}
