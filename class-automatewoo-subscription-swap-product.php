<?php
namespace to51\AW_Action;

class AutomateWoo_Subscription_Swap_Product {
	public static function init() {
		add_filter( 'automatewoo/actions', array( __CLASS__, 'register_action' ) );
	}

	public static function register_action( $actions ) {
		require_once __DIR__ . '/includes/class-action-subscription-swap-product.php';

		$actions['to51_subscription_swap_product'] = Action_Subscription_Swap_Product::class;
		return $actions;
	}
}

AutomateWoo_Subscription_Swap_Product::init();
