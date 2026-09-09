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

/**
 * The plugin's only boundary to language files.
 *
 * Stores customisations as Moodle expects: a generated PHP file per component under
 * <langlocalroot>/<lang>_local/. $root is a constructor argument so tests can use a temp dir.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lang_store {

    /** @var int Seconds to wait for another request to finish writing the same file. */
    protected const LOCK_TIMEOUT = 10;

    /** @var string Directory holding the <lang>_local subdirectories. */
    protected string $root;

    /**
     * @param string|null $root Language root; defaults to $CFG->langlocalroot.
     */
    public function __construct(?string $root = null) {
        global $CFG;

        $this->root = $root ?? $CFG->langlocalroot;
    }

    /**
     * Returns the language file name a component's strings belong in.
     *
     * Core lives in moodle.php; every plugin uses its frankenstyle name.
     *
     * @param string $component Normalised frankenstyle component name.
     * @return string
     */
    public static function filename_for(string $component): string {
        return $component === 'core' ? 'moodle.php' : $component . '.php';
    }

    /**
     * Stores one customisation, preserving every customisation already in the file.
     *
     * @param string_id $id String being customised.
     * @param string $lang Installed language code.
     * @param string $value New value.
     * @throws \moodle_exception When the language is not installed or the file cannot be written.
     */
    public function set(string_id $id, string $lang, string $value): void {
        $lang = self::validate_lang($lang);

        $this->modify($id, $lang, function (array $strings) use ($id, $value): array {
            $strings[$id->identifier] = $value;

            return $strings;
        });
    }

    /**
     * Removes one customisation, so the string falls back to the shipped language pack.
     *
     * @param string_id $id String to reset.
     * @param string $lang Installed language code.
     * @throws \moodle_exception When the language is not installed or the file cannot be written.
     */
    public function remove(string_id $id, string $lang): void {
        $lang = self::validate_lang($lang);

        $this->modify($id, $lang, function (array $strings) use ($id): ?array {
            if (!array_key_exists($id->identifier, $strings)) {
                return null;
            }
            unset($strings[$id->identifier]);

            return $strings;
        });
    }

    /**
     * Reads, changes and rewrites one component's customisations while holding a lock.
     *
     * Without the lock, two administrators saving different strings of one component lose an edit,
     * and silently: rename() keeps the file itself consistent, so nothing looks broken.
     *
     * @param string_id $id String being changed; only its component picks the file.
     * @param string $lang Validated language code.
     * @param callable $change Receives the stored strings, returns the new set, or null to do nothing.
     * @throws \moodle_exception When the lock cannot be taken or the file cannot be written.
     */
    protected function modify(string_id $id, string $lang, callable $change): void {
        $filepath = $this->file_path($id->component, $lang);

        $lock = \core\lock\lock_config::get_lock_factory('tool_quicktranslate')
            ->get_lock($id->component . '_' . $lang, self::LOCK_TIMEOUT);
        if (!$lock) {
            throw new \moodle_exception('cannotwritelangfile', 'tool_quicktranslate', '', s($filepath));
        }

        try {
            $strings = $change($this->get_customised($id->component, $lang));
            if ($strings !== null) {
                $this->write($id->component, $lang, $strings);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns every customisation currently stored for a component in one language.
     *
     * @param string $component Normalised frankenstyle component name.
     * @param string $lang Language code.
     * @return array<string, string> Identifier => customised value.
     */
    public function get_customised(string $component, string $lang): array {
        $filepath = $this->file_path($component, $lang);

        $string = [];
        if (file_exists($filepath)) {
            include($filepath);
        }

        return $string;
    }

    /**
     * Returns the value shipped by the language pack, ignoring any customisation.
     *
     * $disablelocal makes core bypass its string cache entirely, so this always reads from disk -
     * once per language per call. Acceptable: only an administrator, only on a click.
     *
     * @param string_id $id String to look up.
     * @param string $lang Language code.
     * @return string The original value, or '' when the language pack has no such string.
     */
    public function get_original(string_id $id, string $lang): string {
        $strings = get_string_manager()->load_component_strings($id->component, $lang, false, true);

        return $strings[$id->identifier] ?? '';
    }

    /**
     * Drops the caches holding stale copies of what was just written.
     *
     * Templates matter too: mustache resolves {{#str}} at compile time. Both revision counters
     * live in the config table, so a cluster is covered - except the opcache_reset() inside
     * reset_caches(), because the generated file is PHP and each node caches its own copy.
     */
    public static function purge_caches(): void {
        get_string_manager()->reset_caches();
        template_reset_all_caches();
    }

    /**
     * Checks a language code is a language pack actually installed on this site.
     *
     * @param string $lang
     * @return string The validated code.
     * @throws \moodle_exception When the language pack is not installed.
     */
    protected static function validate_lang(string $lang): string {
        if ($lang === '' || $lang !== clean_param($lang, PARAM_LANG)) {
            throw new \moodle_exception('langnotinstalled', 'tool_quicktranslate', '', s($lang));
        }

        return $lang;
    }

    /**
     * Returns the absolute path of a component's local language file.
     *
     * @param string $component
     * @param string $lang
     * @return string
     */
    protected function file_path(string $component, string $lang): string {
        return $this->root . '/' . $lang . '_local/' . self::filename_for($component);
    }

    /**
     * Rewrites a component's local language file with the given set of strings.
     *
     * @param string $component
     * @param string $lang
     * @param array<string, string> $strings
     * @throws \moodle_exception When the file cannot be written.
     */
    protected function write(string $component, string $lang, array $strings): void {
        global $CFG;

        $filepath = $this->file_path($component, $lang);

        // Nothing left to customise. Moodle includes this file on every request while it exists,
        // so remove it rather than leave a header with no strings under it.
        if (!$strings) {
            if (file_exists($filepath) && !unlink($filepath)) {
                throw new \moodle_exception('cannotwritelangfile', 'tool_quicktranslate', '', s($filepath));
            }

            return;
        }

        check_dir_exists(dirname($filepath));

        $body = $this->file_header($component);
        foreach ($strings as $identifier => $value) {
            // Both halves go through var_export() so nothing in either can break out of the PHP.
            $body .= '$string[' . var_export((string) $identifier, true) . '] = '
                . var_export((string) $value, true) . ";\n";
        }

        // Write and rename, so a failure part way through cannot leave broken PHP behind: this
        // file is included on every request once it exists.
        $tempfile = $filepath . '.' . getmypid() . '.tmp';
        if (file_put_contents($tempfile, $body) === false || !rename($tempfile, $filepath)) {
            @unlink($tempfile);
            throw new \moodle_exception('cannotwritelangfile', 'tool_quicktranslate', '', s($filepath));
        }

        chmod($filepath, $CFG->filepermissions);
    }

    /**
     * Returns the licence and documentation header of a generated language file.
     *
     * @param string $component
     * @return string
     */
    protected function file_header(string $component): string {
        [$package, $subpackage] = \core_component::normalize_component($component);
        $packageinfo = " * @package    $package";
        if ($subpackage !== null) {
            $packageinfo .= "\n * @subpackage $subpackage";
        }

        return <<<EOF
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
         * Local language customisations, generated by tool_quicktranslate. Do not edit by hand.
         *
        $packageinfo
         * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
         */

        defined('MOODLE_INTERNAL') || die();


        EOF;
    }
}
