# Tool Quick Custom Translate #

This tool easily helps to translate all strings on each page.

Requires Moodle 5.2 (2026042000) or later. Turn edit mode on as a site administrator to
get the sidebar; strings are stored as a local language pack under `moodledata/lang/<lang>_local/`.

Install plugin as usual. The tool is only available to site administrators.

Uninstalling the plugin leaves the customisations in place. They are an ordinary local language
pack, the same thing `admin/tool/customlang` produces, so they keep working - and keep overriding
the shipped strings - without the plugin. To undo them, either reset each string to its shipped
value before uninstalling (the tool deletes a generated file once its last customisation is gone)
or delete `moodledata/lang/*_local/` and purge caches.

## How it works ##

* `classes/collecting_string_manager.php` replaces the core string manager for the request and
  records every string the page resolves.
* `classes/output/sidebar.php` turns that list into the footer sidebar.
* `classes/external/` holds the two AJAX functions the sidebar calls.
* `classes/local/lang_store.php` is the only code that reads or writes language files; it stores
  customisations as a local language pack under `moodledata/lang/<lang>_local/`.
* `classes/local/string_id.php` validates every `identifier/component` reference.
* `classes/local/permission.php` holds every access decision.

## Testing ##

    vendor/bin/phpunit --testsuite tool_quicktranslate_testsuite
    php admin/tool/behat/cli/run.php --tags=@tool_quicktranslate

## Installing via uploaded ZIP file ##

1. Download `quicktranslate.zip` from the latest GitHub release. Do not use the
   "Download ZIP" button on the repository page: that archive's root directory is
   `moodle-tool_quicktranslate-main`, and Moodle's installer requires it to be
   `quicktranslate`, so the upload is refused with `rootdirinvalid`. To build the
   ZIP yourself from a checkout: `git archive --prefix=quicktranslate/ -o quicktranslate.zip HEAD`.
2. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
3. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
4. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/quicktranslate

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2024 Devlion <info@devlion.co>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
