<?php

namespace NewfoldLabs\WP\Module\PLS\Utilities;

/**
 * Class PLSUtility
 *
 * Provides utility functions for handling license operations.
 */
class PLSUtility {

	/**
	 * Provisions a new license via the Hiive PLS API using the plugin slug.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License status or WP_Error on failure.
	 */
	public static function provision_license( $plugin_slug ) {
		// TODO: Replace this dummy with an actual API call to Hiive for provisioning a new license.
		return array(
			'status' => 'new',
		);
	}

	/**
	 * Retrieves the license status by plugin slug via the Hiive PLS API.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return string|WP_Error License status or WP_Error on failure.
	 */
	public static function retrieve_license_status( $plugin_slug ) {
		// TODO: Replace this dummy with an actual API call to Hiive to retrieve the license status.
		$statuses      = array( 'active', 'new', 'expired', 'not_generated' );
		$random_status = $statuses[ array_rand( $statuses ) ];

		return $random_status;
	}

	/**
	 * Activates a license by plugin slug via the Hiive PLS API.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License status or WP_Error on failure.
	 */
	public static function activate_license( $plugin_slug ) {
		// TODO: Replace this dummy with an actual API call to Hiive to activate the license.
		return array(
			'status' => 'active',
		);
	}
}
