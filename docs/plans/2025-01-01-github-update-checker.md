# GitHub Update Checker Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable WordPress update notifications and one-click upgrades from GitHub Releases for the MWE EtchWP Enhancements plugin.

**Architecture:** Integrate the YahnisElsts/plugin-update-checker library (v5.6) to check GitHub Releases for new versions. The library hooks into WordPress's native update system, providing familiar UX. Updates are triggered when a new GitHub Release is created with a version tag higher than the installed version.

**Tech Stack:** PHP 8.1+, WordPress 5.9+, plugin-update-checker v5.6

---

## Task 1: Download and Add Plugin Update Checker Library

**Files:**
- Create: `vendor/plugin-update-checker/` (entire library directory)

**Step 1: Download the library**

Run:
```bash
cd /Users/marco/Websites/mwe-WP-Plugins/bgpos-etch/mwe-etchwp-enhancements
curl -L https://github.com/YahnisElsts/plugin-update-checker/releases/download/v5.6/plugin-update-checker-5.6.zip -o /tmp/puc.zip
unzip /tmp/puc.zip -d vendor/
rm /tmp/puc.zip
```

Expected: Directory `vendor/plugin-update-checker/` exists with library files.

**Step 2: Verify library structure**

Run:
```bash
ls vendor/plugin-update-checker/
```

Expected: Contains `plugin-update-checker.php`, `Puc/` directory, etc.

**Step 3: Commit**

```bash
git add vendor/plugin-update-checker/
git commit -m "Add plugin-update-checker library v5.6 for GitHub updates"
```

---

## Task 2: Create GitHub Update Checker Class

**Files:**
- Create: `includes/class-github-updater.php`

**Step 1: Create the updater class file**

Create `includes/class-github-updater.php`:

```php
<?php
/**
 * GitHub Updater Class
 *
 * Handles automatic updates from GitHub releases.
 *
 * @package    MWE_EtchWP_Enhancements
 * @subpackage MWE_EtchWP_Enhancements/Includes
 * @author     Marco Michely <email@michelyweb.de>
 * @copyright  2025 Marco Michely
 * @license    GPL-3.0-or-later
 * @link       https://www.michelyweb.de
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace MWE\EtchWP_Enhancements;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the plugin update checker library.
require_once MWE_ETCHWP_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * GitHub Updater class.
 *
 * Integrates with GitHub releases to provide automatic plugin updates.
 *
 * @since 1.2.0
 */
class GitHub_Updater {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.2.0
	 * @var GitHub_Updater|null
	 */
	private static $instance = null;

	/**
	 * The update checker instance.
	 *
	 * @since 1.2.0
	 * @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|null
	 */
	private $update_checker = null;

	/**
	 * GitHub repository URL.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	private const GITHUB_REPO = 'https://github.com/Jehu/mwe-etchwp-enhancements';

	/**
	 * Main GitHub_Updater Instance.
	 *
	 * Ensures only one instance of GitHub_Updater is loaded or can be loaded.
	 *
	 * @since  1.2.0
	 * @return GitHub_Updater Main instance.
	 */
	public static function get_instance(): GitHub_Updater {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.
	}

	/**
	 * Initialize the updater.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	public function init(): void {
		$this->setup_update_checker();
	}

	/**
	 * Setup the plugin update checker.
	 *
	 * @since  1.2.0
	 * @return void
	 */
	private function setup_update_checker(): void {
		$this->update_checker = PucFactory::buildUpdateChecker(
			self::GITHUB_REPO,
			MWE_ETCHWP_PLUGIN_FILE,
			'mwe-etchwp-enhancements'
		);

		// Set the branch that contains the stable release.
		$this->update_checker->setBranch( 'main' );

		// Use GitHub releases for updates.
		$this->update_checker->getVcsApi()->enableReleaseAssets();
	}

	/**
	 * Get the update checker instance.
	 *
	 * Useful for debugging or extending functionality.
	 *
	 * @since  1.2.0
	 * @return \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker|null
	 */
	public function get_update_checker() {
		return $this->update_checker;
	}
}
```

**Step 2: Verify file created**

Run:
```bash
cat includes/class-github-updater.php | head -20
```

Expected: Shows the file header with proper namespace and class definition.

**Step 3: Commit**

```bash
git add includes/class-github-updater.php
git commit -m "Add GitHub_Updater class for automatic updates from releases"
```

---

## Task 3: Initialize GitHub Updater in Plugin Class

**Files:**
- Modify: `includes/class-plugin.php:46-78` (add property)
- Modify: `includes/class-plugin.php:157-182` (add initialization)

**Step 1: Add property to Plugin class**

In `includes/class-plugin.php`, after line 78 (after `$focus_editor_ui` property), add:

