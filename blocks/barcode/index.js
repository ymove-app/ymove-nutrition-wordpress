/* global wp, ymoveNutritionEditor */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, ColorPicker, Placeholder, Notice } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};

	registerBlockType( 'ymove/barcode', {
		edit( { attributes, setAttributes } ) {
			const props = useBlockProps( { className: 'ymn-editor-placeholder' } );
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Accent color', 'ymove-nutrition' ), initialOpen: false },
						el( ColorPicker, {
							color: attributes.accentColor || '#2563eb',
							onChange: ( accentColor ) => setAttributes( { accentColor } ),
							enableAlpha: false,
						} )
					)
				),
				el(
					'div',
					props,
					! cfg.connected &&
						el( Notice, { status: 'warning', isDismissible: false }, __( 'No Your Move API key connected yet.', 'ymove-nutrition' ), ' ', el( 'a', { href: cfg.settings }, __( 'Settings', 'ymove-nutrition' ) ) ),
					el( Placeholder, {
						icon: 'camera',
						label: __( 'Barcode Lookup', 'ymove-nutrition' ),
						instructions: __( 'Members scan or type a barcode and see the product\'s nutrition label.', 'ymove-nutrition' ),
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
