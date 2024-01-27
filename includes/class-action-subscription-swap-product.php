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

		foreach ( $subscription->get_items() as $line_item_id => $line_item ) {
			$item_product = $line_item->get_product();

			if ( $item_product && $item_product->get_id() === $swap_out_product->get_id() ) {
				$quantity               = $line_item->get_quantity();
				$line_item_total        = $line_item->get_total();
				$line_item_subtotal     = $line_item->get_subtotal();
				$line_item_total_tax    = $line_item->get_total_tax();
				$line_item_subtotal_tax = $line_item->get_subtotal_tax();

				// Remove the "swap out" product line item using the line item id
				$subscription->remove_item( $line_item_id );

				// Add the "swap in" product to the subscription retaining the pricing of the original "swap out" product
				$new_item_id = $subscription->add_product(
					$swap_in_product,
					$quantity,
					array(
						'totals' => array(
							'subtotal'     => $line_item_subtotal,
							'total'        => $line_item_total,
							'subtotal_tax' => $line_item_subtotal_tax,
							'total_tax'    => $line_item_total_tax,
						),
					)
				);
				// Get the new line item
				$new_line_item = $subscription->get_item( $new_item_id );

				// Copy tax data
				$new_line_item->set_taxes(
					array(
						'total'    => $line_item->get_taxes()['total'],
						'subtotal' => $line_item->get_taxes()['subtotal'],
					)
				);

				// Copy meta data (this includes coupon data, etc.)
				foreach ( $line_item->get_meta_data() as $meta_data ) {
					$new_line_item->add_meta_data( $meta_data->key, $meta_data->value );
				}

				// Save the item changes and the subscription
				$new_line_item->save();
				$subscription->calculate_totals();
				$subscription->save();

				// Add a note to the subscription indicating the swap
				$this->add_subscription_note( $subscription, $swap_out_product, $swap_in_product );
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
