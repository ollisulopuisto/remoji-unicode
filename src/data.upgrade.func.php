<?php
/**
 * Data upgrade functions
 *
 * @since 2.5
 */
defined( 'WPINC' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- targets the plugin's own {prefix}remoji_history table; the migration is a one-time idempotent schema change.

/**
 * 2.5: add `user_id` column to the history table.
 * Needed for reaction-ownership (withdraw) and reactor-name display. Idempotent.
 *
 * @since 2.5
 */
function remoji_update_2_5() {
	global $wpdb;

	$data = \remoji\Data::get_instance();

	// On installs without the table yet, tb_create() will build it with the new schema
	if ( ! $data->tb_exist( 'history' ) ) {
		return;
	}

	$tb = $data->tb( 'history' );

	// Only add the column when it's missing
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); the value is bound via $wpdb->prepare().
	$col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$tb` LIKE %s", 'user_id' ) );
	if ( $col ) {
		return;
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
	$wpdb->query( "ALTER TABLE `$tb` ADD COLUMN `user_id` bigint(20) NOT NULL DEFAULT '0' AFTER `related_type`, ADD KEY `user_id` (`user_id`)" );
}


/**
 * 2.6.2: purge stored visitor location data and re-key stored IPs.
 * - Empties `ip_geo` (city/postcode/country etc. from the removed doapi.us lookup) on every row.
 * - Raw IPs (non-GDPR installs) are replaced by their HMAC, so existing guest ownership/cap still match.
 * - Legacy unkeyed md5 hashes (GDPR mode) are replaced by an HMAC of the md5, matched via IP::owner_keys().
 * - Anything else that isn't already an HMAC is blanked.
 * Idempotent: rows already holding a 64-hex HMAC are left alone.
 *
 * @since 2.6.2
 */
function remoji_update_2_6_2() {
	global $wpdb;

	$data = \remoji\Data::get_instance();

	if ( ! $data->tb_exist( 'history' ) ) {
		return;
	}

	$tb = $data->tb( 'history' );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
	$wpdb->query( "UPDATE `$tb` SET ip_geo = '' WHERE ip_geo <> ''" );

	// The HMAC key comes from wp_salt(), which isn't loaded yet when the upgrade runs at plugin load time
	// (pluggable.php loads after plugins). Re-key the IPs once it is.
	if ( function_exists( 'wp_salt' ) ) {
		remoji_update_2_6_2_rekey_ips();
	} else {
		add_action( 'plugins_loaded', 'remoji_update_2_6_2_rekey_ips' );
	}
}

/**
 * 2.6.2: replace stored raw IPs / legacy md5 values by keyed HMACs. See remoji_update_2_6_2().
 *
 * @since 2.6.2
 */
function remoji_update_2_6_2_rekey_ips() {
	global $wpdb;

	$data = \remoji\Data::get_instance();
	if ( ! $data->tb_exist( 'history' ) ) {
		return;
	}

	$tb = $data->tb( 'history' );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); no user-supplied values in this query.
	$ips = $wpdb->get_col( "SELECT DISTINCT ip FROM `$tb` WHERE ip <> ''" );
	if ( ! $ips ) {
		return;
	}

	foreach ( $ips as $old ) {
		if ( \remoji\IP::is_hash( $old ) ) {
			continue;
		}

		if ( filter_var( $old, FILTER_VALIDATE_IP ) ) {
			$new = \remoji\IP::hash( $old );
		} elseif ( preg_match( '/^[0-9a-f]{32}$/i', $old ) ) {
			$new = \remoji\IP::legacy_md5_hash( $old );
		} else {
			$new = '';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant (Data::tb()); values are bound via $wpdb->prepare().
		$wpdb->query( $wpdb->prepare( "UPDATE `$tb` SET ip = %s WHERE ip = %s", $new, $old ) );
	}
}