```php
	/**
	 * GitHub Updater instance.
	 *
	 * @since 1.2.0
	 * @var GitHub_Updater|null
	 */
	private $github_updater = null;
```

**Step 2: Initialize in init_features()**

In `includes/class-plugin.php`, at the end of the `init_features()` method (before the closing brace), add:

```php
		// Initialize GitHub Updater for automatic updates.
		$this->github_updater = GitHub_Updater::get_instance();
		$this->github_updater->init();
```

**Step 3: Verify changes**

Run:
```bash
grep -n "github_updater" includes/class-plugin.php
```

Expected: Shows property declaration and initialization lines.

**Step 4: Commit**

```bash
git add includes/class-plugin.php
git commit -m "Initialize GitHub Updater in Plugin class"
```

---

## Task 4: Update Plugin Version and Changelog

**Files:**
- Modify: `mwe-etchwp-enhancements.php:14,37` (version headers)
- Modify: `readme.txt:7,93-96` (stable tag and changelog)

**Step 1: Update version in main plugin file**

In `mwe-etchwp-enhancements.php`:
- Line 14: Change `Version: 1.1.0` to `Version: 1.2.0`
- Line 37: Change `define( 'MWE_ETCHWP_VERSION', '1.1.0' );` to `define( 'MWE_ETCHWP_VERSION', '1.2.0' );`

**Step 2: Update readme.txt**

In `readme.txt`:
- Line 7: Change `Stable tag: 1.1.0` to `Stable tag: 1.2.0`
- After line 93 (before `= 1.1.0 =`), add:

```
= 1.2.0 =
* Added: Automatic updates from GitHub Releases
* Added: One-click upgrade support via WordPress admin
```

**Step 3: Verify version consistency**

Run:
```bash
grep -E "Version|MWE_ETCHWP_VERSION|Stable tag" mwe-etchwp-enhancements.php readme.txt
```

Expected: All show `1.2.0`.

**Step 4: Commit**

```bash
git add mwe-etchwp-enhancements.php readme.txt
git commit -m "Bump version to 1.2.0 with GitHub update support"
```

---

## Task 5: Update .gitattributes for Release Zip

**Files:**
- Create or Modify: `.gitattributes`

**Step 1: Create/update .gitattributes**

Create `.gitattributes` to exclude dev files from release zips:

```
# Exclude development files from release archives
/.gitattributes export-ignore
/.gitignore export-ignore
/tests export-ignore
/docs export-ignore
/phpcs.xml export-ignore
/phpunit.xml export-ignore
/composer.json export-ignore
/composer.lock export-ignore
/.editorconfig export-ignore
```

**Step 2: Verify file**

Run:
```bash
cat .gitattributes
```

Expected: Shows exclusion rules.

**Step 3: Commit**

```bash
git add .gitattributes
git commit -m "Add .gitattributes to exclude dev files from release zips"
```

---

## Task 6: Test Update Checker Initialization

**Step 1: Verify PHP syntax**

Run:
```bash
php -l includes/class-github-updater.php
php -l includes/class-plugin.php
php -l mwe-etchwp-enhancements.php
```

Expected: `No syntax errors detected` for all files.

**Step 2: Verify autoloader works with new class**

The autoloader in `mwe-etchwp-enhancements.php` should automatically load `class-github-updater.php` when `GitHub_Updater` class is referenced. Verify the filename matches the pattern:

Run:
```bash
ls includes/class-github-updater.php
```

Expected: File exists (confirms naming convention is correct).

---

## Task 7: Final Verification and Push

**Step 1: Review all changes**

Run:
```bash
git log --oneline -5
git status
```

Expected: 5 commits, clean working tree.

**Step 2: Push to GitHub**

Run:
```bash
git push -u origin feature/github-update-checker
```

Expected: Branch pushed successfully.

---

## Post-Implementation: Creating a Release

After merging to `main`, create a GitHub Release to trigger updates:

1. Go to https://github.com/Jehu/mwe-etchwp-enhancements/releases/new
2. Create tag: `v1.2.0` (or `1.2.0`)
3. Title: `Version 1.2.0`
4. Description: Changelog from readme.txt
5. Attach ZIP file (optional - library can create from source)
6. Publish release

The update checker will automatically detect new releases and notify WordPress installations.

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Add plugin-update-checker library | `vendor/plugin-update-checker/` |
| 2 | Create GitHub_Updater class | `includes/class-github-updater.php` |
| 3 | Initialize updater in Plugin class | `includes/class-plugin.php` |
| 4 | Update version to 1.2.0 | `mwe-etchwp-enhancements.php`, `readme.txt` |
| 5 | Add .gitattributes for clean releases | `.gitattributes` |
| 6 | Syntax verification | - |
| 7 | Push to GitHub | - |
