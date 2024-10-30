<?php
/**
 * Plugin Name: Kitchen.co - Gravity Forms Add-on
 * Description: Kitchen.co add-on for Gravity Forms automatically creates project conversations in your Kitchen workspace after а Gravity Form submissions on your website.
 * Version: 1.0.1
 * Author: kitchen.co
 * Author URI: https://kitchen.co
 * Text Domain: gfkitchen
 */

define( 'GF_KITCHEN_ADDON_VERSION', '1.0.1' );

add_action( 'gform_loaded', [ 'GFKitchenAddOnBootstrap', 'load' ], 5 );

/**
 * Class GFKitchenAddOnBootstrap
 *
 * Handles the loading of the Kitchen Add-On and registers with the Add-On Framework.
 */
class GFKitchenAddOnBootstrap {

	/**
	 * If the Feed Add-On Framework exists, Kitchen Add-On is loaded.
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-kitchen-addon.php' );

		GFAddOn::register( GFKitchenAddOn::class );

	}

}

/**
 * Returns an instance of the GFKitchenAddOn class
 *
 * @return GFKitchenAddOn
 *@see    GFKitchenAddOn::get_instance()
 *
 */
function gf_kitchen_addon() {

	return GFKitchenAddOn::get_instance();

}
