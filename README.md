# ModMenuUpdater

A one-shot companion for the Kanboard plugin manager
[ModMenu](https://github.com/carmelosantana/kanboard-modmenu). It moves a ModMenu older
than 1.3.0 onto the self-updating release, then you remove it.

## Why it exists

ModMenu before 1.3.0 refuses to install over itself, and a hosted Kanboard often has no
shell or upload path to replace it by hand. ModMenu 1.3.0 updates itself. ModMenuUpdater
bridges that one step: it installs through the old ModMenu's normal Browse → Install, then
swaps in the current ModMenu.

## Four clicks

1. **Settings → ModMenu → Browse**: install **ModMenuUpdater**.
2. **Settings → ModMenu Updater**: open the page.
3. Click **Update ModMenu to &lt;version&gt;**.
4. **Settings → ModMenu → Installed → ModMenuUpdater → Remove.**

From then on ModMenu updates itself from Browse.

## What it does

| Step | Detail |
|---|---|
| Find the release | Reads ModMenu from your ModMenu directory sources (the download must be `https://`). |
| Check the archive | Uses the installed ModMenu's archive checks; the archive's single top-level folder must be `ModMenu`. |
| Stage | Extracts to `plugins/.modmenu-staging-<random>/` (dot folders are never loaded by Kanboard). |
| Verify and swap | Loads `Model/PluginSwap.php` from the staged release and uses it to verify the copy (newer version, every `.php` file parses, `php_version`, `compatible_version`) and rename it into place. |
| Keep a way back | The old copy stays in `plugins/.modmenu-previous-ModMenu/`. The new ModMenu deletes it once it has loaded, or restores it if the new version fails to load. |

It refuses to run when the installed ModMenu already updates itself, and it is admin-only
with CSRF protection on the update action.

## Requirements

- Kanboard ≥ 1.2.47, PHP ≥ 8.4
- ModMenu ≥ 1.1.0, with a writable plugins folder and the PHP zip extension

## License

MIT
