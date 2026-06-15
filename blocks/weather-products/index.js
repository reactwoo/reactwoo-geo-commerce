( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var createElement = wp.element.createElement;

	registerBlockType( 'rwgcm/weather-products', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'rwgcm-weather-products-editor' } );

			return createElement(
				'div',
				blockProps,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Weather Products', initialOpen: true },
						createElement( TextControl, {
							label: 'Heading',
							value: attributes.title || '',
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
						} ),
						createElement( RangeControl, {
							label: 'Max products',
							value: attributes.limit || 8,
							onChange: function ( value ) {
								setAttributes( { limit: value } );
							},
							min: 1,
							max: 24,
						} ),
						createElement( RangeControl, {
							label: 'Columns',
							value: attributes.columns || 4,
							onChange: function ( value ) {
								setAttributes( { columns: value } );
							},
							min: 1,
							max: 6,
						} ),
						createElement( TextControl, {
							label: 'Category slug or ID',
							value: attributes.category || '',
							onChange: function ( value ) {
								setAttributes( { category: value } );
							},
						} ),
						createElement( TextControl, {
							label: 'Product IDs (comma-separated)',
							value: attributes.ids || '',
							onChange: function ( value ) {
								setAttributes( { ids: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'Order by',
							value: attributes.orderby || 'relevance',
							options: [
								{ label: 'Weather relevance', value: 'relevance' },
								{ label: 'Date', value: 'date' },
								{ label: 'Menu order', value: 'menu_order' },
							],
							onChange: function ( value ) {
								setAttributes( { orderby: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'When no weather match',
							value: attributes.fallback || 'hide',
							options: [
								{ label: 'Hide', value: 'hide' },
								{ label: 'Fallback category', value: 'category' },
								{ label: 'Message', value: 'message' },
							],
							onChange: function ( value ) {
								setAttributes( { fallback: value } );
							},
						} ),
						createElement( TextControl, {
							label: 'Fallback category',
							value: attributes.fallback_category || '',
							onChange: function ( value ) {
								setAttributes( { fallback_category: value } );
							},
						} ),
						createElement( TextControl, {
							label: 'Fallback message',
							value: attributes.fallback_message || '',
							onChange: function ( value ) {
								setAttributes( { fallback_message: value } );
							},
						} ),
						createElement( SelectControl, {
							label: 'When weather unavailable',
							value: attributes.weather_unavailable || 'hide',
							options: [
								{ label: 'Hide', value: 'hide' },
								{ label: 'Fallback category', value: 'category' },
								{ label: 'Message', value: 'message' },
							],
							onChange: function ( value ) {
								setAttributes( { weather_unavailable: value } );
							},
						} )
					)
				),
				createElement( ServerSideRender, {
					block: 'rwgcm/weather-products',
					attributes: attributes,
				} )
			);
		},
	} );
} )( window.wp );
