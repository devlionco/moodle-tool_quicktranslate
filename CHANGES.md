# Changelog

## 3.0

* Rewritten for Moodle 5.2: namespaced classes, hooks via `db/hooks.php`, one external
  function class per web service.
* Language files are written through a single boundary, under a lock, and removed once their
  last customisation is reset.
* The string list is shipped as data and rendered when the panel is first opened, instead of
  being emitted into every page.
* The save confirmation and the close button live in the panel itself.
* Capabilities are the only access gate, matching core `tool_customlang`.
* Fixed: strings whose identifier contains a slash (every timezone) could not be translated.
* Added unit and Behat tests.
