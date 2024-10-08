<?php

namespace NewfoldLabs\WP\Module\PLS\Utilities;

use NewfoldLabs\WP\Module\Data\Helpers\Encryption;

/**
 * Class PLSUtility
 *
 * Provides utility functions for handling license operations.
 */
class PLSUtility {

	/**
	 * Provisions a new license via the Hiive PLS API using the plugin slug.
	 * If the license is already stored (encrypted) in WordPress options, it returns the stored license.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License data or WP_Error on failure.
	 */
	public static function provision_license( $plugin_slug ) {
		$encryption = new Encryption();

		$option_name       = 'nfd_module_pls_license_data_' . $plugin_slug;
		$encrypted_license = get_option( $option_name );
		// Check if the license already exists in WordPress options
		if ( $encrypted_license ) {
			$decrypted_license = $encryption->decrypt( $encrypted_license );
			if ( $decrypted_license ) {
				// TODO: Validate the license's validity (if necessary) once the Hiive API is ready.
				return json_decode( $decrypted_license, true );
			}

			delete_option( $option_name );
			return new \WP_Error(
				'nfd_pls_error',
				__( 'Failed to decrypt the stored license.', 'wp-module-pls' )
			);
		}

		// License doesn't exist, so proceed with the API call to provision a new one
		$endpoint      = '/sites/v2/pls';
		$body          = array(
			'pluginSlug' => $plugin_slug,
		);
		$hiive_request = new HiiveUtility( $endpoint, $body, 'POST' );

		$response = $hiive_request->send_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_body = json_decode( $response, true );
		if ( isset( $response_body['license_id'], $response_body['download_url'] ) ) {
			$license_data   = array(
				'licenseId'     => $response_body['license_id'],
				'downloadUrl'   => $response_body['download_url'],
				'storageMethod' => 'wp_options',
				'storageKey'    => $option_name,
			);
			$encrypted_data = $encryption->encrypt( wp_json_encode( $license_data ) );
			if ( $encrypted_data ) {
				update_option( $option_name, $encrypted_data );
			}

			return $license_data;
		}

		// If the response format is unexpected, return an error
		return new WP_Error(
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
