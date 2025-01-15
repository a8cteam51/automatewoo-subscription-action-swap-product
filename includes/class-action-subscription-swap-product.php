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
		$recalculate->set_description( __( 'Update subscription totals to reflect the new product prices', 'automatewoo' ) );
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
		$this->description = __( 'Swap one product for another on existing subscription line items. This will not change quantity of line item, or any other characteristics of the subscription. Prices will only be recalculated if the "Recalculate Totals?" checkbox is checked.', 'automatewoo' );
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

		// Remove all fee line items before processing
		if ( $this->get_option( 'recalculate_totals' ) ) {
			foreach ( $subscription->get_items( 'fee' ) as $item_id => $item ) {
				$subscription->remove_item( $item_id );
			}
		}

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

				// Store the quantity from the original item
				$quantity = $item->get_quantity();
			
				// Remove the old item
				$subscription->remove_item($item_id);
			
				// Add the new product
				$add_product_args = array();
			
				// If we're not recalculating totals, preserve the original prices
				if ( ! $this->get_option( 'recalculate_totals' ) ) {
					$add_product_args['subtotal'] = $item->get_subtotal();
					$add_product_args['total']    = $item->get_total();
				}
			
				// Add the new product
				$subscription->add_product( $swap_in_product, $quantity, $add_product_args );
			
				// Only recalculate if the option is checked
				if ( $this->get_option( 'recalculate_totals' ) ) {
					$subscription->calculate_totals();
				}
			
				$subscription->save();

			}
		}

		if ( $did_update ) {
			$this->add_subscription_note( $subscription, $swap_out_product, $swap_in_product );

			// Recalculate and save totals if option is checked
			if ( $this->get_option( 'recalculate_totals' ) ) {
				// Clear cached calculated totals
				$subscription->get_items_to_calculate();
				
				// Trigger a full recalculation of taxes and shipping
				$subscription->calculate_taxes();
				$subscription->calculate_shipping();
				
				// Calculate all totals (with true to force a clean calculation)
				$subscription->calculate_totals( true );
				
				$subscription->save();
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
