/* global wp, ymoveNutritionEditor */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, RangeControl, ToggleControl, ColorPicker, Button, Spinner, Notice, Placeholder } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); };

	const MEAL_TYPES = [ [ '', __( 'Any meal', 'ymove-nutrition' ) ], [ 'breakfast', 'Breakfast' ], [ 'lunch', 'Lunch' ], [ 'dinner', 'Dinner' ], [ 'snack', 'Snack' ], [ 'pre_workout', 'Pre-workout' ], [ 'post_workout', 'Post-workout' ], [ 'drink', 'Drink' ] ];
	const DIETS = [ [ '', __( 'Any diet', 'ymove-nutrition' ) ], [ 'high_protein', 'High protein' ], [ 'low_carb', 'Low carb' ], [ 'keto', 'Keto' ], [ 'vegan', 'Vegan' ], [ 'vegetarian', 'Vegetarian' ], [ 'mediterranean', 'Mediterranean' ], [ 'paleo', 'Paleo' ] ];
	const opts = ( list ) => list.map( ( [ value, label ] ) => ( { value, label } ) );

	function api( path ) {
		return fetch( restUrl( path ), { headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' } ).then( async ( r ) => {
			const body = await r.json().catch( () => ( {} ) );
			if ( ! r.ok ) {
				throw new Error( body.message || r.statusText );
			}
			return body;
		} );
	}

	function RecipePicker( { onPick } ) {
		const [ q, setQ ] = useState( '' );
		const [ results, setResults ] = useState( [] );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );
		const timer = useRef( null );

		useEffect( () => {
			if ( q.trim().length < 2 ) {
				setResults( [] );
				return;
			}
			clearTimeout( timer.current );
			timer.current = setTimeout( () => {
				setLoading( true );
				setError( '' );
				api( 'recipes?pageSize=12&q=' + encodeURIComponent( q.trim() ) )
					.then( ( body ) => setResults( body.data || [] ) )
					.catch( ( e ) => setError( e.message ) )
					.finally( () => setLoading( false ) );
			}, 350 );
			return () => clearTimeout( timer.current );
		}, [ q ] );

		return el(
			'div',
			{ className: 'ymn-editor-search' },
			el( TextControl, { label: __( 'Find a recipe', 'ymove-nutrition' ), value: q, onChange: setQ, placeholder: __( 'e.g. overnight oats', 'ymove-nutrition' ) } ),
			loading && el( Spinner ),
			error && el( Notice, { status: 'error', isDismissible: false }, error ),
			el(
				'ul',
				{ className: 'ymn-editor-results' },
				results.map( ( r ) =>
					el(
						'li',
						{ key: r.id },
						el( Button, { variant: 'link', onClick: () => onPick( r ) }, r.title ),
						el( 'small', null, ` ${ r.calories } kcal` )
					)
				)
			)
		);
	}

	registerBlockType( 'ymove/recipes', {
		edit( { attributes, setAttributes } ) {
			const props = useBlockProps();
			const single = !! attributes.recipe;
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Single recipe', 'ymove-nutrition' ) },
						el( 'p', null, single
							? __( 'Showing one recipe with full nutrition and Google recipe markup.', 'ymove-nutrition' )
							: __( 'Pick a recipe to embed it in this post. Leave empty to show a recipe browser instead.', 'ymove-nutrition' ) ),
						single && el( 'p', null, el( 'strong', null, attributes.recipeTitle || attributes.recipe ), ' ', el( Button, { variant: 'link', isDestructive: true, onClick: () => setAttributes( { recipe: '', recipeTitle: '' } ) }, __( 'Remove', 'ymove-nutrition' ) ) ),
						el( RecipePicker, { onPick: ( r ) => setAttributes( { recipe: r.slug, recipeTitle: r.title } ) } )
					),
					! single && el(
						PanelBody,
						{ title: __( 'Recipe browser', 'ymove-nutrition' ) },
						el( TextControl, { label: __( 'Start with search', 'ymove-nutrition' ), value: attributes.query, onChange: ( query ) => setAttributes( { query } ) } ),
						el( SelectControl, { label: __( 'Meal', 'ymove-nutrition' ), value: attributes.mealType, options: opts( MEAL_TYPES ), onChange: ( mealType ) => setAttributes( { mealType } ) } ),
						el( SelectControl, { label: __( 'Diet', 'ymove-nutrition' ), value: attributes.diet, options: opts( DIETS ), onChange: ( diet ) => setAttributes( { diet } ) } ),
						el( TextControl, {
							label: __( 'Max calories per serving (0 = any)', 'ymove-nutrition' ),
							type: 'number',
							value: attributes.maxCalories,
							onChange: ( v ) => setAttributes( { maxCalories: parseInt( v, 10 ) || 0 } ),
						} ),
						el( RangeControl, { label: __( 'Recipes per page', 'ymove-nutrition' ), min: 3, max: 24, step: 3, value: attributes.perPage, onChange: ( perPage ) => setAttributes( { perPage } ) } ),
						el( ToggleControl, { label: __( 'Let visitors search and filter', 'ymove-nutrition' ), checked: attributes.showFilters, onChange: ( showFilters ) => setAttributes( { showFilters } ) } )
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
					single && ServerSideRender
						? el( ServerSideRender, { block: 'ymove/recipes', attributes } )
						: el( Placeholder, {
								icon: 'food',
								label: __( 'Recipes', 'ymove-nutrition' ),
								instructions: single
									? attributes.recipeTitle
									: __( 'Visitors browse and search recipes with per-serving nutrition. To embed one recipe instead, pick it in the block settings.', 'ymove-nutrition' ),
						  } )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
