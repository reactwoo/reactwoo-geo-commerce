<?php
/**
 * Page-cache purge adapters.
 *
 * Each adapter runs only when that plugin is active. Calling a purge function
 * that happens to exist is not enough: the plugin constant or active-plugin
 * check has to pass as well.
 *
 * LiteSpeed: actions litespeed_purge_post and litespeed_purge_url.
 * WP Rocket: rocket_clean_post, and rocket_clean_files for archive URLs.
 * W3 Total Cache: w3tc_flush_post, and w3tc_flush_url for archive URLs.
 * WP Super Cache: wpsc_delete_post_cache (post ids only; archive URLs have no API here).
 * Cloudflare APO: cloudflare_purge_by_url, cloudflare_purge_url, and
 * cloudflare_purge_everything, only when something has registered the hook.
 *
 * @package ReactWoo_Geo_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guards and calls cache-plugin purge APIs.
 */
class RWGCM_Cache_Adapters {

	/**
	 * Slugs this class knows how to purge.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array( 'litespeed', 'rocket', 'w3tc', 'supercache', 'cloudflare' );
	}

	/**
	 * Active cache plugins, in slug order.
	 *
	 * The `rwgcm_active_cache_plugins` filter may force a slug on or off
	 * (an array of slug => bool). Slugs omitted from that array use detection.
	 *
	 * @return string[]
	 */
	public static function active() {
		$out = array();
		foreach ( self::slugs() as $slug ) {
			if ( self::plugin_active( $slug ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * @param string $slug Adapter slug.
	 * @return bool
	 */
	public static function plugin_active( $slug ) {
		$forced = apply_filters( 'rwgcm_active_cache_plugins', null );
		if ( is_array( $forced ) && array_key_exists( $slug, $forced ) ) {
			return (bool) $forced[ $slug ];
		}
		switch ( $slug ) {
			case 'litespeed':
				return defined( 'LSCWP_V' ) || self::plugin_file_active( 'litespeed-cache/litespeed-cache.php' );
			case 'rocket':
				return defined( 'WP_ROCKET_VERSION' ) || self::plugin_file_active( 'wp-rocket/wp-rocket.php' );
			case 'w3tc':
				return ( defined( 'W3TC' ) && W3TC ) || defined( 'W3TC_VERSION' ) || self::plugin_file_active( 'w3-total-cache/w3-total-cache.php' );
			case 'supercache':
				return defined( 'WPCACHEHOME' ) || self::plugin_file_active( 'wp-super-cache/wp-cache.php' );
			case 'cloudflare':
				return defined( 'CLOUDFLARE_PLUGIN_DIR' ) || self::plugin_file_active( 'cloudflare/cloudflare.php' );
		}
		return false;
	}

	/**
	 * Whether this cache stores a separate copy per rwgc_country.
	 *
	 * LiteSpeed is varied when cache-safe mode is not off, because Geo Commerce
	 * registers the cookie on litespeed_vary_cookies. WP Rocket is varied when
	 * rwgc_country is already listed on rocket_cache_dynamic_cookies. W3 Total
	 * Cache is varied when that cookie appears in its config (or rwgcm_w3tc_vary_cookies).
	 * WP Super Cache and Cloudflare APO do not vary on this cookie.
	 *
	 * @param string $slug Adapter slug.
	 * @return bool
	 */
	public static function varies_by_country( $slug ) {
		$cookie = class_exists( 'RWGCM_Cache_Safe_Price', false ) ? RWGCM_Cache_Safe_Price::COOKIE : 'rwgc_country';
		if ( 'litespeed' === $slug ) {
			return class_exists( 'RWGCM_Cache_Safe_Price', false ) && RWGCM_Cache_Safe_Price::registers_litespeed_vary();
		}
		if ( 'rocket' === $slug ) {
			$cookies = apply_filters( 'rocket_cache_dynamic_cookies', array() );
			return is_array( $cookies ) && in_array( $cookie, $cookies, true );
		}
		if ( 'w3tc' === $slug ) {
			return self::w3tc_cookie_varied( $cookie );
		}
		return false;
	}

	/**
	 * @param int[] $post_ids Post ids.
	 * @return array{0: array<string, int>, 1: int[]}
	 */
	public static function purge_posts( $post_ids ) {
		$counts = self::blank_counts();
		$ids    = array();
		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || isset( $ids[ $post_id ] ) ) {
				continue;
			}
			$ids[ $post_id ] = $post_id;
			if ( self::plugin_active( 'litespeed' ) && function_exists( 'do_action' ) ) {
				do_action( 'litespeed_purge_post', $post_id );
				$counts['litespeed']++;
			}
			if ( self::plugin_active( 'rocket' ) && function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
				$counts['rocket']++;
			}
			if ( self::plugin_active( 'w3tc' ) && function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $post_id );
				$counts['w3tc']++;
			}
			if ( self::plugin_active( 'supercache' ) && function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $post_id );
				$counts['supercache']++;
			}
			if ( self::plugin_active( 'cloudflare' ) && function_exists( 'cloudflare_purge_cache' ) ) {
				cloudflare_purge_cache( $post_id );
				$counts['cloudflare']++;
			}
		}
		return array( $counts, array_values( $ids ) );
	}

