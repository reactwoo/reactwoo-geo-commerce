/**
 * Cart and Checkout blocks: extensions.rwgcm.shipping_currency_notice.
 *
 * The blockified checkout does not mount the order-meta slot, so a slot
 * fill never appears. The cart and checkout notice lists do. An empty string
 * removes the notice, including when the cart returns to the visitor currency.
 */
( function ( wp, wc ) {
	if ( ! wp || ! wp.data || ! wp.data.select || ! wp.data.dispatch || ! wp.data.subscribe ) {
		return;
	}

	var select = wp.data.select;
	var dispatch = wp.data.dispatch;
	var subscribe = wp.data.subscribe;
	var NOTICE_ID = 'rwgcm-shipping-currency-notice';
	var lastCountry = null;
	var refetching = false;
	var wasPending = false;
	var seenCartUpdate = null;

	function contexts() {
		var checkout = 'wc/checkout';
		var cart = 'wc/cart';
		var known = wc && wc.blocksCheckout && wc.blocksCheckout.noticeContexts;
		if ( known && known.CHECKOUT ) {
			checkout = known.CHECKOUT;
		}
		if ( known && known.CART ) {
			cart = known.CART;
		}
		return [ checkout, cart ];
	}

	/**
	 * Cart resource from wc/store/cart. Null when the store is not ready.
	 *
	 * @return {Object|null}
	 */
	function cartPayload() {
		var store = select( 'wc/store/cart' );
		if ( ! store ) {
			return null;
		}
		var cart = null;
		if ( store.getCartData ) {
			cart = store.getCartData();
		} else if ( store.getCart ) {
			cart = store.getCart();
		}
		return cart && typeof cart === 'object' ? cart : null;
	}

	/**
	 * extensions.rwgcm.shipping_currency_notice, or null when that object
	 * is not in this cart snapshot (do not clear a notice we cannot see).
	 *
	 * @param {Object} cart Cart resource.
	 * @return {string|null}
	 */
	function extensionNotice( cart ) {
		var ext = cart && cart.extensions && cart.extensions.rwgcm;
		if ( ! ext || typeof ext.shipping_currency_notice !== 'string' ) {
			return null;
		}
		return ext.shipping_currency_notice;
	}

	/**
	 * @param {Object} cart Cart resource.
	 * @return {string}
	 */
	function shippingCountry( cart ) {
		var address = cart.shipping_address || cart.shippingAddress || null;
		if ( ! address || typeof address !== 'object' || typeof address.country !== 'string' ) {
			return '';
		}
		return address.country;
	}

	/**
	 * Checkout block page. Its update-customer request already replaces the cart.
	 * A second GET plus receiveCart races that request and can leave the
	 * totals on loading placeholders.
	 *
	 * @return bool
	 */
	function onCheckoutBlock() {
		if ( ! document || ! document.querySelector ) {
			return false;
		}
		return !! document.querySelector( '.wc-block-checkout, .wp-block-woocommerce-checkout' );
	}

	/**
	 * True while the block is pushing an address or a coupon. Reconcile after
	 * this returns to false, not in the middle of update-customer.
	 *
	 * @return bool
	 */
	function addressUpdatePending() {
		var cartStore = select( 'wc/store/cart' );
		var checkoutStore = select( 'wc/store/checkout' );
		if ( cartStore && typeof cartStore.isCustomerDataUpdating === 'function' && cartStore.isCustomerDataUpdating() ) {
			return true;
		}
		if ( cartStore && typeof cartStore.isApplyingCoupon === 'function' && cartStore.isApplyingCoupon() ) {
			return true;
		}
		if ( checkoutStore && typeof checkoutStore.isCalculating === 'function' && checkoutStore.isCalculating() ) {
			return true;
		}
		return false;
	}

	/**
	 * Cart or checkout stamp, when that selector exists.
	 *
	 * @return {*}
	 */
	function lastCartUpdate() {
		var cartStore = select( 'wc/store/cart' );
		var checkoutStore = select( 'wc/store/checkout' );
		if ( cartStore && typeof cartStore.getLastCartUpdate === 'function' ) {
			return cartStore.getLastCartUpdate();
		}
		if ( checkoutStore && typeof checkoutStore.getLastCartUpdate === 'function' ) {
			return checkoutStore.getLastCartUpdate();
		}
		return null;
	}

	/**
	 * Cart block only. The checkout block already refreshes the cart.
	 *
	 * @return void
	 */
	function refetchCart() {
		if ( refetching || ! wp.apiFetch ) {
			return;
		}
		var actions = dispatch( 'wc/store/cart' );
		if ( ! actions || ! actions.receiveCart ) {
			return;
		}
		refetching = true;
		wp.apiFetch( { path: '/wc/store/v1/cart' } ).then( function ( fresh ) {
			if ( fresh && typeof fresh === 'object' ) {
				actions.receiveCart( fresh );
			}
		} ).catch( function () {
			return null;
		} ).then( function () {
			refetching = false;
		} );
	}

	function noticeContent( notice ) {
		if ( ! notice ) {
			return '';
		}
		if ( typeof notice.content === 'string' ) {
			return notice.content;
		}
		return typeof notice.message === 'string' ? notice.message : '';
	}

	/**
	 * One list for the block on this page. Checkout must not also fill wc/cart,
	 * or the same sentence is rendered twice.
	 *
	 * @return {Array<string>}
	 */
	function noticeContexts() {
		var pair = contexts();
		if ( onCheckoutBlock() ) {
			return [ pair[ 0 ] ];
		}
		if ( document && document.querySelector && document.querySelector( '.wc-block-cart, .wp-block-woocommerce-cart' ) ) {
			return [ pair[ 1 ] ];
		}
		return pair;
	}

	function findNotice( list ) {
		var current = null;
		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ] && list[ i ].id === NOTICE_ID ) {
				current = list[ i ];
				break;
			}
		}
		return current;
	}

	function syncNotices( text ) {
		var notices = select( 'core/notices' );
		var actions = dispatch( 'core/notices' );
		if ( ! notices || ! actions || ! actions.createNotice || ! actions.removeNotice || ! notices.getNotices ) {
			return;
		}
		var active = {};
		noticeContexts().forEach( function ( context ) {
			active[ context ] = true;
		} );
		contexts().forEach( function ( context ) {
			var list = notices.getNotices( context ) || [];
			var current = findNotice( list );
			if ( ! text || ! active[ context ] ) {
				if ( current ) {
					actions.removeNotice( NOTICE_ID, context );
				}
				return;
			}
			if ( current && noticeContent( current ) === text ) {
				return;
			}
			actions.createNotice( 'info', text, {
				id: NOTICE_ID,
				context: context,
				isDismissible: false
			} );
		} );
	}

	function bannerShows( text ) {
		var banners = document.querySelectorAll( '.wc-block-components-notice-banner' );
		for ( var i = 0; i < banners.length; i++ ) {
			if ( ( banners[ i ].textContent || '' ).indexOf( text ) !== -1 ) {
				return true;
			}
		}
		return false;
	}

	function clearFallback() {
		var nodes = document.querySelectorAll( '[data-rwgcm-shipping-notice]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].parentNode ) {
				nodes[ i ].parentNode.removeChild( nodes[ i ] );
			}
		}
	}

	/**
	 * Visible role=status region when the block notice list is not on the page.
	 * Removed once that list is showing the same text, so it is not announced twice.
	 *
	 * @param {string} text Notice text.
	 */
	function scheduleFallbackRemoval( text ) {
		var tries = 0;
		function tick() {
			if ( ! text || bannerShows( text ) ) {
				clearFallback();
				return;
			}
			tries++;
			if ( tries < 8 ) {
				var paint = window.requestAnimationFrame || function ( cb ) { return window.setTimeout( cb, 16 ); };
				paint( tick );
			}
		}
		var paint = window.requestAnimationFrame || function ( cb ) { return window.setTimeout( cb, 16 ); };
		paint( tick );
	}

	function ensureVisible( text ) {
		if ( ! document || ! document.querySelector ) {
			return;
		}
		// The notice list is the one visible copy. Its banner may also hold
		// one screen-reader span. Drop every fallback node once that list shows.
		if ( ! text || bannerShows( text ) ) {
			clearFallback();
			return;
		}
		var nodes = document.querySelectorAll( '[data-rwgcm-shipping-notice]' );
		var node = nodes.length ? nodes[ 0 ] : null;
		for ( var i = 1; i < nodes.length; i++ ) {
			if ( nodes[ i ].parentNode ) {
				nodes[ i ].parentNode.removeChild( nodes[ i ] );
			}
		}
		if ( ! node ) {
			var host = document.querySelector( '.wc-block-checkout, .wp-block-woocommerce-checkout, .wc-block-cart, .wp-block-woocommerce-cart' );
			if ( ! host ) {
				return;
			}
			node = document.createElement( 'div' );
			node.setAttribute( 'data-rwgcm-shipping-notice', '1' );
			node.className = 'woocommerce-info rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--block';
			node.setAttribute( 'role', 'status' );
			host.insertBefore( node, host.firstChild );
		}
		if ( node.textContent !== text ) {
			node.textContent = text;
		}
		scheduleFallbackRemoval( text );
	}

	/**
	 * Runs on every wc/store/cart update, including after the shipping address
	 * changes. The notice text is extensions.rwgcm.shipping_currency_notice.
	 *
	 * @return void
	 */
	function reconcile( text ) {
		try {
			syncNotices( text );
		} catch ( err ) {
			if ( window.console && console.error ) {
				console.error( err );
			}
		}
		ensureVisible( text );
	}

	function syncCart() {
		// Wait until update-customer (or a coupon) has finished. The cart
		// snapshot during that request is still the previous address.
		if ( addressUpdatePending() ) {
			wasPending = true;
			return;
		}
		var stamp = lastCartUpdate();
		var justSettled = wasPending || ( null !== stamp && stamp !== seenCartUpdate );
		if ( null !== stamp ) {
			seenCartUpdate = stamp;
		}
		wasPending = false;
		var cart = cartPayload();
		if ( ! cart ) {
			return;
		}
		var text = extensionNotice( cart );
		// Every settled cart snapshot, including the one just after
		// isCustomerDataUpdating / isCalculating flips off, and when
		// lastCartUpdate changes. Clear when the string is empty, add when
		// it is present, one notice per id.
		if ( null !== text || justSettled ) {
			reconcile( null === text ? '' : text );
		}
		if ( onCheckoutBlock() ) {
			return;
		}
		var country = shippingCountry( cart );
		if ( null === lastCountry ) {
			lastCountry = country;
			return;
		}
		if ( country && country !== lastCountry ) {
			lastCountry = country;
			refetchCart();
		}
	}

	subscribe( syncCart );
	if ( wp.domReady ) {
		wp.domReady( syncCart );
	} else {
		syncCart();
	}
}( window.wp, window.wc ) );
