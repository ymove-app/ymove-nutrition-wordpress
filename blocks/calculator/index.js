/* global wp */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, ToggleControl, TextControl, RangeControl, ColorPicker, Placeholder } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};
	const themes = cfg.themes || { classic: 'Classic' };
	const themeOptions = () => [
		{ label: __( 'Site default', 'ymove-nutrition' ) + ' (' + ( themes[ cfg.siteTheme ] || themes.classic ) + ')', value: '' },
		...Object.keys( themes ).map( ( value ) => ( { label: themes[ value ], value } ) ),
	];
	const isClassic = ( theme ) => ( theme || cfg.siteTheme || 'classic' ) === 'classic';
	const takesAccent = ( theme ) => [ 'classic', 'ios', 'brutalist' ].includes( theme || cfg.siteTheme || 'classic' );

	registerBlockType( 'ymove/calculator', {
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
						{ title: __( 'Calculator settings', 'ymove-nutrition' ) },
						el( TextControl, {
							label: __( 'Title (optional)', 'ymove-nutrition' ),
							value: attributes.title,
							onChange: ( title ) => setAttributes( { title } ),
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
						el( SelectControl, {
							label: __( 'Default goal', 'ymove-nutrition' ),
							value: attributes.goal,
							options: [
								{ label: __( 'Lose weight', 'ymove-nutrition' ), value: 'lose' },
								{ label: __( 'Lose slowly', 'ymove-nutrition' ), value: 'lose_slow' },
								{ label: __( 'Maintain', 'ymove-nutrition' ), value: 'maintain' },
								{ label: __( 'Gain slowly', 'ymove-nutrition' ), value: 'gain_slow' },
								{ label: __( 'Gain weight', 'ymove-nutrition' ), value: 'gain' },
							],
							onChange: ( goal ) => setAttributes( { goal } ),
						} ),
						el( SelectControl, {
							label: __( 'BMR formula', 'ymove-nutrition' ),
							value: attributes.formula,
							options: [
								{ label: __( 'Mifflin-St Jeor (recommended)', 'ymove-nutrition' ), value: 'mifflin' },
								{ label: __( 'Harris-Benedict (revised 1984)', 'ymove-nutrition' ), value: 'harris' },
								{ label: __( 'Katch-McArdle (asks for body fat %)', 'ymove-nutrition' ), value: 'katch' },
							],
							onChange: ( formula ) => setAttributes( { formula } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show macro split', 'ymove-nutrition' ),
							checked: attributes.showMacros,
							onChange: ( showMacros ) => setAttributes( { showMacros } ),
						} ),
						el( ToggleControl, {
							label: __( 'Instant results while typing', 'ymove-nutrition' ),
							checked: attributes.instant,
							onChange: ( instant ) => setAttributes( { instant } ),
						} ),
						el( ToggleControl, {
							label: __( 'Also show BMI in the results', 'ymove-nutrition' ),
							checked: attributes.showBmi,
							onChange: ( showBmi ) => setAttributes( { showBmi } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show the goal selector', 'ymove-nutrition' ),
							help: __( 'Off = calculate maintenance calories only. Goal labels and kcal deltas are edited under Settings > Your Move Nutrition.', 'ymove-nutrition' ),
							checked: attributes.showGoal,
							onChange: ( showGoal ) => setAttributes( { showGoal } ),
						} ),
						el( ToggleControl, {
							label: __( 'Hide the metric / imperial switch', 'ymove-nutrition' ),
							checked: attributes.hideUnits,
							onChange: ( hideUnits ) => setAttributes( { hideUnits } ),
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Layout & style', 'ymove-nutrition' ) },
						el( SelectControl, {
							label: __( 'Template', 'ymove-nutrition' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Card - form above results', 'ymove-nutrition' ), value: 'card' },
								{ label: __( 'Plain - no box, inherits your theme', 'ymove-nutrition' ), value: 'plain' },
								{ label: __( 'Split - form left, live results right', 'ymove-nutrition' ), value: 'split' },
								{ label: __( 'Steps - multi-step wizard', 'ymove-nutrition' ), value: 'steps' },
								{ label: __( 'Chat - asks one question at a time, like a conversation', 'ymove-nutrition' ), value: 'chat' },
							],
							onChange: ( layout ) => setAttributes( { layout } ),
						} ),
						attributes.layout === 'chat' && el( SelectControl, {
							label: __( 'Chat display', 'ymove-nutrition' ),
							value: attributes.chatDisplay,
							options: [
								{ label: __( 'In the page - grows with the conversation', 'ymove-nutrition' ), value: 'auto' },
								{ label: __( 'In the page - fixed height, scrolls inside', 'ymove-nutrition' ), value: 'fixed' },
								{ label: __( 'Floating button - bottom right of the screen', 'ymove-nutrition' ), value: 'floating' },
							],
							help: attributes.chatDisplay === 'floating' ? __( 'Opens as a chat window from a button in the corner. Add it once per page.', 'ymove-nutrition' ) : null,
							onChange: ( chatDisplay ) => setAttributes( { chatDisplay } ),
						} ),
						attributes.layout === 'chat' && attributes.chatDisplay !== 'auto' && el( RangeControl, {
							label: __( 'Chat height (px)', 'ymove-nutrition' ),
							min: 300,
							max: 900,
							step: 10,
							value: attributes.chatHeight,
							onChange: ( chatHeight ) => setAttributes( { chatHeight } ),
						} ),
						attributes.layout === 'chat' && attributes.chatDisplay === 'floating' && el( TextControl, {
							label: __( 'Button text', 'ymove-nutrition' ),
							value: attributes.chatLabel,
							placeholder: __( 'How many calories do I need?', 'ymove-nutrition' ),
							onChange: ( chatLabel ) => setAttributes( { chatLabel } ),
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
							options: [
								{ label: __( 'Light (default)', 'ymove-nutrition' ), value: 'default' },
								{ label: __( 'Dark', 'ymove-nutrition' ), value: 'dark' },
								{ label: __( 'Soft - warm neutrals', 'ymove-nutrition' ), value: 'soft' },
								{ label: __( 'Bold - accent background', 'ymove-nutrition' ), value: 'bold' },
							],
							onChange: ( scheme ) => setAttributes( { scheme } ),
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Lead capture', 'ymove-nutrition' ) },
						el( SelectControl, {
							label: __( 'Email capture', 'ymove-nutrition' ),
							value: attributes.leadMode === 'off' && attributes.leadCapture ? 'optional' : attributes.leadMode,
							options: [
								{ label: __( 'Off', 'ymove-nutrition' ), value: 'off' },
								{ label: __( 'Optional - show results, offer to email them', 'ymove-nutrition' ), value: 'optional' },
								{ label: __( 'Required - email address unlocks the results', 'ymove-nutrition' ), value: 'required' },
							],
							onChange: ( leadMode ) => setAttributes( { leadMode, leadCapture: leadMode !== 'off' } ),
							help: __( 'Visitors receive a branded email with their numbers. Configure the email, consent text, notifications and webhook under Settings > Your Move Nutrition.', 'ymove-nutrition' ),
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
						icon: 'calculator',
						label: __( 'Calorie Calculator', 'ymove-nutrition' ),
						instructions: __( 'Visitors enter sex, age, height, weight, activity and goal and get BMR, TDEE, goal calories and a macro split. Preview it on the front end.', 'ymove-nutrition' ),
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
