=== Sponsor Manager ===
Contributors: bemistech
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Booking, scheduling, and Gutenberg blocks for sponsor ads and placements.

== Description ==

Sponsor Manager stores sponsor ads as a custom post type, assigns them to placements, and serves them through the Sponsors block (horizontal or vertical).

Updates are delivered from updates.bemistech.com.

== Changelog ==

= 1.5.3 =
* Sponsor cards have no border unless a custom border is saved.
* Hover uses the theme's 2px ring instead of a larger offset shadow.
* Ad rotation pauses while the grid is hovered, focused, hidden, or reduced motion is on.

= 1.5.2 =
* Checks for updates on updates.bemistech.com and stores a license key on the Sponsor Manager License tab.
* Sponsor cards use the active theme's border radius, color, and shadow unless a custom border is saved.

= 1.5.1 =
* Ships Updater.php with the PSR-4 filename Linux hosts expect so the plugin can load.

= 1.5.0 =
* Replaces Sponsor Grid and Sponsor Sidebar with one Sponsors block. Choose horizontal or vertical and a maximum number of ads. Existing sidebar blocks still render and convert in the editor.

= 1.4.0 =
* Registers the Sponsor Ad post type, placements, and booking fields in the plugin. ACF / SCF is no longer required and can stay active for other content.

= 1.3.3 =
* Namespaced classes, thin bootstrap, and admin menu registered early so the dashboard nests correctly under Sponsor Manager.
