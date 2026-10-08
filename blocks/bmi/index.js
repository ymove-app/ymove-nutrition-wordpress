/* global wp */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, ToggleControl, ColorPicker, Placeholder } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};
	const themes = cfg.themes || { classic: 'Classic' };
	const themeOptions = () => [
		{ label: __( 'Site default', 'ymove-nutrition' ) + ' (' + ( themes[ cfg.siteTheme ] || themes.classic ) + ')', value: '' },
		...Object.keys( themes ).map( ( value ) => ( { label: themes[ value ], value } ) ),
	];
	const isClassic = ( theme ) => ( theme || cfg.siteTheme || 'classic' ) === 'classic';
	const takesAccent = ( theme ) => [ 'classic', 'ios', 'brutalist' ].includes( theme || cfg.siteTheme || 'classic' );

	registerBlockType( 'ymove/bmi', {
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
						{ title: __( 'BMI settings', 'ymove-nutrition' ) },
						el( TextControl, { label: __( 'Title (optional)', 'ymove-nutrition' ), value: attributes.title, onChange: ( title ) => setAttributes( { title } ) } ),
						el( SelectControl, {
							label: __( 'Email capture', 'ymove-nutrition' ),
							value: attributes.leadMode,
							options: [
								{ label: __( 'Off', 'ymove-nutrition' ), value: 'off' },
								{ label: __( 'Optional', 'ymove-nutrition' ), value: 'optional' },
								{ label: __( 'Required - email unlocks the result', 'ymove-nutrition' ), value: 'required' },
							],
							onChange: ( leadMode ) => setAttributes( { leadMode } ),
						} ),
						el( SelectControl, {
							label: __( 'Units', 'ymove-nutrition' ),
							value: attributes.units,
							options: [
								{ label: __( 'Site default', 'ymove-nutrition' ), value: '' },
								{ label: __( 'Metric', 'ymove-nutrition' ), value: 'metric' },
								{ label: __( 'Imperial', 'ymove-nutrition' ), value: 'imperial' },
							],
							onChange: ( units ) => setAttributes( { units } ),
						} ),
						el( ToggleControl, { label: __( 'Hide the metric / imperial switch', 'ymove-nutrition' ), checked: attributes.hideUnits, onChange: ( hideUnits ) => setAttributes( { hideUnits } ) } ),
						el( SelectControl, {
							label: __( 'Template', 'ymove-nutrition' ),
							value: attributes.layout,
							options: [ { label: __( 'Card', 'ymove-nutrition' ), value: 'card' }, { label: __( 'Plain', 'ymove-nutrition' ), value: 'plain' }, { label: __( 'Split', 'ymove-nutrition' ), value: 'split' } ],
							onChange: ( layout ) => setAttributes( { layout } ),
						} ),
						el( SelectControl, {
							label: __( 'Style', 'ymove-nutrition' ),
							value: attributes.theme,
							options: themeOptions(),
							help: __( 'Site default is set under Settings > Your Move Nutrition.', 'ymove-nutrition' ),
							onChange: ( theme ) => setAttributes( { theme } ),
						} ),
						( attributes.theme || cfg.siteTheme ) === 'gradient' && el( SelectControl, {
							label: __( 'Gradient colours', 'ymove-nutrition' ),
							value: attributes.palette,
							options: [ { label: __( 'Site default', 'ymove-nutrition' ), value: '' } ].concat( Object.keys( cfg.palettes || {} ).map( ( value ) => ( { label: cfg.palettes[ value ], value } ) ) ),
							onChange: ( palette ) => setAttributes( { palette } ),
						} ),
						isClassic( attributes.theme ) && el( SelectControl, {
							label: __( 'Colour scheme', 'ymove-nutrition' ),
							value: attributes.scheme,
							options: [ { label: __( 'Light', 'ymove-nutrition' ), value: 'default' }, { label: __( 'Dark', 'ymove-nutrition' ), value: 'dark' }, { label: __( 'Soft', 'ymove-nutrition' ), value: 'soft' }, { label: __( 'Bold', 'ymove-nutrition' ), value: 'bold' } ],
							onChange: ( scheme ) => setAttributes( { scheme } ),
						} )
					),
					takesAccent( attributes.theme ) && el(
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
					el( Placeholder, {
						icon: 'universal-access',
						label: __( 'BMI Calculator', 'ymove-nutrition' ),
						instructions: __( 'Height and weight in, BMI, WHO category and healthy weight range out. Preview on the front end.', 'ymove-nutrition' ),
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
