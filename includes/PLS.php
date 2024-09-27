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

		if ( Permissions::rest_is_authorized_admin() ) {
			new RestApi();
		}

		new WPCLI();
	}
}
