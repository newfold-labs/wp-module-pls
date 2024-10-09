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
	 * If the license is already stored in the WordPress option, it returns the stored license.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License data or WP_Error on failure.
	 */
	public static function provision_license( $plugin_slug ) {
		// Retrieve the stored license data from the WordPress option.
		$option_name  = 'nfd_module_pls_license_data_' . $plugin_slug;
		$license_data = get_option( $option_name );

		// Check if the license data already exists in the options.
		// TODO: Update this to store only the license ID in the appropriate plugin option once the Hiive API is ready and can store the download URL as well.
		if ( $license_data ) {
			return json_decode( $license_data, true );
		}

		// If no license is found, proceed with an API request to provision a new license.
		$endpoint = '/sites/v2/pls/license';
		$body     = array(
			'pluginSlug' => $plugin_slug,
		);

		$hiive_request = new HiiveUtility( $endpoint, $body, 'POST' );
		$response      = $hiive_request->send_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = json_decode( $response, true );
		// Check if the response contains the necessary license ID and download URL.
		if ( isset( $response_body['license_id'], $response_body['download_url'] ) ) {
			$license_data = array(
				'licenseId'     => $response_body['license_id'],
				'downloadUrl'   => $response_body['download_url'],
				'storageMethod' => 'wp_option',
				'storageKey'    => $option_name,
			);

			update_option( $option_name, wp_json_encode( $license_data ) );

			return $license_data;
		}

		return new \WP_Error(
			'nfd_pls_error',
			__( 'Unexpected response format from the API.', 'wp-module-pls' )
		);
	}

	/**
	 * Retrieves the license status by plugin slug via the Hiive PLS API.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return string|WP_Error The license status or WP_Error on failure.
	 */
	public static function retrieve_license_status( $plugin_slug ) {
		// TODO: Replace this dummy method with an actual API call to Hiive to retrieve the license status.
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
		// TODO: Replace this dummy method with an actual API call to Hiive to activate the license.
		return array(
			'status' => 'active',
		);
	}
}
