<?php

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\PLS\PLS;

use function NewfoldLabs\WP\ModuleLoader\register;

if ( function_exists( 'add_action' ) ) {
	add_action(
		'plugins_loaded',
		function () {
			register(
				array(
					'name'     => 'wp-module-pls',
					'label'    => __( 'wp-module-pls', 'wp-module-pls' ),
					'callback' => function ( Container $container ) {
						new PLS( $container );
					},
					'isActive' => true,
					'isHidden' => true,
				)
			);
		}
	);

}
