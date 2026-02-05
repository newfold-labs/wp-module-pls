<?php

namespace NewfoldLabs\WP\Module\PLS;

use NewfoldLabs\WP\Module\PLS\RestApi\Controllers\PLSController;

/**
 * REST API wpunit tests.
 *
 * @covers \NewfoldLabs\WP\Module\PLS\RestApi\Controllers\PLSController::register_routes
 */
class RestApiWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verifies that PLS REST routes are registered when rest_api_init runs.
	 *
	 * @return void
	 */
	public function test_rest_api_init_registers_pls_routes() {
		$server = rest_get_server();
		$this->assertNotNull( $server );

		// Register routes as the module does on rest_api_init.
		add_action(
			'rest_api_init',
			function () {
				$controller = new PLSController();
				$controller->register_routes();
			}
		);
		do_action( 'rest_api_init' );

		$routes = $server->get_routes();
		$this->assertArrayHasKey( '/newfold-pls/v1/license', $routes );
		$this->assertArrayHasKey( '/newfold-pls/v1/license/activate', $routes );
		$this->assertArrayHasKey( '/newfold-pls/v1/license/status', $routes );
	}
}
