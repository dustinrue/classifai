<?php

namespace Klasifai;

/**
 * Miscellaneous Helper functions to access different parts of the
 * Klasifai plugin.
 */

/**
 * Returns the Klasifai plugin's singleton instance
 *
 * @return Plugin
 */
function get_plugin() {
	return Plugin::get_instance();
}

/**
 * Returns the Klasifai plugin's stored settings in the WP options
 */
function get_plugin_settings() {
	return get_option( 'klasifai_settings' );
}

/**
 * Overwrites the Klasifai plugin's stored settings. Expected format is,
 *
 * [
	 'post_types' => [ <list of post type names> ]
	 'features' => [
		 <feature_name> => <bool>
		 <feature_threshold> => <int>
	 ],
	 'credentials' => [
		 'watson_username' => <string>
		 'watson_password' => <string>
	 ]
 * ]
 */
function set_plugin_settings( $settings ) {
	update_option( 'klasifai_settings', $settings );
}

/**
 * Resets the plugin to factory defaults.
 */
function reset_plugin_settings() {
	$settings = [
		'post_types' => [
			'post',
			'page'
		],
		'features' => [
			'category' => true,
			'category_threshold' => WATSON_CATEGORY_THRESHOLD,

			'keyword' => true,
			'keyword_threshold' => WATSON_KEYWORD_THRESHOLD,

			'concept' => false,
			'concept_threshold' => WATSON_CONCEPT_THRESHOLD,

			'entity' => false,
			'entity_threshold' => WATSON_ENTITY_THRESHOLD,
		]
	];
}

/**
 * Returns the currently configured Watson username. Lookup order is,
 *
 * - Options
 * - Constant
 *
 * @return string
 */
function get_watson_username() {
	$settings = get_plugin_settings();
	$creds    = ! empty( $settings['credentials'] ) ? $settings['credentials'] : [];

	if ( ! empty( $creds['watson_username'] ) ) {
		return $creds['watson_username'];
	} else if ( defined( 'WATSON_USERNAME' ) ) {
		return WATSON_USERNAME;
	} else {
		return '';
	}
}

/**
 * Returns the currently configured Watson username. Lookup order is,
 *
 * - Options
 * - Constant
 *
 * @return string
 */
function get_watson_password() {
	$settings = get_plugin_settings();
	$creds    = ! empty( $settings['credentials'] ) ? $settings['credentials'] : [];

	if ( ! empty( $creds['watson_password'] ) ) {
		return $creds['watson_password'];
	} else if ( defined( 'WATSON_PASSWORD' ) ) {
		return WATSON_PASSWORD;
	} else {
		return '';
	}
}

/**
 * The list of post types that get the Klasifai taxonomies. Defaults
 * to 'post'.
 *
 * return array
 */
function get_supported_post_types() {
	$klasifai_settings = get_plugin_settings();

	if ( empty( $klasifai_settings ) ) {
		$post_types = [];
	} else {
		$post_types = [];
		foreach ( $klasifai_settings['post_types'] as $post_type => $enabled ) {
			if ( ! empty( $enabled ) ) {
				$post_types[] = $post_type;
			}
		}
	}

	if ( empty( $post_types ) ) {
		$post_types = [ 'post' ];
	}

	$post_types = apply_filters( 'klasifai_post_types', $post_types );

	return $post_types;
}

/**
 * Returns a bool based on whether the specified feature is enabled
 *
 * @param string $feature category,keyword,entity,concept
 * @return bool
 */
function get_feature_enabled( $feature ) {
	$settings = get_plugin_settings();

	if ( ! empty( $settings ) && ! empty( $settings['features'] ) ) {
		if ( ! empty( $settings['features'][ $feature ] ) ) {
			return filter_var(
				$settings['features'][ $feature ], FILTER_VALIDATE_BOOLEAN
			);
		}
	}

	return false;
}

/**
 * Returns the feature threshold based on current configuration. Lookup
 * order is.
 *
 * - Option
 * - Constant
 *
 * Any results below the threshold will be ignored.
 *
 * @param string $feature The feature whose threshold to lookup
 * @return int
 */
function get_feature_threshold( $feature ) {
	$settings  = get_plugin_settings();
	$threshold = 0;

	if ( ! empty( $settings ) && ! empty( $settings['features'] ) ) {
		if ( ! empty( $settings['features'][ $feature . '_threshold' ] ) ) {
			$threshold = filter_var(
				$settings['features'][ $feature . '_threshold' ], FILTER_VALIDATE_INT
			);
		}
	}

	if ( empty( $threshold ) ) {
		$constant = 'WATSON_' . strtoupper( $feature ) . '_THRESHOLD';

		if ( defined( $constant ) ) {
			$threshold = intval( $constant );
		}
	}

	if ( ! empty( $threshold ) ) {
		return $threshold / 100;
	} else {
		return 0.7;
	}
}