/**
 * Cart and Checkout blocks: extensions.rwgcm.shipping_currency_notice.
 * An empty string renders nothing, so a later currency switch clears the notice.
 */
( function ( wp, wc ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.plugins.registerPlugin ) {
		return;
	}
	var registerPlugin = wp.plugins.registerPlugin;
	var el = wp.element.createElement;
	var useSelect = wp.data.useSelect;
	var Slot = wc && wc.blocksCheckout && wc.blocksCheckout.ExperimentalOrderMeta;

	function noticeText( select ) {
		var store = select( 'wc/store/cart' );
		if ( ! store || ! store.getCartData ) {
			return '';
		}
		var cart = store.getCartData();
		var ext = cart && cart.extensions && cart.extensions.rwgcm;
		var text = ext && ext.shipping_currency_notice;
		return typeof text === 'string' ? text : '';
	}

	function Notice() {
		var notice = useSelect( noticeText, [] );
		if ( ! notice ) {
			return null;
		}
		return el(
			'div',
			{
				className: 'woocommerce-info rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--block',
				role: 'status'
			},
			notice
		);
	}

	function render() {
		if ( ! Slot ) {
			return el( Notice, null );
		}
		return el( Slot, null, el( Notice, null ) );
	}

	registerPlugin( 'rwgcm-shipping-currency-notice', {
		render: render,
		scope: 'woocommerce-checkout'
	} );
	registerPlugin( 'rwgcm-shipping-currency-notice-cart', {
		render: render,
		scope: 'woocommerce-cart'
	} );
}( window.wp, window.wc ) );
