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
		$this->description = __( 'Swap one product for another on existing subscription line items. This will not change price or quantity of line item, or any other characteristics of the subscription.', 'automatewoo' );
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
			return; // Bail early if the subscription or products are not set.
		}

		$swap_out_product = wc_get_product( $swap_out_product_id );
		$swap_in_product  = wc_get_product( $swap_in_product_id );

		if ( ! $swap_out_product || ! $swap_in_product ) {
			return; // Bail early if product lookups fail.
		}

		$did_update = false;

		foreach ( $subscription->get_items( array( 'line_item', 'shipping' ) ) as $item_id => $item ) {

			if ( 'shipping' === $item->get_type() ) {
				// Clear the "Items" meta for the shipping line item, since that can sometimes include info from previous product.
				wc_delete_order_item_meta( $item_id, 'Items', '', true );
				$item->save();
				continue;
			}

			// Check if the item matches the product to swap out (it could be a simple product or a variation).
			if ( $item->get_product_id() === $swap_out_product->get_id() || $item->get_variation_id() === $swap_out_product->get_id() ) {

				$did_update = true;

				// Determine if $swap_in_product is a variation or a simple product.
				$swap_in_is_variation = $swap_in_product instanceof WC_Product_Variation;

				// Get the main product ID and the variation ID.
				$main_product_id = $swap_in_is_variation ? $swap_in_product->get_parent_id() : $swap_in_product->get_id();
				$variation_id    = $swap_in_is_variation ? $swap_in_product->get_id() : 0;

				// Update the order item with the new product information.
				wc_update_order_item_meta( $item_id, '_product_id', $main_product_id );
				wc_update_order_item_meta( $item_id, '_variation_id', $variation_id );

				// Update the order item name with the name of the new product or variation.
				$item->set_name( $swap_in_product->get_name() );
				$item->save();

			}
		}

		if ( $did_update ) {
			$this->add_subscription_note( $subscription, $swap_out_product, $swap_in_product );
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
