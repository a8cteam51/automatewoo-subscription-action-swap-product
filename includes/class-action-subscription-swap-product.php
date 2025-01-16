<?php

namespace to51\AW_Action;

use AutomateWoo\Action;
use AutomateWoo\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Action_Subscription_Swap_Product extends Action {

	/**
	 * The data items required by the action.
	 *
	 * @var array
	 */
	public $required_data_items = array( 'subscription' );

	/**
	 * Flag to define whether variable products should be included in search results for the
	 * product select field.
	 *
	 * @var bool
	 */
	protected $allow_variable_products = true;

	/**
	 * Method to load the action's fields.
	 */
	public function load_fields() {
		$this->add_field( $this->get_swap_out_product_select_field() );
		$this->add_field( $this->get_swap_in_product_select_field() );

		$recalculate = new Fields\Checkbox();
		$recalculate->set_name( 'recalculate_totals' );
		$recalculate->set_title( __( 'Recalculate Totals?', 'automatewoo' ) );
		$recalculate->set_description( __( 'Update subscription totals, shipping, taxes, and fees to reflect the new product prices.', 'automatewoo' ) );
		$this->add_field( $recalculate );
	}

	/**
	 * Get a product selection field for "swap out" product
	 */
	protected function get_swap_out_product_select_field() {
		$swap_out = new Fields\Product();
		$swap_out->set_name( 'product_to_swap_out' );
		$swap_out->set_title( __( 'Product to Swap Out', 'automatewoo' ) );
		$swap_out->set_required();
		$swap_out->set_allow_variations( true );
		$swap_out->set_allow_variable( $this->allow_variable_products );

		return $swap_out;
	}

	/**
	 * Get a product selection field for "swap in" product
	 */
	protected function get_swap_in_product_select_field() {
		$swap_in = new Fields\Product();
		$swap_in->set_name( 'product_to_swap_in' );
		$swap_in->set_title( __( 'Product to Swap In', 'automatewoo' ) );
		$swap_in->set_required();
		$swap_in->set_allow_variations( true );
		$swap_in->set_allow_variable( $this->allow_variable_products );

		return $swap_in;
	}

	/**
	 * Method to set the action's admin properties.
	 *
	 * Admin properties include: title, group and description.
	 */
	protected function load_admin_details() {
		$this->title       = __( 'Swap Product', 'automatewoo' );
		$this->group       = __( 'Subscription', 'automatewoo' );
		$this->description = __( 'Swap one product for another on existing subscription line items. This will not change quantity of line item, or subscription schedule. Prices will only be recalculated if the "Recalculate Totals?" checkbox is checked.', 'automatewoo' );
	}

	/**
	 * Run the action.
	 */
	public function run() {
		/** @var \WC_Subscription $subscription */
		$subscription = $this->workflow->data_layer()->get_subscription();

		$swap_out_product_id = $this->get_option( 'product_to_swap_out' );
		$swap_in_product_id  = $this->get_option( 'product_to_swap_in' );

		if ( ! $subscription || ! $swap_out_product_id || ! $swap_in_product_id ) {
			$this->workflow->log( 'Missing required data: subscription or product IDs not set' );
			return;
		}

		$swap_out_product = wc_get_product( $swap_out_product_id );
		$swap_in_product  = wc_get_product( $swap_in_product_id );

		if ( ! $swap_out_product || ! $swap_in_product ) {
			$this->workflow->log( 'Failed to load products for swap' );
			return;
		}

		$did_update = false;

		foreach ( $subscription->get_items( array( 'line_item', 'shipping' ) ) as $item_id => $item ) {

			if ( 'shipping' === $item->get_type() ) {
				wc_delete_order_item_meta( $item_id, 'Items', '', true );
				$item->save();
				continue;
			}

			if ( $item->get_product_id() === $swap_out_product->get_id() || $item->get_variation_id() === $swap_out_product->get_id() ) {

				$did_update = true;

				$quantity = $item->get_quantity();
				$subscription->remove_item( $item_id );

				$add_product_args = array();

				if ( ! $this->get_option( 'recalculate_totals' ) ) {
					$add_product_args['subtotal'] = $item->get_subtotal();
					$add_product_args['total']    = $item->get_total();
				}

				$subscription->add_product( $swap_in_product, $quantity, $add_product_args );

				if ( $this->get_option( 'recalculate_totals' ) ) {
					$subscription->calculate_totals();
				}

				$subscription->save();
			}
		}

		if ( $did_update ) {
			$this->add_subscription_note( $subscription, $swap_out_product, $swap_in_product );

			if ( $this->get_option( 'recalculate_totals' ) ) {
				// Track if Route protection was present
				$had_route_protection = false;
    
				foreach ( $subscription->get_items( 'fee' ) as $item_id => $item ) {
					if ( 'Route Shipping Protection' === $item->get_name() ) {
						$had_route_protection = true;
					}
					$subscription->remove_item( $item_id );
				}

				if ( ! WC()->cart ) {
					WC()->frontend_includes();
					WC()->session = new \WC_Session_Handler();
					WC()->session->init();
					WC()->customer = new \WC_Customer( get_current_user_id(), true );
					WC()->cart     = new \WC_Cart();
				}

				WC()->cart->empty_cart();

				foreach ( $subscription->get_items() as $item ) {
					$product = $item->get_product();
					if ( ! $product ) {
						$this->workflow->log( 'Failed to get product for subscription item' );
						continue;
					}

					$variation_id   = $item->get_variation_id();
					$variation_data = array();

					if ( $variation_id ) {
						foreach ( $item->get_meta_data() as $meta ) {
							if ( strpos( $meta->key, 'pa_' ) === 0 ) {
								$variation_data[ $meta->key ] = $meta->value;
							}
						}
					}

					$cart_item_key = WC()->cart->add_to_cart(
						$item->get_product_id(),
						$item->get_quantity(),
						$variation_id,
						$variation_data
					);

					if ( ! $cart_item_key ) {
						$this->workflow->log( 'Failed to add item to cart for recalculation' );
					}
				}

				WC()->customer->set_shipping_country( $subscription->get_shipping_country() );
				WC()->customer->set_shipping_state( $subscription->get_shipping_state() );
				WC()->customer->set_shipping_postcode( $subscription->get_shipping_postcode() );
				WC()->customer->set_shipping_address_1( $subscription->get_shipping_address_1() );
				WC()->customer->set_shipping_address_2( $subscription->get_shipping_address_2() );
				WC()->customer->set_shipping_city( $subscription->get_shipping_city() );
				WC()->customer->set_shipping_company( $subscription->get_shipping_company() );
				WC()->customer->set_shipping_first_name( $subscription->get_shipping_first_name() );
				WC()->customer->set_shipping_last_name( $subscription->get_shipping_last_name() );
				WC()->customer->set_billing_email( $subscription->get_billing_email() );
				WC()->customer->set_shipping_phone( $subscription->get_shipping_phone() );

				WC()->cart->calculate_totals();

				$flavorcloud = \Novos_Extended\Integrations\FlavorCloud::init();
				$result      = $flavorcloud->add_shipping_fees( WC()->cart );

				if ( is_wp_error( $result ) ) {
					$this->workflow->log( 'FlavorCloud API error: ' . $result->get_error_message() );
				}

				foreach ( WC()->cart->get_fees() as $fee ) {
					$item = new \WC_Order_Item_Fee();
					$item->set_props(
						array(
							'name'      => $fee->name,
							'tax_class' => $fee->tax_class,
							'amount'    => $fee->amount,
							'total'     => $fee->total,
							'total_tax' => $fee->tax,
						)
					);
					$subscription->add_item( $item );
				}

				$cart_subtotal = WC()->cart->get_subtotal();

				if ( class_exists( '\Routeapp_Public' ) && $had_route_protection ) {
					$route = new \Routeapp_Public( 'routeapp', ROUTEAPP_VERSION );

					$cart_total = round( $route->get_cart_subtotal_with_only_shippable_items( WC()->cart ), 2 );
					$cart_ref   = WC()->cart->get_cart_hash();
					$currency   = get_woocommerce_currency();
					$cart_items = $route->get_cart_shippable_items( WC()->cart );

					try {
						$route_insurance_quote = $route->routeapp_get_quote_from_api( $cart_ref, $cart_total, $currency, $cart_items );
					} catch ( \Exception $e ) {
						$this->workflow->log( 'Route API error: ' . $e->getMessage() );
					}

					if ( isset( $route_insurance_quote->premium->amount ) &&
						isset( $route_insurance_quote->payment_responsible->type ) &&
						'paid_by_customer' === $route_insurance_quote->payment_responsible->type ) {

						$protection_amount = $route_insurance_quote->premium->amount;

						if ( $protection_amount > 0 ) {
							$item = new \WC_Order_Item_Fee();
							$item->set_props(
								array(
									'name'      => $route->routeapp_get_insurance_label(),
									'tax_class' => $route->routeapp_get_taxable_class(),
									'amount'    => $protection_amount,
									'total'     => $protection_amount,
									'total_tax' => 0,
								)
							);
							$subscription->add_item( $item );

							if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
								&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
								$subscription->update_meta_data( '_routeapp_route_charge', $protection_amount );
								$subscription->update_meta_data( '_routeapp_route_protection', true );
							} else {
								update_post_meta( $subscription->get_id(), '_routeapp_route_charge', $protection_amount );
								update_post_meta( $subscription->get_id(), '_routeapp_route_protection', true );
							}
						}
					}
				}

				$subscription->calculate_totals( true );
				$subscription->save();

				WC()->cart->empty_cart();
			}
		}
	}

	/**
	 * Adds a note to the given subscription indicating the product swap
	 *
	 * @param \WC_Subscription $subscription
	 * @param \WC_Product $swap_out_product
	 * @param \WC_Product $swap_in_product
	 */
	protected function add_subscription_note( $subscription, $swap_out_product, $swap_in_product ) {
		$note = sprintf(
			// translators: 1: name of the product to swap out, 2: ID of the product to swap out, 3: name of the product to swap in, 4: ID of the product to swap in
			__( 'AutomateWoo - Swapped out "%1$s" (ID: %2$s) for "%3$s" (ID: %4$s)', 'automatewoo' ),
			$swap_out_product->get_name(),
			$swap_out_product->get_id(),
			$swap_in_product->get_name(),
			$swap_in_product->get_id()
		);
		$subscription->add_order_note( $note );
	}
}
