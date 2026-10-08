/* global wp, ymoveNutritionEditor */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, RangeControl, TextControl, ToggleControl, ColorPicker, Placeholder, Notice } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};

	registerBlockType( 'ymove/meal-plan', {
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
						{ title: __( 'Defaults', 'ymove-nutrition' ) },
						el( SelectControl, {
							label: __( 'Diet', 'ymove-nutrition' ),
							value: attributes.diet,
							options: [ 'balanced', 'high_protein', 'low_carb', 'keto', 'vegan', 'vegetarian', 'mediterranean', 'paleo' ].map( ( v ) => ( { value: v, label: v.replace( '_', ' ' ) } ) ),
							onChange: ( diet ) => setAttributes( { diet } ),
						} ),
						el( RangeControl, { label: __( 'Days', 'ymove-nutrition' ), min: 1, max: 7, value: attributes.days, onChange: ( days ) => setAttributes( { days } ) } ),
						el( RangeControl, { label: __( 'Meals per day', 'ymove-nutrition' ), min: 3, max: 6, value: attributes.meals, onChange: ( meals ) => setAttributes( { meals } ) } ),
						el( TextControl, {
							label: __( 'Default calories (0 = use the member\'s target or 2000)', 'ymove-nutrition' ),
							type: 'number',
							value: attributes.calories,
							onChange: ( v ) => setAttributes( { calories: parseInt( v, 10 ) || 0 } ),
						} ),
						el( ToggleControl, { label: __( 'Show ingredients and method', 'ymove-nutrition' ), checked: attributes.showRecipes, onChange: ( showRecipes ) => setAttributes( { showRecipes } ) } )
					),
					el(
						PanelBody,
						{ title: __( 'Accent color', 'ymove-nutrition' ), initialOpen: false },
						el( ColorPicker, { color: attributes.accentColor || '#2563eb', onChange: ( accentColor ) => setAttributes( { accentColor } ), enableAlpha: false } )
					)
				),
				el(
					'div',
					props,
					! cfg.connected && el( Notice, { status: 'warning', isDismissible: false }, __( 'No Your Move API key connected yet.', 'ymove-nutrition' ), ' ', el( 'a', { href: cfg.settings }, __( 'Settings', 'ymove-nutrition' ) ) ),
					el( Placeholder, {
						icon: 'carrot',
						label: __( 'Meal Plan Generator', 'ymove-nutrition' ),
						instructions: __( 'Visitors pick calories, diet, days and meals per day and get a plan with recipes. Members can add meals straight to their diary. Who may use it is set under Settings > Your Move Nutrition.', 'ymove-nutrition' ),
					} )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
