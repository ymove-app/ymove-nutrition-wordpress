/* global wp, ymoveNutritionEditor */
( function () {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment, useState, useEffect, useRef } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, ToggleControl, Button, Spinner, Notice, Placeholder } = wp.components;
	const { __ } = wp.i18n;
	const cfg = window.ymoveNutritionEditor || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); };

	function api( path ) {
		return fetch( restUrl( path ), { headers: { 'X-WP-Nonce': cfg.nonce }, credentials: 'same-origin' } ).then( async ( r ) => {
			const body = await r.json().catch( () => ( {} ) );
			if ( ! r.ok ) {
				throw new Error( body.message || r.statusText );
			}
			return body;
		} );
	}

	function fmt( v, d ) {
		if ( v === null || v === undefined ) return '-';
		return Number( v ).toLocaleString( undefined, { maximumFractionDigits: d === undefined ? 1 : d } );
	}

	function Label( { food, per } ) {
		const factor = per === '100g' && food.servingSize > 0 ? 100 / food.servingSize : 1;
		const v = ( k ) => ( food[ k ] === null || food[ k ] === undefined ? null : food[ k ] * factor );
		const rows = [
			[ __( 'Total Fat', 'ymove-nutrition' ), v( 'fat' ), 'g', true ],
			[ __( 'Saturated Fat', 'ymove-nutrition' ), v( 'saturatedFat' ), 'g', false ],
			[ __( 'Cholesterol', 'ymove-nutrition' ), v( 'cholesterol' ), 'mg', true ],
			[ __( 'Sodium', 'ymove-nutrition' ), v( 'sodium' ), 'mg', true ],
			[ __( 'Total Carbohydrate', 'ymove-nutrition' ), v( 'carbs' ), 'g', true ],
			[ __( 'Dietary Fiber', 'ymove-nutrition' ), v( 'fiber' ), 'g', false ],
			[ __( 'Total Sugars', 'ymove-nutrition' ), v( 'sugar' ), 'g', false ],
			[ __( 'Protein', 'ymove-nutrition' ), v( 'protein' ), 'g', true ],
		].filter( ( r ) => r[ 1 ] !== null );
		return el(
			'div',
			{ className: 'ymn ymn-label' },
			el( 'div', { className: 'ymn-label-title' }, __( 'Nutrition Facts', 'ymove-nutrition' ) ),
			el( 'div', { className: 'ymn-label-food' }, food.displayName || food.shortName || food.name ),
			el( 'div', { className: 'ymn-label-serving' }, __( 'Serving size', 'ymove-nutrition' ), ' ', el( 'strong', null, per === '100g' ? '100 g' : food.servingDescription || fmt( food.servingSize, 0 ) + ' g' ) ),
			el( 'div', { className: 'ymn-label-cal' }, el( 'span', null, __( 'Calories', 'ymove-nutrition' ) ), el( 'strong', null, fmt( v( 'calories' ), 0 ) ) ),
			el(
				'table',
				{ className: 'ymn-label-table' },
				el( 'tbody', null, rows.map( ( r, i ) => el( 'tr', { key: i, className: r[ 3 ] ? 'is-main' : 'is-sub' }, el( 'th', null, r[ 0 ] ), el( 'td', null, fmt( r[ 1 ] ) + ' ' + r[ 2 ] ) ) ) )
			),
			food.brand && el( 'div', { className: 'ymn-label-brand' }, food.brand )
		);
	}

	function Search( { onPick } ) {
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
				api( 'foods/search?q=' + encodeURIComponent( q.trim() ) )
					.then( ( body ) => setResults( body.data || [] ) )
					.catch( ( e ) => setError( e.message ) )
					.finally( () => setLoading( false ) );
			}, 350 );
			return () => clearTimeout( timer.current );
		}, [ q ] );

		return el(
			'div',
			{ className: 'ymn-editor-search' },
			el( TextControl, { label: __( 'Search a food', 'ymove-nutrition' ), value: q, onChange: setQ, placeholder: __( 'e.g. chicken breast, oat milk, Nutella', 'ymove-nutrition' ), autoFocus: true } ),
			loading && el( Spinner ),
			error && el( Notice, { status: 'error', isDismissible: false }, error ),
			el(
				'ul',
				{ className: 'ymn-editor-results' },
				results.map( ( f ) =>
					el(
						'li',
						{ key: f.id },
						el(
							Button,
							{ variant: 'link', onClick: () => onPick( f ) },
							f.displayName || f.shortName || f.name,
							el( 'small', null, ' ', fmt( f.calories, 0 ), ' kcal / ', f.servingDescription || fmt( f.servingSize, 0 ) + ' g' )
						)
					)
				)
			)
		);
	}

	registerBlockType( 'ymove/nutrition-facts', {
		edit( { attributes, setAttributes } ) {
			const props = useBlockProps();
			const [ picking, setPicking ] = useState( ! attributes.food );

			const pick = ( food ) => {
				setAttributes( { foodId: String( food.id ), food } );
				setPicking( false );
			};

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Label settings', 'ymove-nutrition' ) },
						el( SelectControl, {
							label: __( 'Show values per', 'ymove-nutrition' ),
							value: attributes.per,
							options: [
								{ label: __( 'Serving', 'ymove-nutrition' ), value: 'serving' },
								{ label: __( '100 g', 'ymove-nutrition' ), value: '100g' },
							],
							onChange: ( per ) => setAttributes( { per } ),
						} ),
						el( TextControl, {
							label: __( 'Custom title', 'ymove-nutrition' ),
							value: attributes.title || '',
							onChange: ( title ) => setAttributes( { title: title || undefined } ),
							help: __( 'Leave empty to use the food name.', 'ymove-nutrition' ),
						} ),
						el( ToggleControl, {
							label: __( 'Add NutritionInformation schema (recipe SEO)', 'ymove-nutrition' ),
							checked: attributes.schema,
							onChange: ( schema ) => setAttributes( { schema } ),
						} ),
						el( Button, { variant: 'secondary', onClick: () => setPicking( true ) }, __( 'Change food', 'ymove-nutrition' ) )
					)
				),
				el(
					'div',
					props,
					! cfg.connected
						? el( Placeholder, { icon: 'clipboard', label: __( 'Nutrition Facts', 'ymove-nutrition' ) }, el( Notice, { status: 'warning', isDismissible: false }, __( 'Connect a Your Move API key to search foods.', 'ymove-nutrition' ), ' ', el( 'a', { href: cfg.settings }, __( 'Settings', 'ymove-nutrition' ) ) ) )
						: picking
						? el( Placeholder, { icon: 'clipboard', label: __( 'Nutrition Facts', 'ymove-nutrition' ) }, el( Search, { onPick: pick } ) )
						: el( Label, { food: attributes.food, per: attributes.per } )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )();
