/**
 * Price books → Shipping: one currency column below 782px.
 * Changing the select does not save anything.
 */
( function () {
	var select = document.getElementById( 'rwgcm-shipping-currency' );
	var matrix = document.querySelector( '.rwgcm-shipping-matrix' );
	if ( ! select || ! matrix ) {
		return;
	}
	function apply() {
		var code = select.value;
		var cols = matrix.querySelectorAll( '.rwgcm-currency-col' );
		for ( var i = 0; i < cols.length; i++ ) {
			var on = cols[ i ].getAttribute( 'data-currency' ) === code;
			if ( on ) {
				cols[ i ].classList.add( 'is-selected' );
			} else {
				cols[ i ].classList.remove( 'is-selected' );
			}
		}
	}
	select.addEventListener( 'change', apply );
	apply();
}() );
