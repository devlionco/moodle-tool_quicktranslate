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

/**
 * Tests for the language file store.
 *
 * Most tests point the store at a throwaway directory, so they never touch the site's language
 * packs and never need the string caches purging.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\local\lang_store::class)]
final class lang_store_test extends \advanced_testcase {

    /** @var string Throwaway language root. */
    private string $root;

    /** @var lang_store Store under test, writing into {@see self::$root}. */
    private lang_store $store;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->root = make_request_directory();
        $this->store = new lang_store($this->root);
    }

    /**
     * Reads back the raw PHP the store generated for one component and language.
     *
     * @param string $component
     * @param string $lang
     * @return array<string, string>
     */
    private function file_contents(string $component, string $lang): array {
        $string = [];
        include($this->root . '/' . $lang . '_local/' . lang_store::filename_for($component));

        return $string;
    }

    /**
     * A stored value is written to the file the language pack loader will read.
     */
    public function test_set_writes_a_loadable_file(): void {
        $this->store->set(new string_id('yes', 'core'), 'en', 'Aye');

        $this->assertSame(['yes' => 'Aye'], $this->file_contents('core', 'en'));
        $this->assertSame(['yes' => 'Aye'], $this->store->get_customised('core', 'en'));
    }

    /**
     * Storing a second string keeps the first one.
     */
    public function test_set_preserves_existing_customisations(): void {
        $this->store->set(new string_id('yes', 'core'), 'en', 'Aye');
        $this->store->set(new string_id('no', 'core'), 'en', 'Nay');
        $this->store->set(new string_id('yes', 'core'), 'en', 'Aye aye');

        $this->assertSame(['yes' => 'Aye aye', 'no' => 'Nay'], $this->file_contents('core', 'en'));
    }

    /**
     * Components and languages are kept in separate files.
     */
    public function test_set_separates_components_and_languages(): void {
        $this->store->set(new string_id('yes', 'core'), 'en', 'Aye');
        $this->store->set(new string_id('pluginname', 'mod_forum'), 'en', 'Discussions');

        $this->assertSame(['yes' => 'Aye'], $this->file_contents('core', 'en'));
        $this->assertSame(['pluginname' => 'Discussions'], $this->file_contents('mod_forum', 'en'));
    }

    /**
     * A value full of quotes is stored verbatim rather than executed.
     *
     * The generated file is included on every request, so this is the escaping that matters.
     */
    public function test_set_escapes_the_value(): void {
        $value = "It's a '; system('id'); \$string['x'] = '";
        $this->store->set(new string_id('yes', 'core'), 'en', $value);

        $this->assertSame($value, $this->file_contents('core', 'en')['yes']);
    }

    /**
     * A language pack that is not installed is refused before anything is written.
     */
    public function test_set_rejects_a_language_that_is_not_installed(): void {
        try {
            $this->store->set(new string_id('yes', 'core'), '../../evil', 'Aye');
            $this->fail('Expected the language pack to be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame('langnotinstalled', $e->errorcode);
        }

        $this->assertSame([], glob($this->root . '/*'));
    }

    /**
     * Removing a customisation leaves the other strings in the file alone.
     */
    public function test_remove(): void {
        $this->store->set(new string_id('yes', 'core'), 'en', 'Aye');
        $this->store->set(new string_id('no', 'core'), 'en', 'Nay');

        $this->store->remove(new string_id('yes', 'core'), 'en');

        $this->assertSame(['no' => 'Nay'], $this->file_contents('core', 'en'));
    }

    /**
     * Removing the last customisation takes the generated file away, rather than leaving a
     * header Moodle would include on every request.
     */
    public function test_remove_of_the_last_string_deletes_the_file(): void {
        $id = new string_id('yes', 'core');
        $filepath = $this->root . '/en_local/' . lang_store::filename_for('core');

        $this->store->set($id, 'en', 'Aye');
        $this->assertFileExists($filepath);

        $this->store->remove($id, 'en');

        $this->assertFileDoesNotExist($filepath);
        $this->assertSame([], $this->store->get_customised('core', 'en'));
    }

    /**
     * Removing something that was never customised is not an error.
     */
    public function test_remove_is_idempotent(): void {
        $this->store->remove(new string_id('yes', 'core'), 'en');

        $this->assertSame([], $this->store->get_customised('core', 'en'));
    }

    /**
     * The original value comes from the language pack, never from a customisation.
     */
    public function test_get_original_ignores_customisations(): void {
        $this->resetAfterTest();
        $id = new string_id('yes', 'core');
        $expected = get_string_manager()->get_string('yes', 'core', null, 'en');

        // This one writes into the real (test) dataroot, so the string caches must be reset.
        (new lang_store())->set($id, 'en', 'Aye');
        lang_store::purge_caches();

        $this->assertSame('Aye', get_string_manager()->get_string('yes', 'core', null, 'en'));
        $this->assertSame($expected, (new lang_store())->get_original($id, 'en'));
    }
}
