<?php

namespace NewfoldLabs\WP\Module\PLS;

use NewfoldLabs\WP\Module\PLS\RestApi\RestApi;
use NewfoldLabs\WP\Module\PLS\WPCLI\WPCLI;
use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Manages all the functionalities for the module.
 */
class PLS {
	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor for the PLS class.
	 *
	 * @param Container $container The module container.
	 */
	public function __construct( Container $container ) {
		// We're trying to avoid adding more stuff to this.
		$this->container = $container;

		\add_action( 'init', array( __CLASS__, 'load_text_domain' ), 100 );

		if ( Permissions::rest_is_authorized_admin() ) {
			new RestApi();
		}

		new WPCLI();
	}


	/**
	 * Load text domain for Module
	 *
	 * @return void
	 */
	public static function load_text_domain() {

		\load_plugin_textdomain(
			'wp-module-pls',
			false,
			NFD_PLS_DIR . '/languages'
		);

	}
}
