Forked from [Remoji](https://wordpress.org/plugins/remoji/).
Changes: Unicode emoji rendering, configurable and custom emoji sets, optional self-hosted emoji fonts, and privacy and security updates.

=== Remoji Unicode Fork ===

Unofficial fork of Remoji 2.6 (https://wordpress.org/plugins/remoji/, GPLv3). Changes:

* Reactions render as plain Unicode emoji (`<span>`), not bundled SVG images. The `data/emoji/` SVG folder is removed.
* Any emoji works: the `remoji_emoji_list` filter accepts `name => codepoint` (`'fire' => '1F525'`, ZWJ sequences like `'1F9D1-200D-1F4BB'`, `'U+2764'`, or the emoji character).
* "Limit Emojis" accepts any of the ~2,200 bundled shortnames (`data/emoji.map`) and sets the picker order.
* New "Custom Emojis" setting: one `name = 1F525` per line.
* Optional self-hosted emoji font: `add_filter( 'remoji_emoji_font_url', fn() => 'https://example.com/emoji-subset.woff2' );`.
* Settings, post/comment meta, and the reaction table are shared with the original, so existing reactions carry over.

Contributors: Remoji
Tags: comment, emoji, postviews, counter, views
Requires at least: 4.4
Tested up to: 7.0
Stable tag: 2.6.2
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

Reactive emoji. Allow visitors to add emoji reactions to your posts and comments. Disable comment for pages, posts.

== Description ==

Add the slack style emoji to posts, pages or comments.

= Features =

* React with emojis to any post or comment.

* Post View counter. Compatible with all cache plugins! Easy to use. Automatically exclude bots.

* Disable comment on any post type (pages, posts, attachments).

* Most Viewed widget. Recent Reacted Emoji Post widget.

* Allow guests reaction or logged-in user reaction only.

* Privacy: no outbound requests about visitors (no IP geolocation). Visitor IPs are stored only as a keyed HMAC hash, never raw, and no location data is kept. GDPR mode additionally hides reactor names.

= Post View =

1. Edit `wp-content/themes/<YOUR THEME>/index.php` or `archive.php`/`single.php`/`post.php`/`page.php`.

2. In the loop `while ( have_posts() ) {` or anywhere you want to show the views, add the following codes: `do_action( 'remoji_postview' );`.


= API =

To show postview in themes/plugins, use `do_action( 'remoji_postview', $the_post_id_to_inquire );`.


= Shortcode [views] =

Use `[views]` or `[views id="3"]` (to show the views of post ID 3) in your editor.


== Screenshots ==

1. Comment emoji style
2. Remoji Settings
3. Post View counter

== Frequently Asked Questions ==

= The reaction is not added / I see "Error happened" =

Clicking an emoji sends a request to the REST endpoint `/wp-json/remoji/v1/add`. If reactions fail, the most common causes are:

* A security plugin, firewall (WAF) or server rule blocking the WordPress REST API.
* Another plugin overriding REST routes, or the REST API being disabled.
* Aggressive page caching serving a stale nonce to logged-in users (guests are unaffected as of 2.4).

Open your browser console: if the `add` request returns `rest_no_route` (404), the route is being blocked/rewritten before it reaches the plugin — allow the `remoji/v1` REST namespace in your security plugin/firewall.

= How do I place reactions manually instead of auto-appending? =

Turn OFF "Auto-Append To Content" in the settings, then use the shortcode `[remoji]` (or `[remoji id="3"]`) or the PHP hook `do_action( 'remoji_reaction' );` where you want the reaction bar.

= Can I limit which emojis are available, or add my own? =

Use "Limit Emojis" in the settings to restrict the picker to a subset. Developers can add/replace emojis via the `remoji_emoji_list` filter.

== Changelog ==

= 2.6.2 (Unicode fork) =
* **Privacy** Removed the third-party IP geolocation lookup (www.doapi.us). The plugin no longer sends any visitor data to outside services.
* **Privacy** No location data is stored. Existing city/postcode/country data in the reaction log is erased on upgrade.
* **Privacy** Visitor IPs are always stored as a keyed HMAC-SHA256 hash (salt-based) instead of raw or unkeyed md5. Existing rows are re-keyed on upgrade.
* **Privacy** The admin Reaction Log no longer shows IPs or location; it shows the reactor's name or "Guest".
* **Security** Per-IP rate limit on the reaction endpoint (default 30 requests per minute, HTTP 429 when exceeded). Tune with the `remoji_rate_limit` and `remoji_rate_limit_window` filters.

= 2.6.1 (Unicode fork) =
* Unicode emoji rendering, open emoji set, Custom Emojis setting, emoji font filter.

= 2.6 - Jul 7, 2026 =
* 🌱 Add a per-post Remoji option to disable reactions for a single post and its comments.
* **Bugfix** Setting Max Emojis Per IP to 0 now means unlimited instead of blocking all reactions.
* **Bugfix** Harden reaction counters, Most Viewed widget defaults, and admin metabox visibility.

= 2.5 - Jul 7, 2026 =
* **Bugfix** Emoji picker layout now works with limited emoji sets of any size.
* **Bugfix** Reaction notifications now go to the correct post/comment author.
* **Bugfix** Recent Reacted widget backfills visible entries after hidden/deleted targets are skipped.
* **Security** Password-protected posts/comments no longer render or accept reactions.

= 2.4 - Jul 7, 2026 =
* 🌱 Withdraw your own reaction by clicking the same emoji again.
* 🌱 Optional email notification to the author on new reactions.
* 🌱 Show who reacted (logged-in user names) on hover.
* 🌱 `[remoji]` shortcode + `do_action( 'remoji_reaction' )` hook, with an option to disable auto-appending.
* 🌱 Limit available emojis to a subset, plus a `remoji_emoji_list` filter for custom emojis.
* 🌱 Disable reactions per post type (e.g. pages).
* 🌱 bbPress topics/replies support.
* **Bugfix** No longer renders the reaction bar (and "Error happened") inside excerpts/feeds.
* **Bugfix** Guest reactions now work on cached pages (nonce only sent for logged-in users).
* **Bugfix** Mobile-responsive emoji picker panel.
* **Bugfix** Correct text domain on all strings; clearer reaction error messages.
* **Bugfix** Admin reaction log: fixed pagination edge cases and escaped all output.
* **Security** Server-side validation of reaction targets (published post / approved comment on a public parent), plus post-type and emoji-whitelist enforcement.
* **Security** Client IP now uses REMOTE_ADDR by default and no longer trusts proxy headers unless the `remoji_trust_proxy_headers` filter is enabled — sites behind a CDN/proxy should enable it.

= 2.2 =
* Test up to latest WP.

= 2.1.1 =
* Translation fix. (@alexclassroom)

= 2.1 =
* Bypassed version check to speed up WP v6.

= 2.0 =
* Postview column in Posts list. (@inside83)

= 1.9.1 =
* Test up to WP v5.8.

= 1.9 =
* 🌱 Limit emoji amount per visitor per post.

= 1.8.1 =
* More accurate to detect IP.

= 1.8 =
* WordPress v5.5 REST compatibility.

= 1.7 =
* 🌱 Recent Reacted Emoji Post widget. (@wilcosky)
* 🌱 Most Viewed widget.

= 1.6 =
* 🌱 Disable comments on any post type (posts/pages/attachments).

= 1.5 =
* 🌱 Post view counter.

= 1.4 =
* 🌱 Reaction delete. (@wilcosky)
* 🌱 Guest Reaction Control. (@wilcosky)
* **Bugfix** Fixed repeated reactions issue when GDPR mode is ON.

= 1.3 =
* 🌱 Reaction log.

= 1.2 =
* Options to turn emoji ON/OFF on Post/Page/Comment.

= 1.1 =
* Reactive emoji for posts.

= 1.0 =
* Reactive emoji for comments.
