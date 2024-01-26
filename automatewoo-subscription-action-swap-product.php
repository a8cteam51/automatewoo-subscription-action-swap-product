<?php
/*
 * Plugin Name: AutomateWoo Subscription Action - Swap Product
 * Plugin URI:  https://github.com/a8cteam51/automatewoo-order-action-add-free-product
 * Description: Extends the functionality of AutomateWoo with a custom action which allows you to add a product to an order as a line item.
 * Version:     1.0.0
 * Author:      WP Special Projects
 * Author URI:  https://wpspecialprojects.wordpress.com/
 * License:     GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/class-automatewoo-subscription-swap-product.php';
