<?php

namespace NewfoldLabs\WP\Module\PLS\Utilities;

use NewfoldLabs\WP\Module\Data\Helpers\Encryption;
use NewfoldLabs\WP\Module\PLS\Data\Providers;

/**
 * Class PLSUtility
 *
 * Provides utility functions for handling license operations.
 */
class PLSUtility {

	/**
	 * The base URL for the PLS API.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Option name for storing license storage map as an associative array in WordPress options.
	 *
	 * @var string
	 */
	private $license_storage_map_option_name = 'nfd_module_pls_license_storage_map';

	/**
	 * Constructor for PLSUtility.
	 * Initializes the base URL for the API and sets up other configurations.
	 */
	public function __construct() {
		// Define the base URL for the API if not already defined.
		if ( ! defined( 'NFD_PLS_URL' ) ) {
			define( 'NFD_PLS_URL', 'https://licensing.hiive.cloud' );
		}
		$this->base_url = constant( 'NFD_PLS_URL' );
	}

	/**
	 * Stores the license storage map with encryption.
	 *
	 * @param array $storage_map The license storage map to be encrypted and stored.
	 */
	public function store_license_storage_map( $storage_map ) {
		$encryption     = new Encryption();
		$encrypted_data = $encryption->encrypt( wp_json_encode( $storage_map ) );
		update_option( $this->license_storage_map_option_name, $encrypted_data );
	}

	/**
	 * Retrieves the license storage map with decryption.
	 *
	 * @return array|false The decrypted license storage map, or false on failure.
	 */
	public function retrieve_license_storage_map() {
		$encryption     = new Encryption();
		$encrypted_data = get_option( $this->license_storage_map_option_name );
		if ( ! $encrypted_data ) {
			return false;
		}
		$decrypted_data = $encryption->decrypt( $encrypted_data );
		if ( ! $decrypted_data ) {
			return false;
		}
		return json_decode( $decrypted_data, true );
	}

