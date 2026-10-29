( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var createElement = wp.element.createElement;

	registerBlockType( 'rwgcm/product-price', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'rwgcm-product-price-editor' } );

			return createElement(
				'div',
				blockProps,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Product Price', initialOpen: true },
						createElement( TextControl, {
							label: 'Product ID',
							help: 'Leave empty to use the current product.',
							type: 'number',
							value: attributes.product_id ? String( attributes.product_id ) : '',
							onChange: function ( value ) {
								setAttributes( { product_id: parseInt( value, 10 ) || 0 } );
							},
						} ),
						createElement( TextControl, {
							label: 'Variation ID',
							type: 'number',
							value: attributes.variation_id ? String( attributes.variation_id ) : '',
							onChange: function ( value ) {
								setAttributes( { variation_id: parseInt( value, 10 ) || 0 } );
							},
						} ),
						createElement( SelectControl, {
							label: 'Format',
							value: attributes.format || 'symbol',
							options: [
								{ label: 'Currency symbol', value: 'symbol' },
								{ label: 'Amount only', value: 'amount' },
								{ label: 'Currency code', value: 'code' },
							],
							onChange: function ( value ) {
								setAttributes( { format: value } );
							},
						} ),
						createElement( TextControl, {
							label: 'Billing period suffix',
							help: 'Example: /year. Leave empty to use the subscription period.',
							value: attributes.period || '',
							onChange: function ( value ) {
								setAttributes( { period: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'From prefix',
							help: 'Lowest variation price on variable products.',
							value: attributes.from || 'auto',
							options: [
								{ label: 'Automatic', value: 'auto' },
								{ label: 'Always', value: 'yes' },
								{ label: 'Never', value: 'no' },
							],
							onChange: function ( value ) {
								setAttributes( { from: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'Sale price',
							value: attributes.sale || 'yes',
							options: [
								{ label: 'Show struck-through regular price', value: 'yes' },
								{ label: 'Active price only', value: 'no' },
							],
							onChange: function ( value ) {
								setAttributes( { sale: value } );
							},
						} ),
						createElement( TextControl, {
							label: 'Fallback text',
							help: 'Shown when the product is missing, not purchasable, or out of stock.',
							value: attributes.fallback || 'Unavailable',
							onChange: function ( value ) {
								setAttributes( { fallback: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'Colour',
							help: 'Inherit, or a site colour variable.',
							value: attributes.color || 'inherit',
							options: [
								{ label: 'Inherit', value: 'inherit' },
								{ label: 'Primary', value: 'primary' },
								{ label: 'Secondary', value: 'secondary' },
								{ label: 'Accent', value: 'accent' },
								{ label: 'Text', value: 'text' },
							],
							onChange: function ( value ) {
								setAttributes( { color: value } );
							},
						} )
					)
				),
				createElement( ServerSideRender, {
					block: 'rwgcm/product-price',
					attributes: attributes,
				} )
			);
		},
	} );
} )( window.wp );
