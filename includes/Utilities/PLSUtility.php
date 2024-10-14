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
	 * The base URL for the Hiive PLS API.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Option name for storing license data as an associative array in WordPress options.
	 *
	 * @var string
	 */
	private $license_data_option_name = 'nfd_module_pls_license_data';

	/**
	 * Constructor for PLSUtility.
	 */
	public function __construct() {
		// Define the base URL for the API if not already defined.
		if ( ! defined( 'NFD_PLS_URL' ) ) {
			define( 'NFD_PLS_URL', 'https://licensing.hiive.cloud' );
		}
		$this->base_url = constant( 'NFD_PLS_URL' );
	}

	/**
	 * Stores license data with encryption.
	 *
	 * @param array $license_data The license data to be encrypted and stored.
	 */
	public function store_license_data( $license_data ) {
		$encryption = new Encryption();

		// Encrypt the license data before storing it in WordPress options
		$encrypted_data = $encryption->encrypt( wp_json_encode( $license_data ) );

		// Save the encrypted license data to the WordPress options table
		update_option( $this->license_data_option_name, $encrypted_data );
	}

	/**
	 * Retrieves license data with decryption.
	 *
	 * @return array|false The decrypted license data, or false on failure.
	 */
	public function retrieve_license_data() {
		$encryption = new Encryption();

		// Retrieve the encrypted license data from the WordPress options table
		$encrypted_data = get_option( $this->license_data_option_name );
		if ( ! $encrypted_data ) {
			return false;
		}

		// Decrypt the license data
		$decrypted_data = $encryption->decrypt( $encrypted_data );
		if ( ! $decrypted_data ) {
			return false;
		}

		// Return the decrypted data as an associative array
		return json_decode( $decrypted_data, true );
	}

	/**
	 * Provisions a new license via the Hiive PLS API using the plugin slug.
	 * If the license is already stored in the WordPress option, it returns the stored data.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License data or WP_Error on failure.
	 */
	public function provision_license( $plugin_slug ) {
		// Retrieve existing license data for the plugin if available
		$license_data_store = $this->retrieve_license_data();
		if ( isset( $license_data_store[ $plugin_slug ] ) ) {
			return $license_data_store[ $plugin_slug ];
		}

		// If no license is found, proceed with an API request to provision a new license.
		$endpoint = '/sites/v2/pls/license';
		$body     = array(
			'pluginSlug' => $plugin_slug,
		);

		// Send the API request to provision a license
		$hiive_request = new HiiveUtility( $endpoint, $body, 'POST' );
		$response      = $hiive_request->send_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse the response from the API
		$response_body = json_decode( $response, true );
		if ( isset(
			$response_body['download_url'],
			$response_body['storage_key'],
			$response_body['storage_method'],
			$response_body['license_id'],
			$response_body['basename']
		) ) {
			$license_data = array(
				'downloadUrl'   => $response_body['download_url'],
				'storageKey'    => $response_body['storage_key'],
				'storageMethod' => $response_body['storage_method'],
				'licenseId'     => $response_body['license_id'],
				'basename'      => $response_body['basename'],
			);

			// Add the new license data to the license data store and save it
			$license_data_store[ $plugin_slug ] = $license_data;
			$this->store_license_data( $license_data_store );

			return $license_data;
		}

		return new \WP_Error(
			'nfd_pls_error',
			__( 'Unexpected response format from the API.', 'wp-module-pls' )
		);
	}

	/**
	 * Activates a license by plugin slug via the Hiive PLS API.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array|WP_Error License status or WP_Error on failure.
	 */
	public function activate_license( $plugin_slug ) {
		// Retrieve the stored license data for the plugin
		$license_data_store = $this->retrieve_license_data();
		if ( ! isset( $license_data_store[ $plugin_slug ] ) ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'No license data found for the specified plugin slug.', 'wp-module-pls' )
			);
		}

		$license_data = $license_data_store[ $plugin_slug ];
		if ( ! isset( $license_data['storageKey'], $license_data['licenseId'] ) ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'Missing storageKey or licenseId in stored license data.', 'wp-module-pls' )
			);
		}

		$storage_key = $license_data['storageKey'];
		$license_id  = $license_data['licenseId'];

		// Retrieve domain name and email from WordPress database
		$domain_name = get_home_url();
		$email       = get_option( 'admin_email' );

		// Create the body for the API request, including domain name and email
		$body     = array(
			'domain_name' => $domain_name,
			'email'       => $email,
		);
		$endpoint = "{$this->base_url}/license/{$license_id}/activate";

		// Send the request to activate the license
		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode( $body ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$response_body = wp_remote_retrieve_body( $response );
			return new \WP_Error(
				'nfd_pls_error',
				__( 'API returned a non-success status code: ', 'wp-module-pls' ) . $response_code
			);
		}

		// Parse the response to check for activation key
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );
		if ( isset( $response_data['data']['activation_key'] ) ) {
			// Store the activation key in the WordPress options table
			update_option( $storage_key, $response_data['data']['activation_key'] );

			return $response_data;
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
	public function retrieve_license_status( $plugin_slug ) {
		// Retrieve the stored license data for the plugin
		$license_data_store = $this->retrieve_license_data();

		if ( ! isset( $license_data_store[ $plugin_slug ] ) ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'No license data found for the specified plugin slug.', 'wp-module-pls' )
			);
		}

		$license_data = $license_data_store[ $plugin_slug ];
		$storage_key  = $license_data['storageKey'] ?? null;

		if ( ! $storage_key ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'Missing storageKey in stored license data.', 'wp-module-pls' )
			);
		}

		// Retrieve the license ID from the option stored under storageKey.
		$license_id = get_option( $storage_key );

		if ( ! $license_id ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'No license ID found for the specified storage key.', 'wp-module-pls' )
			);
		}

		// Send request to retrieve the license status from the API
		$endpoint = "{$this->base_url}/license/{$license_id}/status";
		$response = wp_remote_get( $endpoint, array( 'timeout' => 30 ) );

		// Check for errors in the request
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'API returned a non-success status code: ', 'wp-module-pls' ) . $response_code
			);
		}

		// Parse the response to get the license status
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		return $response_data['status'] ?? new \WP_Error(
			'nfd_pls_error',
			__( 'Unexpected response format from the API.', 'wp-module-pls' )
		);
	}
}
