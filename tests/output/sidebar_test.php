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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the sidebar renderable.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\tool_quicktranslate\output\sidebar::class)]
final class sidebar_test extends \advanced_testcase {

    /**
     * Returns the identifier/component references the sidebar would offer.
     *
     * @param sidebar $sidebar
     * @return string[]
     */
    private function offered_references(sidebar $sidebar): array {
        global $PAGE;

        $context = $sidebar->export_for_template($PAGE->get_renderer('core'));

        return array_map(
            fn(array $string): string => $string['identifier'] . '/' . $string['component'],
            $context['langstrings']
        );
    }

    /**
     * The strings handed to the sidebar are offered for translation.
     */
    public function test_export_offers_the_collected_strings(): void {
        $sidebar = new sidebar([
            ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
            ['identifier' => 'pluginname', 'component' => 'mod_forum', 'text' => 'Forum'],
        ]);

        $references = $this->offered_references($sidebar);

        $this->assertContains('yes/core', $references);
        $this->assertContains('pluginname/mod_forum', $references);
    }

    /**
     * A string appearing in both lists is offered once.
     */
    public function test_export_does_not_duplicate(): void {
        $sidebar = new sidebar([
            ['identifier' => 'search', 'component' => 'tool_quicktranslate', 'text' => 'Search...'],
        ]);

        $references = $this->offered_references($sidebar);

        $this->assertCount(1, array_keys($references, 'search/tool_quicktranslate', true));
    }

    /**
     * The renderable renders through its template without the plugin needing a renderer class.
     */
    public function test_renders(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');

        // $OUTPUT is still the bootstrap renderer until the page has a theme, so ask $PAGE.
        $html = $PAGE->get_renderer('core')->render(new sidebar([
            ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
        ]));

        // Only the shell renders server side; rows are built from the json on first open.
        $this->assertStringContainsString('id="quicktranslate_menu"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('id="quicktranslate_status"', $html);

        // Closing lives in the panel header. As a repositioned floating toggle it ended up
        // outside the panel, and below 992px the full-width panel painted over it.
        $this->assertStringContainsString('id="quicktranslate_close"', $html);
        $this->assertMatchesRegularExpression(
            '~id="quicktranslate_menu_toggler".*?</button>~s',
            $html,
            'the toggler should still be rendered'
        );
        $this->assertStringNotContainsString('fa-xmark" aria-hidden="true"></i>
    <span', $html, 'the toggler should no longer double as the close button');
        $this->assertStringContainsString('<ul class="list-group" id="quicktranslate_strings_list"></ul>', $html);

        // The strings ride along as data rather than as several hundred list rows.
        $this->assertStringContainsString('id="quicktranslate_data"', $html);
        $this->assertStringContainsString('"identifier":"yes"', $html);
        $this->assertStringNotContainsString('data-id="yes/core"', $html);
    }

    /**
     * The rows template turns that same data into the markup the javascript relies on.
     */
    public function test_items_template_renders_the_rows(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');

        $html = $PAGE->get_renderer('core')->render_from_template('tool_quicktranslate/sidebar_items', [
            'langstrings' => [
                ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
            ],
        ]);

        $this->assertStringContainsString('data-id="yes/core"', $html);
        $this->assertStringContainsString('class="translate"', $html);
        $this->assertStringContainsString('quicktranslate-item', $html);
    }

    /**
     * Outside a request that collected strings, the sidebar is simply empty of page strings.
     */
    public function test_create_without_a_collecting_string_manager(): void {
        $references = $this->offered_references(sidebar::create());

        $this->assertContains('search/tool_quicktranslate', $references);
        $this->assertNotContains('yes/core', $references);
    }

    /**
     * Every string the sidebar template resolves itself is also offered for translation.
     *
     * Those strings reach the list only through the hand-maintained sidebar::OWN_STRINGS, so add
     * one to the template, forget the constant, and the tool stops translating its own interface.
     */
    public function test_own_strings_covers_the_whole_sidebar_template(): void {
        $template = file_get_contents(
            \core_component::get_plugin_directory('tool', 'quicktranslate') . '/templates/sidebar.mustache'
        );
        // Both forms: "{{#str}}x, component{{/str}}" and the core shorthand "{{#str}}x{{/str}}".
        preg_match_all(
            '~\{\{#str\}\}\s*([a-z_]+)\s*(?:,\s*([a-z_]+)\s*)?\{\{/str\}\}~',
            $template,
            $matches,
            PREG_SET_ORDER
        );
        $this->assertNotEmpty($matches, 'the template should resolve some strings');

        $offered = $this->offered_references(new sidebar([]));
        foreach ($matches as $match) {
            $reference = $match[1] . '/' . ($match[2] ?? 'core');
            $this->assertContains(
                $reference,
                $offered,
                "the sidebar template resolves '$reference' but sidebar::OWN_STRINGS omits it"
            );
        }
    }

    /**
     * The strings travel with the page as json, and survive a string containing markup.
     */
    public function test_export_ships_the_strings_as_json(): void {
        global $PAGE;

        $sidebar = new sidebar([
            ['identifier' => 'yes', 'component' => 'core', 'text' => 'Yes'],
            ['identifier' => 'nasty', 'component' => 'core', 'text' => '</script><b>&x</b>'],
        ]);

        $context = $sidebar->export_for_template($PAGE->get_renderer('core'));

        $this->assertArrayHasKey('stringsjson', $context);

        // No raw angle bracket, so nothing here can be read as markup. Checking '</script>'
        // alone would pass without JSON_HEX_TAG, because PHP escapes '/' by itself.
        $this->assertStringNotContainsString('<', $context['stringsjson']);
        $this->assertStringNotContainsString('>', $context['stringsjson']);

        $decoded = json_decode($context['stringsjson'], true);
        $this->assertSame($context['langstrings'], $decoded, 'the json must carry the same list');

        $texts = array_column($decoded, 'text', 'identifier');
        $this->assertSame('Yes', $texts['yes']);
        $this->assertSame('</script><b>&x</b>', $texts['nasty'], 'escaping must survive a round trip');
    }
}
