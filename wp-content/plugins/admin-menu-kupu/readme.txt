=== Admin Menu Kupu (Māori Labels) ===
Contributors: richardmorrison
Tags: admin, translation, i18n, te reo, maori, accessibility
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Overlay Māori labels onto wp-admin menus (and optionally headers) with original labels on hover. Per-user toggle, admin bar quick switch, glossary editor with import/export, and minimal accessible tooltips. No audio playback included.

== Description ==
Admin Menu Kupu overlays Māori strings on top of existing wp-admin labels without changing the site's locale or core translations. Hover a label to see its original string. Users can enable/disable the overlay for themselves via profile defaults and an admin bar toggle. Site admins control global defaults, role limits, and the glossary content.

== Features ==
* Overlay translations for the left-hand admin menu and submenu items
* Original labels on hover via title & accessible tooltip
* Per-user and global defaults
* Role limiting (apply overlay to specific roles)
* Optional overlay for common page titles/headers (off by default)
* Glossary editor + import/export
* Security: capabilities, nonces, sanitized inputs, escaped outputs
* Performance: only loads minimal assets on admin pages

== Installation ==
1. Upload the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin.
3. Visit **Settings → Admin Menu Kupu** to review settings and glossary.

== Frequently Asked Questions ==
= Does this change my site's locale? =
No. We overlay visible labels only; core translations and locale remain untouched.

= Can I add more translations? =
Yes. Use the Glossary tab to add/edit items, or import a JSON file matching the structure in `/glossary/admin-menu-kupu-glossary.json`.

= Multisite? =
Yes. Activate per-site. Options are stored per site.

== Changelog ==
= 0.6.0 =
* Master-prompt rebuild. Adds role limiting, improved glossary tools, AJAX admin-bar toggle, optional header overlay, stricter sanitization, and PHPCS rules.
