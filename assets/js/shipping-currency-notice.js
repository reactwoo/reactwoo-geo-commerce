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
	var queued = false;

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
	 * @return {string|null} Notice text, or null when the cart store is not ready.
	 */
	function readNotice() {
		var store = select( 'wc/store/cart' );
		if ( ! store || ! store.getCartData ) {
			return null;
		}
		var cart = store.getCartData();
		var ext = cart && cart.extensions && cart.extensions.rwgcm;
		var text = ext && ext.shipping_currency_notice;
		return typeof text === 'string' ? text : '';
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

	function syncNotices( text ) {
		var notices = select( 'core/notices' );
		var actions = dispatch( 'core/notices' );
		if ( ! notices || ! actions || ! actions.createNotice || ! actions.removeNotice || ! notices.getNotices ) {
			return;
		}
		contexts().forEach( function ( context ) {
			var list = notices.getNotices( context ) || [];
			var current = null;
			for ( var i = 0; i < list.length; i++ ) {
				if ( list[ i ] && list[ i ].id === NOTICE_ID ) {
					current = list[ i ];
					break;
				}
			}
			if ( ! text ) {
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
	function ensureVisible( text ) {
		if ( ! document || ! document.querySelectorAll ) {
			return;
		}
		if ( ! text || bannerShows( text ) ) {
			clearFallback();
			return;
		}
		var hosts = document.querySelectorAll( '.wc-block-checkout, .wc-block-cart, .wp-block-woocommerce-checkout, .wp-block-woocommerce-cart' );
		if ( ! hosts.length ) {
			return;
		}
		for ( var i = 0; i < hosts.length; i++ ) {
			var host = hosts[ i ];
			var node = host.querySelector( '[data-rwgcm-shipping-notice]' );
			if ( ! node ) {
				node = document.createElement( 'div' );
				node.setAttribute( 'data-rwgcm-shipping-notice', '1' );
				node.className = 'woocommerce-info rwgcm-shipping-currency-notice rwgcm-shipping-currency-notice--block';
				node.setAttribute( 'role', 'status' );
				host.insertBefore( node, host.firstChild );
			}
			if ( node.textContent !== text ) {
				node.textContent = text;
			}
		}
	}

	function sync() {
		var text = readNotice();
		if ( null === text ) {
			return;
		}
		try {
			syncNotices( text );
		} catch ( err ) {
			if ( window.console && console.error ) {
				console.error( err );
			}
		}
		var paint = window.requestAnimationFrame || function ( cb ) { return window.setTimeout( cb, 16 ); };
		paint( function () {
			ensureVisible( text );
		} );
	}

	function schedule() {
		if ( queued ) {
			return;
		}
		queued = true;
		var paint = window.requestAnimationFrame || function ( cb ) { return window.setTimeout( cb, 16 ); };
		paint( function () {
			queued = false;
			sync();
		} );
	}

	subscribe( schedule );
	if ( wp.domReady ) {
		wp.domReady( sync );
	} else {
		sync();
	}
}( window.wp, window.wc ) );
