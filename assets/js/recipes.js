/**
 * Recipes block. Browse mode calls /ymove/v1/recipes (proxied to
 * /recipes/search) and opens a recipe in place; single mode is rendered
 * by PHP and only gets the members' add-to-diary button here.
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const t = cfg.i18n || {};
	const MEALS = { '': 'Any meal', breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', snack: 'Snack', pre_workout: 'Pre-workout', post_workout: 'Post-workout', drink: 'Drink' };
	const DIETS = { '': 'Any diet', high_protein: 'High protein', low_carb: 'Low carb', keto: 'Keto', vegan: 'Vegan', vegetarian: 'Vegetarian', mediterranean: 'Mediterranean', paleo: 'Paleo' };
	const CALS = { 0: 'Any calories', 300: 'Under 300 kcal', 500: 'Under 500 kcal', 700: 'Under 700 kcal' };

	function h( tag, attrs, ...children ) {
		const el = document.createElement( tag );
		for ( const [ k, v ] of Object.entries( attrs || {} ) ) {
			if ( v === null || v === undefined || v === false ) continue;
			if ( k === 'class' ) el.className = v;
			else if ( k.startsWith( 'on' ) ) el.addEventListener( k.slice( 2 ).toLowerCase(), v );
			else if ( v === true ) el.setAttribute( k, '' );
			else el.setAttribute( k, v );
		}
		for ( const c of children.flat() ) {
			if ( c === null || c === undefined || c === false ) continue;
			el.append( c instanceof Node ? c : document.createTextNode( String( c ) ) );
		}
		return el;
	}
	const fmt = ( n, d = 0 ) => Number( n || 0 ).toLocaleString( undefined, { maximumFractionDigits: d } );
	const label = ( s ) => String( s || '' ).replace( /_/g, ' ' ).replace( /^./, ( c ) => c.toUpperCase() );

	async function api( path, opts = {} ) {
		const r = await fetch( restUrl( path ), {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, ...( opts.body ? { 'Content-Type': 'application/json' } : {} ) },
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} );
		const body = await r.json().catch( () => ( {} ) );
		if ( ! r.ok ) throw Object.assign( new Error( body.message || t.error ), { status: r.status, code: body.code } );
		return body;
	}

	function logButton( r ) {
		const meal = [ 'breakfast', 'lunch', 'dinner', 'snack' ].includes( r.mealType ) ? r.mealType : 'snack';
		const btn = h( 'button', { class: 'ymn-btn ymn-btn-small ymn-btn-primary', type: 'button' }, t.addToDiary || 'Add to diary' );
		btn.addEventListener( 'click', async () => {
			btn.disabled = true;
			try {
				await api( 'diary', {
					method: 'POST',
					body: { meal, source: 'recipe', displayName: r.displayName || r.title, servingG: 0, quantity: 1, calories: r.calories, protein: r.protein, carbs: r.carbs, fat: r.fat },
				} );
				btn.textContent = '✓ ' + ( t.added || 'Added' );
			} catch ( e ) {
				btn.disabled = false;
				btn.textContent = e.message || t.error;
			}
		} );
		return btn;
	}

	/* Same markup as Blocks::recipe_html() in PHP. */
	function recipeView( r, canLog ) {
		const time = ( r.prepTimeMin || 0 ) + ( r.cookTimeMin || 0 );
		const chips = [ r.mealType && label( r.mealType ), time && `${ time } min`, `${ r.servings } ${ t.servings || 'servings' }`, r.difficulty && label( r.difficulty ) ].filter( Boolean );
		return h(
			'article',
			{ class: 'ymn-rx' },
			r.imageUrl ? h( 'img', { class: 'ymn-rx-img', src: r.imageUrl, alt: r.title } ) : null,
			h(
				'div',
				{ class: 'ymn-rx-body' },
				h( 'h2', { class: 'ymn-rx-title' }, r.title ),
				h( 'div', { class: 'ymn-chips' }, chips.map( ( c ) => h( 'span', { class: 'ymn-chip' }, c ) ), ( r.dietTags || [] ).map( ( d ) => h( 'span', { class: 'ymn-chip ymn-chip-diet' }, label( d ) ) ) ),
				r.description ? h( 'p', { class: 'ymn-rx-desc' }, r.description ) : null,
				h(
					'div',
					{ class: 'ymn-rx-macros' },
					[ [ fmt( r.calories ), t.kcal || 'kcal' ], [ fmt( r.protein ) + ' g', t.protein || 'Protein' ], [ fmt( r.carbs ) + ' g', t.carbs || 'Carbs' ], [ fmt( r.fat ) + ' g', t.fat || 'Fat' ] ].map( ( [ v, l ] ) => h( 'div', null, h( 'strong', null, v ), h( 'span', null, l ) ) )
				),
				h( 'p', { class: 'ymn-rx-per' }, 'Nutrition ' + ( t.perServing || 'per serving' ) ),
				canLog ? h( 'div', { class: 'ymn-recipe-actions' }, logButton( r ) ) : null,
				h(
					'div',
					{ class: 'ymn-rx-cols' },
					r.ingredients && r.ingredients.length
						? h( 'section', null, h( 'h3', null, t.ingredients || 'Ingredients' ), h( 'ul', { class: 'ymn-ingredients' }, r.ingredients.map( ( i ) => h( 'li', null, [ i.quantity !== null && i.quantity !== undefined ? `${ fmt( i.quantity, 2 ) } ${ i.unit || '' }`.trim() : '', i.name ].filter( Boolean ).join( ' ' ) ) ) ) )
						: null,
					r.instructions && r.instructions.length ? h( 'section', null, h( 'h3', null, t.method || 'Method' ), h( 'ol', { class: 'ymn-steps' }, r.instructions.map( ( s ) => h( 'li', null, s ) ) ) ) : null
				),
				r.source
					? h( 'p', { class: 'ymn-rx-attr' }, ( t.adaptedFrom || 'Adapted from' ) + ' ', h( 'a', { href: r.source.url, target: '_blank', rel: 'noopener nofollow' }, r.source.title ), r.source.license ? ` (${ r.source.license })` : '' )
					: null
			)
		);
	}

	function card( r, onOpen ) {
		const time = ( r.prepTimeMin || 0 ) + ( r.cookTimeMin || 0 );
		return h(
			'button',
			{ class: 'ymn-rcard', type: 'button', onClick: () => onOpen( r ) },
			r.imageUrl ? h( 'img', { class: 'ymn-rcard-img', src: r.imageUrl, alt: '', loading: 'lazy' } ) : h( 'span', { class: 'ymn-rcard-img ymn-recipe-img-empty' } ),
			h(
				'span',
				{ class: 'ymn-rcard-body' },
				h( 'span', { class: 'ymn-recipe-type' }, label( r.mealType ) ),
				h( 'span', { class: 'ymn-rcard-title' }, r.title ),
				h( 'span', { class: 'ymn-recipe-meta' }, h( 'strong', null, `${ fmt( r.calories ) } kcal` ), ` · P ${ fmt( r.protein ) } g`, time ? ` · ${ time } min` : '' )
			)
		);
	}

	function initBrowser( root ) {
		const c = JSON.parse( root.dataset.config || '{}' );
		const mount = root.querySelector( '.ymn-mount' );
		const pageSize = c.pageSize || 9;
		let page = 1;
		let seq = 0;
		let scrollY = 0;

		const select = ( name, options, value ) => h( 'select', { name, 'aria-label': options[ '' ] || options[ 0 ] }, Object.entries( options ).map( ( [ v, l ] ) => h( 'option', { value: v, selected: v === String( value || '' ) }, l ) ) );
		const form = h(
			'form',
			{ class: 'ymn-form ymn-rfilters', role: 'search', hidden: c.showFilters === false, onSubmit: ( ev ) => {
				ev.preventDefault();
				load( true );
			} },
			h( 'input', { type: 'search', name: 'q', value: c.q || '', placeholder: 'Search recipes or ingredients', 'aria-label': 'Search recipes' } ),
			select( 'mealType', MEALS, c.mealType ),
			select( 'diet', DIETS, c.diet ),
			select( 'maxCalories', CALS[ c.maxCalories ] ? CALS : { ...CALS, [ c.maxCalories ]: `Under ${ c.maxCalories } kcal` }, c.maxCalories || 0 ),
			h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'submit' }, 'Search' )
		);
		form.querySelectorAll( 'select' ).forEach( ( s ) => s.addEventListener( 'change', () => load( true ) ) );

		const status = h( 'p', { class: 'ymn-status', role: 'status' } );
		const grid = h( 'div', { class: 'ymn-rgrid' } );
		const more = h( 'button', { class: 'ymn-btn ymn-btn-secondary ymn-rmore', type: 'button', hidden: true, onClick: () => load( false ) }, 'Load more recipes' );
		const list = h( 'div', null, form, status, grid, more );
		const detail = h( 'div', { class: 'ymn-rdetail', hidden: true } );
		mount.replaceChildren( list, detail );

		async function load( reset ) {
			const id = ++seq;
			page = reset ? 1 : page + 1;
			const f = form.elements;
			const q = new URLSearchParams( { q: f.q.value.trim(), mealType: f.mealType.value, diet: f.diet.value, maxCalories: f.maxCalories.value, page, pageSize } );
			if ( reset ) grid.replaceChildren();
			status.textContent = t.loading || 'Loading...';
			more.hidden = true;
			try {
				const res = await api( 'recipes?' + q );
				if ( id !== seq ) return;
				const rows = res.data || [];
				grid.append( ...rows.map( ( r ) => card( r, open ) ) );
				const p = res.pagination || {};
				status.textContent = ! grid.childNodes.length ? t.noRecipes || 'No recipes match.' : '';
				more.hidden = ! ( p.totalPages && page < p.totalPages );
			} catch ( e ) {
				if ( id !== seq ) return;
				status.textContent = e.status === 429 ? t.throttled : e.message || t.error;
			}
		}

		async function open( summary ) {
			scrollY = window.scrollY;
			list.hidden = true;
			detail.hidden = false;
			const back = h( 'button', { class: 'ymn-link ymn-rback', type: 'button', onClick: close }, '← Back to recipes' );
			detail.replaceChildren( back, h( 'p', { class: 'ymn-status' }, t.loading || 'Loading...' ) );
			root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			try {
				const res = await api( 'recipes/' + encodeURIComponent( summary.slug || summary.id ) );
				if ( detail.hidden ) return;
				detail.replaceChildren( back, recipeView( res.data, c.canLog ) );
			} catch ( e ) {
				detail.replaceChildren( back, h( 'p', { class: 'ymn-error' }, e.status === 429 ? t.throttled : e.message || t.error ) );
			}
		}

		function close() {
			detail.hidden = true;
			detail.replaceChildren();
			list.hidden = false;
			window.scrollTo( { top: scrollY } );
		}

		load( true );
	}

	document.querySelectorAll( '.ymn-recipes[data-config]' ).forEach( initBrowser );
	if ( cfg.canTrack ) {
		document.querySelectorAll( '.ymn-recipes-single .ymn-rx[data-log]' ).forEach( ( art ) => {
			const actions = art.querySelector( '.ymn-recipe-actions' );
			if ( actions ) actions.append( logButton( JSON.parse( art.dataset.log ) ) );
		} );
	}
} )();
