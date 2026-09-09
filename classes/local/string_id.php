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
 * A validated reference to a single language string.
 *
 * References travel as "identifier/component" in the markup and the web service parameters.
 * Parsing them in one place is the only guard between that input and the language files, and an
 * instance can only exist for names Moodle itself considers valid.
 *
 * @package    tool_quicktranslate
 * @copyright  2024 Devlion <info@devlion.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class string_id {

    /** @var string The string identifier, e.g. "yes". */
    public readonly string $identifier;

    /** @var string Normalised frankenstyle component, e.g. "core" or "mod_forum". */
    public readonly string $component;

    /**
     * @param string $identifier String identifier within the component.
     * @param string $component Frankenstyle component; '' and 'moodle' both mean core.
     * @throws \invalid_parameter_exception When either part is not a valid Moodle name.
     */
    public function __construct(string $identifier, string $component = 'core') {
        $identifier = trim($identifier);
        $component = trim($component);

        // Core resolves its own strings under three different names; settle on one.
        if ($component === '' || $component === 'moodle') {
            $component = 'core';
        }

        // PARAM_STRINGID permits slashes and dots, so alone it accepts 'yes/../..'. No real
        // identifier has a '..' segment.
        if ($identifier === ''
                || str_contains($identifier, '..')
                || $identifier !== clean_param($identifier, PARAM_STRINGID)) {
            throw new \invalid_parameter_exception('Invalid string identifier: ' . s($identifier));
        }
        if ($component !== clean_param($component, PARAM_COMPONENT)) {
            throw new \invalid_parameter_exception('Invalid component name: ' . s($component));
        }

        $this->identifier = $identifier;
        $this->component = $component;
    }

    /**
     * Builds an instance from the "identifier/component" form.
     *
     * The component follows the *last* slash. Identifiers may contain slashes of their own - core
     * stores every timezone as get_string('europe/london', 'core_timezones') - while a component
     * never can. Splitting on the first slash made all of those unreachable.
     *
     * @param string $reference
     * @return self
     * @throws \invalid_parameter_exception When the reference cannot be parsed.
     */
    public static function parse(string $reference): self {
        $split = strrpos($reference, '/');
        if ($split === false) {
            return new self($reference);
        }

        return new self(substr($reference, 0, $split), substr($reference, $split + 1));
    }

    /**
     * Returns the "identifier/component" form.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->identifier . '/' . $this->component;
    }
}
