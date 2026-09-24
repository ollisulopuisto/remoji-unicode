<?php
/**
 * Plugin Name:       Remoji Unicode Fork
 * Description:       Fork of Remoji 2.6 (Post/Comment Reaction and Enhancement) that renders reactions as plain Unicode emoji instead of bundled SVG images, and accepts any shortname => codepoint mapping. Deactivate the original Remoji before activating this fork; both share the same settings and reaction data.
 * Version:           2.6.3
 * Update URI:        false
 * Author:            PHP Fan
 * License:           GPLv3
 * License URI:       http://www.gnu.org/licenses/gpl.html
 * Text Domain:       remoji
 * Domain Path:       /lang
 *
 * Copyright (C) 2022-2026 WPDO
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
defined( 'WPINC' ) || exit;

if ( defined( 'REMOJI_V' ) ) {
	return;
}

define( 'REMOJI_V', '2.6.3' ); // Unicode fork of Remoji 2.6

! defined( 'REMOJI_DIR' ) && define( 'REMOJI_DIR', __DIR__ . '/' ); // Full absolute path '/usr/local/***/wp-content/plugins/remoji/' or MU

! defined( 'REMOJI_URL' ) && define( 'REMOJI_URL', plugin_dir_url( __FILE__ ) ); // Full URL path '//example.com/wp-content/plugins/remoji/'

require_once REMOJI_DIR . 'autoload.php';

\remoji\Core::get_instance();