	/**
	 * @param string[] $urls Absolute or root-relative URLs.
	 * @return array{0: array<string, int>, 1: string[]}
	 */
	public static function purge_urls( $urls ) {
		$counts = self::blank_counts();
		$clean  = array();
		foreach ( (array) $urls as $url ) {
			$url = self::clean_url( $url );
			if ( '' === $url || isset( $clean[ $url ] ) ) {
				continue;
			}
			$clean[ $url ] = $url;
		}
		$clean = array_values( $clean );
		if ( empty( $clean ) ) {
			return array( $counts, array() );
		}
		if ( self::plugin_active( 'litespeed' ) && function_exists( 'do_action' ) ) {
			foreach ( $clean as $url ) {
				do_action( 'litespeed_purge_url', $url );
				$counts['litespeed']++;
			}
		}
		if ( self::plugin_active( 'rocket' ) && function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $clean );
			$counts['rocket'] += count( $clean );
		}
		if ( self::plugin_active( 'w3tc' ) && function_exists( 'w3tc_flush_url' ) ) {
			foreach ( $clean as $url ) {
				w3tc_flush_url( $url );
				$counts['w3tc']++;
			}
		}
		return array( $counts, $clean );
	}

	/**
	 * Full-zone Cloudflare purge when the plugin has registered the hook.
	 *
	 * @return bool
	 */
	public static function purge_cloudflare_everything() {
		if ( ! self::plugin_active( 'cloudflare' ) || ! function_exists( 'do_action' ) || ! self::hook_available( 'cloudflare_purge_everything' ) ) {
			return false;
		}
		do_action( 'cloudflare_purge_everything' );
		return true;
	}

	/**
	 * @param string $url URL.
	 * @return bool Whether a Cloudflare hook ran.
	 */
	public static function purge_cloudflare_url( $url ) {
		$url = self::clean_url( $url );
		if ( '' === $url || ! self::plugin_active( 'cloudflare' ) || ! function_exists( 'do_action' ) ) {
			return false;
		}
		$fired = false;
		foreach ( array( 'cloudflare_purge_by_url', 'cloudflare_purge_url' ) as $hook ) {
			if ( self::hook_available( $hook ) ) {
				do_action( $hook, $url );
				$fired = true;
			}
		}
		return $fired;
	}

	/**
	 * @return array<string, int>
	 */
	public static function blank_counts() {
		return array(
			'litespeed'  => 0,
			'rocket'     => 0,
			'w3tc'       => 0,
			'supercache' => 0,
			'cloudflare' => 0,
		);
	}

	/**
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	private static function plugin_file_active( $file ) {
		return function_exists( 'is_plugin_active' ) && is_plugin_active( $file );
	}

	/**
	 * @param string $cookie Cookie name.
	 * @return bool
	 */
	private static function w3tc_cookie_varied( $cookie ) {
		$extra = apply_filters( 'rwgcm_w3tc_vary_cookies', array() );
		if ( is_array( $extra ) && in_array( $cookie, $extra, true ) ) {
			return true;
		}
		$config = get_option( 'w3tc_config', array() );
		if ( is_string( $config ) ) {
			return false !== strpos( $config, $cookie );
		}
		if ( ! is_array( $config ) ) {
			return false;
		}
		$blob = function_exists( 'wp_json_encode' ) ? wp_json_encode( $config ) : json_encode( $config );
		return is_string( $blob ) && false !== strpos( $blob, $cookie );
	}

	/**
	 * @param string $hook Hook name.
	 * @return bool
	 */
	private static function hook_available( $hook ) {
		if ( ! function_exists( 'has_action' ) ) {
			return false;
		}
		$found = has_action( $hook );
		return false !== $found && (int) $found > 0;
	}

	/**
	 * @param mixed $url Raw URL.
	 * @return string
	 */
	private static function clean_url( $url ) {
		$url = trim( (string) $url );
		if ( function_exists( 'esc_url_raw' ) ) {
			$url = (string) esc_url_raw( $url );
		}
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $url ) || ( isset( $url[0] ) && '/' === $url[0] ) ) {
			return $url;
		}
		return '';
	}
}
