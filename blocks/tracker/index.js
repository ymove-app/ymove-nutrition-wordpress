/* global wp, ymoveNutritionEditor */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, ToggleControl, ColorPicker, Placeholder, Notice } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};

	registerBlockType( 'ymove/tracker', {
		edit( { attributes, setAttributes } ) {
			const props = useBlockProps( { className: 'ymn-editor-placeholder' } );
			const toggle = ( key, label ) =>
				el( ToggleControl, { label, checked: attributes[ key ], onChange: ( v ) => setAttributes( { [ key ]: v } ) } );
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Tracker features', 'ymove-nutrition' ) },
						toggle( 'showBarcode', __( 'Barcode scanner', 'ymove-nutrition' ) ),
						toggle( 'showPhoto', __( 'AI photo logging (Pro plan)', 'ymove-nutrition' ) ),
						toggle( 'showText', __( 'AI text logging (Pro plan)', 'ymove-nutrition' ) ),
						toggle( 'showWeek', __( 'Weekly overview', 'ymove-nutrition' ) )
					),
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
						el(
							Notice,
							{ status: 'warning', isDismissible: false },
							__( 'No Your Move API key connected yet - the tracker will show a notice to admins only.', 'ymove-nutrition' ),
							' ',
							el( 'a', { href: cfg.settings }, __( 'Settings', 'ymove-nutrition' ) )
						),
					el( Placeholder, {
						icon: 'food',
						label: __( 'Calorie Tracker', 'ymove-nutrition' ),
						instructions: __( 'Logged-in members see their food diary here. Put this block on a members-only page. Visitors are asked to log in.', 'ymove-nutrition' ),
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
