<?php
/**
 * Cart handler.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_PPEC_Cart_Handler handles button display in the cart.
 */
class WC_Gateway_PPEC_Cart_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! wc_gateway_ppec()->settings->is_enabled() ) {
			return;
		}

		add_action( 'woocommerce_before_cart_totals', array( $this, 'before_cart_totals' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'display_mini_paypal_button' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_paypal_button' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( 'yes' === wc_gateway_ppec()->settings->checkout_on_single_product_enabled ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_paypal_button_product' ), 1 );
			add_action( 'wc_ajax_wc_ppec_generate_cart', array( $this, 'wc_ajax_generate_cart' ) );
		}

		add_action( 'wc_ajax_wc_ppec_update_shipping_costs', array( $this, 'wc_ajax_update_shipping_costs' ) );
		add_action( 'wc_ajax_wc_ppec_start_checkout', array( $this, 'wc_ajax_start_checkout' ) );
	}

	/**
	 * Start checkout handler when cart is loaded.
	 */
	public function before_cart_totals() {
		// If there then call start_checkout() else do nothing so page loads as normal.
		if ( ! empty( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			// Trying to prevent auto running checkout when back button is pressed from PayPal page.
			$_GET['startcheckout'] = 'false';
			woo_pp_start_checkout();
		}
	}

	/**
	 * Generates the cart for express checkout on a product level.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_generate_cart() {
		global $post;

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_generate_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();
		$product = wc_get_product( $post->ID );

		if ( ! empty( $_POST['add-to-cart'] ) ) {
			$product = wc_get_product( absint( $_POST['add-to-cart'] ) );
		}

		/**
		 * If this page is single product page, we need to simulate
		 * adding the product to the cart taken account if it is a
		 * simple or variable product.
		 */
		if ( $product ) {
			$qty     = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );

			if ( $product->is_type( 'variable' ) ) {
				$attributes = array_map( 'wc_clean', $_POST['attributes'] );

				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$variation_id = $product->get_matching_variation( $attributes );
				} else {
					$data_store = WC_Data_Store::load( 'product' );
					$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
				}

				WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
			} else {
				WC()->cart->add_to_cart( $product->get_id(), $qty );
			}

			WC()->cart->calculate_totals();
		}

		wp_send_json( new stdClass() );
	}

	/**
	 * Update shipping costs. Trigger this update before checking out to have total costs up to date.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_update_shipping_costs() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_update_shipping_costs_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		WC()->cart->calculate_totals();

		wp_send_json( new stdClass() );
	}

	/**
	 * Set Express Checkout and return token in response.
	 *
	 * @since 1.6.0
	 */
	public function wc_ajax_start_checkout() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_start_checkout_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		$checkout = wc_gateway_ppec()->checkout;
		$checkout->start_checkout_from_cart();
		wp_send_json( array( 'token' => WC()->session->paypal->token ) );
	}

	/**
	 * Display paypal button on the product page.
	 *
	 * @since 1.4.0
	 */
	public function display_paypal_button_product() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! is_product() || ! isset( $gateways['ppec_paypal'] ) ) {
			return;
		}

		$settings = wc_gateway_ppec()->settings;

		?>
		<div class="wcppec-checkout-buttons woo_pp_cart_buttons_div">
			<div id="woo_pp_ec_button"></div>
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart page.
	 */
	public function display_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}
		?>
		<div class="wcppec-checkout-buttons woo_pp_cart_buttons_div">

			<?php if ( has_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout' ) ) : ?>
				<div class="wcppec-checkout-buttons__separator">
					<?php _e( '&mdash; or &mdash;', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</div>
			<?php endif; ?>

			<div id="woo_pp_ec_button"></div>
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart widget
	 */
	public function display_mini_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}
		?>
		<div id="woo_pp_ec_button"></div>
		<?php
	}

	/**
	 * Frontend scripts
	 */
	public function enqueue_scripts() {
		$settings = wc_gateway_ppec()->settings;
		$client   = wc_gateway_ppec()->client;

		wp_enqueue_style( 'wc-gateway-ppec-frontend-cart', wc_gateway_ppec()->plugin_url . 'assets/css/wc-gateway-ppec-frontend-cart.css' );

		if ( is_cart() || is_product() ) {
			wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
			wp_enqueue_script( 'wc-gateway-ppec-frontend-in-context-checkout', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-frontend-in-context-checkout.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
			wp_localize_script( 'wc-gateway-ppec-frontend-in-context-checkout', 'wc_ppec_context',
				array(
					'env'                         => $settings->get_environment(),
					'production'                  => $settings->api_clientid,
					'sandbox'                     => $settings->sandbox_api_clientid,
					'locale'                      => $settings->get_paypal_locale(),

					'button_layout'               => is_product() ? 'horizontal' : $settings->button_layout,
					'button_size'                 => $settings->button_size,
					'button_shape'                => $settings->button_shape,
					'button_color'                => $settings->button_color,
					'button_label'                => is_product() ? 'buynow' : null,

					'paypal_credit'               => $settings->is_credit_enabled(),
					'update_shipping_costs_nonce' => wp_create_nonce( '_wc_ppec_update_shipping_costs_nonce' ),
					'ajaxurl'                     => WC_AJAX::get_endpoint( 'wc_ppec_update_shipping_costs' ),
					'start_checkout_nonce'        => wp_create_nonce( '_wc_ppec_start_checkout_nonce' ),
					'start_checkout_url'          => WC_AJAX::get_endpoint( 'wc_ppec_start_checkout' ),
				)
			);
		}
	}

	/**
	 * @deprecated
	 */
	public function loadCartDetails() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function loadOrderDetails( $order_id ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function setECParams() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}
}