	/**
	 * Provisions a new license via the Hiive Licensing API using the plugin slug.
	 * If the license is already stored, it returns the stored data.
	 *
	 * @param string $plugin_slug The plugin slug for which the license is being provisioned.
	 * @param string $provider The provider name.
	 *
	 * @return array|WP_Error License data or WP_Error on failure.
	 */
	public function provision_license( $plugin_slug, $provider ) {
		// Retrieve the existing license storage map
		$storage_map = $this->retrieve_license_storage_map();

		// Check if a license storage map already exists for the given plugin slug
		if ( isset( $storage_map[ $plugin_slug ] ) ) {
			// Get the license ID storage name from the storage map if available
			$license_id_storage_name = isset( $storage_map[ $plugin_slug ]['licenseIdStorageName'] )
			? $storage_map[ $plugin_slug ]['licenseIdStorageName'] : null;

			// If the license ID storage name is missing, retrieve it from Providers based on the provider name
			if ( ! $license_id_storage_name ) {
				$provider_instance       = new Providers();
				$license_id_storage_name = $provider_instance->get_license_id_option_name( $provider, $plugin_slug );
			}

			// Check if the license ID exists in the WordPress options table
			$license_id = get_option( $license_id_storage_name );
			if ( $license_id ) {
				// Retrieve the activation key storage name from the storage map if available
				$activation_key_storage_name = isset( $storage_map[ $plugin_slug ]['activationKeyStorageName'] )
				? $storage_map[ $plugin_slug ]['activationKeyStorageName'] : null;

				// If the activation key storage name is missing, retrieve it from Providers
				if ( ! $activation_key_storage_name ) {
					$activation_key_storage_name = $provider_instance->get_activation_key_option_name( $provider, $plugin_slug );
				}

				// Retrieve the activation key from the WordPress options table
				$activation_key = get_option( $activation_key_storage_name );

				// If the activation key exists, check if the license is valid
				if ( $activation_key ) {
					$is_valid = $this->check_license_status( $plugin_slug, $activation_key );

					// If the license is valid, return the license data from the storage map
					if ( $is_valid ) {
						$storage_map[ $plugin_slug ]['licenseId'] = $license_id;
						return $storage_map[ $plugin_slug ];
					}
				}
			}
		}

		// If no license is found, send a request to the PLS API to provision a new license
		$endpoint = '/sites/v2/pls/license';
		$body     = array(
			'pluginSlug' => $plugin_slug,
			'providerName' => $provider,
		);

		// Send the API request to provision a new license
		$hiive_request = new HiiveUtility( $endpoint, $body, 'POST' );
		$response      = $hiive_request->send_request();

		// If the API request returns an error, return the error
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse the response from the API
		$response_body = json_decode( $response, true );

		// Check if the storage_map keys have values, else fallback to Providers class
		$provider_instance    = new Providers();
		$storage_map_response = isset( $response_body['storage_map'] ) ? $response_body['storage_map'] : array();

		$activation_key_storage_name = ! empty( $storage_map_response['activation_key'] )
		? $storage_map_response['activation_key']
		: $provider_instance->get_activation_key_option_name( $provider, $plugin_slug );

		$license_id_storage_name = ! empty( $storage_map_response['license_id'] )
		? $storage_map_response['license_id']
		: $provider_instance->get_license_id_option_name( $provider, $plugin_slug );

		$storage_method = ! empty( $storage_map_response['method'] )
		? $storage_map_response['method']
		: $provider_instance->get_storage_method( $provider );

		// Store the license ID in the WordPress options table
		update_option( $license_id_storage_name, $response_body['license_id'] );

		// Prepare the new storage map data
		$storage_map_data = array(
			'downloadUrl'              => $response_body['download_url'],
			'basename'                 => $response_body['basename'],
			'provider'                 => $provider,
			'activationKeyStorageName' => $activation_key_storage_name,
			'licenseIdStorageName'     => $license_id_storage_name,
			'storageMethod'            => $storage_method,
		);

		// Save the new storage map data
		$storage_map[ $plugin_slug ] = $storage_map_data;
		$this->store_license_storage_map( $storage_map );

		// Return the new license data
		return array(
			'licenseId'                => $response_body['license_id'],
			'downloadUrl'              => $response_body['download_url'],
			'activationKeyStorageName' => $activation_key_storage_name,
			'licenseIdStorageName'     => $license_id_storage_name,
			'storageMethod'            => $storage_method,
		);
	}

