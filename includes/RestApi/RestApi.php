<?php

namespace NewfoldLabs\WP\Module\PLS\RestApi;

/**
 * Class RestApi
 *
 * Handles the registration of custom REST API routes
 */
final class RestApi {
	/**
	 * An array of controller class names that manage REST API routes.
	 *
	 * @var array $controllers
	 */
	protected $controllers = array(
		'NewfoldLabs\\WP\\Module\\PLS\\RestApi\\Controller\\PLSController',
	);

	/**
	 * Register the custom REST API routes.
	 */
	public function register_routes() {
		foreach ( $this->controllers as $controller ) {
			$instance = new $controller();
			$instance->register_routes();
		}
	}
}
