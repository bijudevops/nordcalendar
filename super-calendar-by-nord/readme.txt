
=== Super Calendar by NORD ===
Contributors: chatgpt
Tags: booking, calendar, appointments
Requires at least: 5.2
Tested up to: 6.6
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elegant booking calendar with 1-hour slots (8:00–19:00), weekend support, monthly view, backend logs, and email notifications.

== Description ==
Use the shortcode [super_calendar] to render the application. Set the notification email under **Super Calendar** in wp-admin.

== Installation ==
1. Upload the zip via Plugins → Add New → Upload Plugin.
2. Activate, then go to Super Calendar menu.

== Changelog ==
= 1.0.0 =
* Initial release.

= 1.0.1 =
* Stronger CSS isolation (prevents theme from turning days red / oversized fonts)
* Highlighting fix with higher specificity

= 1.0.2 =
* Restored original elegant styling
* Fix: form fields now stay within container in Elementor
* Fix: selected day/time highlight reliably visible on live

= 1.0.3 =
* UI: Past days are greyed out & disabled
* UX: Time-slot highlight robust in Elementor; buttons won't be hijacked by theme forms
* Server: Prevent booking past dates