	/**
	 * Activates a license by plugin slug via the PLS API.
	 * If an activation key exists and is valid, it returns an error.
	 * Otherwise, it sends a request to the PLS API to activate the license.
	 *
	 * @param string $plugin_slug The plugin slug for which to activate the license.
	 *
	 * @return string|WP_Error Activation key or WP_Error on failure.
	 */
	public function activate_license( $plugin_slug ) {
		// Retrieve the stored storage map for the plugin.
		$storage_map = $this->retrieve_license_storage_map();

		// If no license storage map exists for the given plugin slug, return an error.
		if ( ! isset( $storage_map[ $plugin_slug ] ) ) {
			return new \WP_Error(
				'nfd_pls_error',
				__( 'No license storage map found for the specified plugin slug.', 'wp-module-pls' )
			);
		}

		// Extract the storage data for the plugin slug.
		$storage_data = $storage_map[ $plugin_slug ];

		// If the activation key storage or license ID storage is not set, retrieve it from the Providers class.
		if ( ! isset( $storage_data['activationKeyStorageName'], $storage_data['licenseIdStorageName'] ) ) {
			if ( isset( $storage_data['provider'] ) ) {
				$provider_instance                        = new Providers();
				$storage_data['activationKeyStorageName'] = $provider_instance->get_activation_key_option_name( $storage_data['provider'], $plugin_slug );
				$storage_data['licenseIdStorageName']     = $provider_instance->get_license_id_option_name( $storage_data['provider'], $plugin_slug );
			}
		}

		// Retrieve the activation key using the storage name in the storage map.
		$activation_key_storage_name = $storage_data['activationKeyStorageName'];
		$activation_key              = get_option( $activation_key_storage_name );

		// If the activation key exists, check if it's valid.
		if ( $activation_key ) {
			$is_valid = $this->check_license_status( $plugin_slug, $activation_key );
			// If the activation key is valid, return it.
			if ( $is_valid ) {
				return $activation_key;
			}
		}

		// Retrieve the license ID from the storage map.
		$license_id_storage_name = $storage_data['licenseIdStorageName'];
		$license_id              = get_option( $license_id_storage_name );

		$domain_name = get_home_url();
		$email       = get_option( 'admin_email' );
		$body        = array(
			'domain_name' => $domain_name,
			'email'       => $email,
		);

		$endpoint = "{$this->base_url}/license/{$license_id}/activate";

		// Send the request to the PLS API to activate the license.
		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode( $body ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check the response code from the API. If it's not in the success range (200-299), return an error.
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			$response_body = wp_remote_retrieve_body( $response );
			return new \WP_Error(
				'nfd_pls_error',
				__( 'API returned a non-success status code: ', 'wp-module-pls' ) . $response_code
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		// If the activation key exists in the response, store it in the appropriate option.
		if ( isset( $response_data['data']['activation_key'] ) ) {
			update_option( $activation_key_storage_name, $response_data['data']['activation_key'] );
			return $response_data['data']['activation_key'];
		}

		// If the API response does not contain the expected data, return an error.
		return new \WP_Error(
			'nfd_pls_error',
			__( 'Unexpected response format from the API.', 'wp-module-pls' )
		);
	}

	/**
	 * Checks the status of the license activation by either plugin slug or activation key.
	 * If the activation key is not provided, it attempts to retrieve it using the plugin slug
	 * from the stored license map. If the activation key does not exist, it uses the provider's
	 * method to retrieve the appropriate option name.
	 *
	 * @param string|null $plugin_slug The slug of the plugin for which to check the activation status.
	 * @param string|null $activation_key The activation key, if available.
	 *
	 * @return bool True if the activation key is valid, false otherwise.
	 */
	public function check_license_status( $plugin_slug = null, $activation_key = null ) {
		// If the activation key is not provided, try to retrieve it using the plugin slug.
		if ( ! $activation_key && $plugin_slug ) {
			// Retrieve the stored license map.
			$storage_map = $this->retrieve_license_storage_map();

			// If no license storage map is found for the provided plugin slug, return false.
			if ( ! isset( $storage_map[ $plugin_slug ] ) ) {
				return false;
			}

			// Extract the storage data for the given plugin slug.
			$storage_data = $storage_map[ $plugin_slug ];

			// Check if the activation key storage name exists in the storage data.
			if ( ! isset( $storage_data['activationKeyStorageName'] ) ) {
				// If the storage name is missing, check if the provider is available in the storage data.
				if ( isset( $storage_data['provider'] ) ) {
					// Retrieve the activation key storage name using the provider's method.
					$provider_instance                        = new Providers();
					$storage_data['activationKeyStorageName'] = $provider_instance->get_activation_key_option_name( $storage_data['provider'], $plugin_slug );
				}
			}

			// Retrieve the activation key from the stored option using the activation key storage name.
			$activation_key = get_option( $storage_data['activationKeyStorageName'] );

			// If the activation key is not found in the options, return false.
			if ( ! $activation_key ) {
				return false;
			}
		}

		// Prepare the API request to check the activation key status using the retrieved or provided activation key.
		$response = wp_remote_get(
			"{$this->base_url}/license/{$activation_key}/status",
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		// If the API request returns an error, return false.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Parse the response to check the status of the activation key.
		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );

		// Return true if the activation key is valid, otherwise return false.
		return isset( $response_data['data']['valid'] ) && true === $response_data['data']['valid'];
	}
}
