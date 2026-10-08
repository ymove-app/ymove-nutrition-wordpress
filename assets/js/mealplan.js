/**
 * Meal Plan Generator block. Calls /ymove/v1/mealplan (proxied to
 * /mealplans/generate) and renders day tabs with recipe cards. Members can
 * add a meal straight into their diary.
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const t = cfg.i18n || {};
	const DIETS = { balanced: 'Balanced', high_protein: 'High protein', low_carb: 'Low carb', keto: 'Keto', vegan: 'Vegan', vegetarian: 'Vegetarian', mediterranean: 'Mediterranean', paleo: 'Paleo' };
	const SPLITS = { balanced: 'Balanced macros', high_protein: 'High protein', low_carb: 'Low carb', high_fat: 'High fat' };

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
	const cap = ( s ) => String( s || '' ).charAt( 0 ).toUpperCase() + String( s || '' ).slice( 1 );

	async function api( path, opts = {} ) {
		const r = await fetch( restUrl( path ), {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, ...( opts.body ? { 'Content-Type': 'application/json' } : {} ) },
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} );
		const body = await r.json().catch( () => ( {} ) );
		if ( ! r.ok ) throw Object.assign( new Error( body.message || t.error ), { status: r.status, code: body.code, upgradeUrl: body.upgradeUrl } );
		return body;
	}

	/* Captcha: same flow as the calculator lead form. */
	function captchaToken( lead ) {
		const c = cfg.captcha || {};
		if ( ! c.provider || ! c.siteKey ) return Promise.resolve( '' );
		if ( c.provider === 'recaptcha' ) {
			return new Promise( ( resolve ) => {
				const g = window.grecaptcha;
				if ( ! g ) return resolve( '' );
				g.ready( () => g.execute( c.siteKey, { action: 'ymove_lead' } ).then( resolve, () => resolve( '' ) ) );
			} );
		}
		return Promise.resolve( lead.dataset.turnstileToken || '' );
	}
	function mountTurnstile( lead ) {
		const c = cfg.captcha || {};
		const box = lead.querySelector( '.ymn-captcha[data-provider="turnstile"]' );
		if ( ! box || c.provider !== 'turnstile' || ! c.siteKey ) return;
		const render = () => {
			if ( ! window.turnstile ) return setTimeout( render, 300 );
			window.turnstile.render( box, { sitekey: c.siteKey, size: 'flexible', callback: ( tk ) => ( lead.dataset.turnstileToken = tk ), 'expired-callback': () => ( lead.dataset.turnstileToken = '' ) } );
		};
		render();
	}

	function init( root ) {
		const c = JSON.parse( root.dataset.config || '{}' );
		const mount = root.querySelector( '.ymn-mount' );
		let plan = null;
		let token = '';
		let dayIndex = 0;
		let open = new Set();

		// Default calories: block attribute, else the visitor's last calculator result, else 2000.
		let calories = c.calories || 0;
		if ( ! calories ) {
			try {
				const last = JSON.parse( localStorage.getItem( 'ymn_calc' ) );
				if ( last && last.target ) calories = last.target;
			} catch ( e ) {
				/* ignore */
			}
		}
		calories = calories || 2000;

		const select = ( name, options, value ) => h( 'select', { name }, Object.entries( options ).map( ( [ v, l ] ) => h( 'option', { value: v, selected: v === String( value ) }, l ) ) );
		const numOpts = ( a, b ) => Object.fromEntries( Array.from( { length: b - a + 1 }, ( _, i ) => [ a + i, String( a + i ) ] ) );

		const status = h( 'p', { class: 'ymn-status', role: 'status' } );
		const output = h( 'div', { class: 'ymn-plan' } );
		const deliver = h( 'div', { class: 'ymn-plan-deliver', hidden: true } );
		const form = h(
			'form',
			{
				class: 'ymn-form ymn-plan-form',
				novalidate: true,
				onSubmit: ( ev ) => {
					ev.preventDefault();
					generate( false );
				},
			},
			h(
				'div',
				{ class: 'ymn-grid' },
				h( 'label', null, h( 'span', null, 'Daily calories' ), h( 'input', { type: 'number', name: 'calories', min: 800, max: 8000, step: 1, value: calories, inputmode: 'numeric' } ) ),
				h( 'label', null, h( 'span', null, 'Diet' ), select( 'diet', DIETS, c.diet || 'balanced' ) ),
				h( 'label', null, h( 'span', null, 'Meals per day' ), select( 'meals', numOpts( 3, 6 ), c.meals || 3 ) ),
				h( 'label', null, h( 'span', null, 'Days' ), select( 'days', numOpts( 1, 7 ), c.days || 1 ) ),
				h( 'label', { class: 'ymn-wide' }, h( 'span', null, 'Macro focus' ), select( 'macroSplit', SPLITS, 'balanced' ) )
			),
			h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'submit' }, 'Generate meal plan' )
		);
		mount.append( form, status, output, deliver );
		buildDeliver();

		async function generate( fresh ) {
			const f = form.elements;
			const q = new URLSearchParams( { calories: f.calories.value, diet: f.diet.value, meals: f.meals.value, days: f.days.value, macroSplit: f.macroSplit.value } );
			if ( fresh ) q.set( 'fresh', '1' );
			form.querySelector( 'button' ).disabled = true;
			status.textContent = t.generating || 'Building your plan...';
			output.innerHTML = '';
			deliver.hidden = true;
			try {
				const res = await api( 'mealplan?' + q );
				plan = res.data;
				token = res.token || '';
				dayIndex = 0;
				open = new Set();
				status.textContent = '';
				renderPlan();
				deliver.hidden = ! deliver.childNodes.length;
				output.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			} catch ( e ) {
				status.textContent = e.status === 429 ? t.throttled : e.message || t.error;
				if ( e.upgradeUrl ) status.append( ' ', h( 'a', { href: e.upgradeUrl, target: '_blank', rel: 'noopener' }, 'Upgrade' ) );
			} finally {
				form.querySelector( 'button' ).disabled = false;
			}
		}

		function renderPlan() {
			output.innerHTML = '';
			const days = plan.days || [ { dayIndex: 1, totals: plan.totals, meals: plan.meals } ];
			const day = days[ dayIndex ];
			const avg = plan.averageDailyTotals || day.totals;

			output.append(
				h(
					'div',
					{ class: 'ymn-plan-head' },
					h( 'div', null, h( 'strong', null, `${ fmt( plan.calories ) } kcal` ), ` · ${ DIETS[ plan.diet ] || cap( plan.diet ) } · ${ plan.mealsPerDay } meals/day` ),
					h( 'div', { class: 'ymn-plan-avg' }, `Avg per day: ${ fmt( avg.calories ) } kcal · P ${ fmt( avg.protein ) } g · C ${ fmt( avg.carbs ) } g · F ${ fmt( avg.fat ) } g` ),
					h( 'button', { class: 'ymn-link', type: 'button', onClick: () => generate( true ) }, 'Shuffle recipes' )
				)
			);
			if ( days.length > 1 ) {
				output.append(
					h(
						'div',
						{ class: 'ymn-tabs ymn-plan-days' },
						days.map( ( d, i ) =>
							h( 'button', { class: 'ymn-tab' + ( i === dayIndex ? ' is-active' : '' ), type: 'button', onClick: () => {
								dayIndex = i;
								renderPlan();
							} }, `${ t.day || 'Day' } ${ d.dayIndex || i + 1 }` )
						)
					)
				);
			}
			output.append( h( 'p', { class: 'ymn-plan-daytotal' }, `${ fmt( day.totals.calories ) } kcal · P ${ fmt( day.totals.protein ) } · C ${ fmt( day.totals.carbs ) } · F ${ fmt( day.totals.fat ) }` ) );
			output.append( h( 'div', { class: 'ymn-plan-meals' }, ( day.meals || [] ).map( ( m, i ) => mealCard( m, `${ dayIndex }-${ i }`, i ) ) ) );
		}

		function mealCard( m, key, index ) {
			const r = m.recipe || {};
			const isOpen = open.has( key );
			const card = h(
				'article',
				{ class: 'ymn-recipe' + ( isOpen ? ' is-open' : '' ) },
				m.imageUrl || r.imageUrl ? h( 'img', { class: 'ymn-recipe-img', src: m.imageUrl || r.imageUrl, alt: '', loading: 'lazy' } ) : h( 'div', { class: 'ymn-recipe-img ymn-recipe-img-empty' } ),
				h(
					'div',
					{ class: 'ymn-recipe-body' },
					h( 'div', { class: 'ymn-recipe-type' }, ( t.meals || {} )[ m.type ] || cap( m.type ) ),
					h( 'h3', { class: 'ymn-recipe-title' }, m.name || r.title ),
					h(
						'div',
						{ class: 'ymn-recipe-meta' },
						h( 'strong', null, `${ fmt( m.calories ) } kcal` ),
						` · P ${ fmt( m.protein ) } · C ${ fmt( m.carbs ) } · F ${ fmt( m.fat ) }`,
						r.prepTimeMin || r.cookTimeMin ? ` · ${ ( r.prepTimeMin || 0 ) + ( r.cookTimeMin || 0 ) } min` : null,
						m.portionMultiplier && m.portionMultiplier !== 1 ? ` · ${ fmt( m.portionMultiplier, 2 ) }× portion` : null
					),
					h(
						'div',
						{ class: 'ymn-recipe-actions' },
						c.showRecipes !== false && ( ( m.foods && m.foods.length ) || ( r.instructions && r.instructions.length ) )
							? h( 'button', { class: 'ymn-btn ymn-btn-small', type: 'button', onClick: () => {
									isOpen ? open.delete( key ) : open.add( key );
									renderPlan();
								} }, isOpen ? 'Hide recipe' : 'Recipe' )
							: null,
						token ? swapButton( index ) : null,
						c.canLog ? logButton( m ) : null
					),
					isOpen && recipeDetail( m, r )
				)
			);
			return card;
		}

		function recipeDetail( m, r ) {
			return h(
				'div',
				{ class: 'ymn-recipe-detail' },
				r.description ? h( 'p', { class: 'ymn-note' }, r.description ) : null,
				m.foods && m.foods.length
					? h( 'div', null, h( 'h4', null, t.ingredients || 'Ingredients' ), h( 'ul', { class: 'ymn-ingredients' }, m.foods.map( ( f ) => h( 'li', null, h( 'span', null, f.portion ), ' ', f.name, h( 'small', null, ` ${ fmt( f.calories ) } kcal` ) ) ) ) )
					: null,
				r.instructions && r.instructions.length
					? h( 'div', null, h( 'h4', null, t.method || 'Method' ), h( 'ol', { class: 'ymn-steps' }, r.instructions.map( ( s ) => h( 'li', null, s ) ) ) )
					: null,
				r.servings ? h( 'p', { class: 'ymn-note' }, `Recipe makes ${ r.servings } ${ t.servings || 'servings' }; the numbers above are for your portion.` ) : null
			);
		}

		function swapButton( index ) {
			const day = dayIndex;
			const btn = h( 'button', { class: 'ymn-btn ymn-btn-small', type: 'button', title: 'Swap for another recipe' }, '↻ Swap' );
			btn.addEventListener( 'click', async () => {
				btn.disabled = true;
				btn.textContent = '…';
				try {
					const res = await api( 'mealplan/swap', { method: 'POST', body: { token, day, index } } );
					plan.days[ day ].meals[ index ] = res.meal;
					plan.days[ day ].totals = res.dayTotals;
					plan.averageDailyTotals = res.averageDailyTotals;
					open.delete( `${ day }-${ index }` );
					renderPlan();
				} catch ( e ) {
					btn.disabled = false;
					btn.textContent = '↻ Swap';
					status.textContent = e.status === 429 ? t.throttled : e.message || t.error;
				}
			} );
			return btn;
		}

		/* ------------------------------------------ take the plan home */

		function buildDeliver() {
			const mode = c.delivery || 'off';
			if ( mode === 'off' ) return;
			const row = h( 'div', { class: 'ymn-plan-deliver-head' }, h( 'strong', null, 'Take your plan with you' ) );
			deliver.append( row );
			if ( mode === 'pdf' || mode === 'both' ) {
				row.append( h( 'button', { class: 'ymn-btn ymn-btn-secondary ymn-btn-small', type: 'button', onClick: printPlan }, '⤓ Save as PDF' ) );
			}
			if ( mode === 'email' || mode === 'both' ) deliver.append( emailForm() );
		}

		function emailForm() {
			const cap = cfg.captcha || {};
			const err = h( 'p', { class: 'ymn-lead-error', role: 'alert', hidden: true } );
			const done = h( 'p', { class: 'ymn-lead-done', hidden: true }, 'Sent - check your inbox for your full meal plan.' );
			const lead = h(
				'form',
				{ class: 'ymn-lead', novalidate: true },
				h( 'label', null, h( 'span', null, 'Email me this plan with all recipes' ),
					h( 'span', { class: 'ymn-inline' },
						h( 'input', { type: 'email', name: 'email', required: true, placeholder: 'you@example.com', autocomplete: 'email' } ),
						h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'submit' }, 'Send' ) ) ),
				c.consent ? h( 'label', { class: 'ymn-consent' }, h( 'input', { type: 'checkbox', name: 'consent', value: '1', required: true } ), ' ', h( 'span', null, c.consent ) ) : null,
				cap.provider === 'turnstile' && cap.siteKey ? h( 'div', { class: 'ymn-captcha', 'data-provider': 'turnstile' } ) : null,
				h( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'ymn-hp', 'aria-hidden': 'true' } ),
				err,
				done
			);
			mountTurnstile( lead );
			lead.addEventListener( 'submit', async ( ev ) => {
				ev.preventDefault();
				err.hidden = true;
				const f = lead.elements;
				if ( ! f.email.value || ! f.email.checkValidity() ) {
					err.textContent = 'Please enter a valid email address.';
					err.hidden = false;
					return;
				}
				if ( f.consent && ! f.consent.checked ) {
					err.textContent = 'Please tick the consent box.';
					err.hidden = false;
					return;
				}
				const btn = lead.querySelector( 'button' );
				btn.disabled = true;
				try {
					await api( 'mealplan/email', { method: 'POST', body: {
						token,
						email: f.email.value,
						consent: f.consent ? f.consent.checked : false,
						website: f.website.value,
						captcha: await captchaToken( lead ),
						page: location.href,
					} } );
					lead.querySelectorAll( 'label' ).forEach( ( l ) => ( l.hidden = true ) );
					done.hidden = false;
				} catch ( e ) {
					err.textContent = e.status === 429 ? t.throttled : e.message || t.error;
					err.hidden = false;
					btn.disabled = false;
				}
			} );
			return lead;
		}

		/**
		 * Full plan in a clean print window; the browser's "Save as PDF"
		 * makes the file. Built with DOM nodes, so recipe text is never HTML.
		 */
		function printPlan() {
			const w = window.open( '', '_blank' );
			if ( ! w ) {
				status.textContent = 'Allow pop-ups for this site to save the PDF.';
				return;
			}
			const d = w.document;
			d.open();
			d.write( '<!doctype html><html><head><meta charset="utf-8"><title></title></head><body></body></html>' );
			d.close();
			d.title = `Meal plan - ${ c.site || '' }`;
			const style = d.createElement( 'style' );
			style.textContent = `
				@page { margin: 16mm; }
				body { font: 13px/1.5 -apple-system, Segoe UI, Helvetica, Arial, sans-serif; color: #111827; max-width: 760px; margin: 24px auto; padding: 0 16px; }
				h1 { font-size: 24px; margin: 0 0 4px; } .sub { color: #6b7280; margin: 0 0 20px; }
				h2 { font-size: 18px; margin: 28px 0 2px; padding-top: 12px; border-top: 2px solid #111827; break-after: avoid; }
				.meal { display: grid; grid-template-columns: 180px 1fr; gap: 16px; padding: 16px 0; border-top: 1px solid #e5e7eb; }
				.meal img { width: 180px; height: 130px; object-fit: cover; border-radius: 10px; }
				.head { break-inside: avoid; } .kicker { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
				h3 { font-size: 16px; margin: 2px 0; } .meta { color: #6b7280; font-size: 12px; margin: 0 0 6px; }
				h4 { font-size: 11px; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; margin: 10px 0 4px; }
				ul, ol { margin: 0; padding-left: 18px; } li { margin: 0 0 2px; }
				.foot { margin-top: 24px; color: #9ca3af; font-size: 11px; }
				@media print { .noprint { display: none; } }`;
			d.head.append( style );
			const x = ( tag, cls, ...kids ) => {
				const e = d.createElement( tag );
				if ( cls ) e.className = cls;
				kids.flat().forEach( ( k ) => k !== null && k !== undefined && e.append( k instanceof w.Node ? k : d.createTextNode( String( k ) ) ) );
				return e;
			};
			const avg = plan.averageDailyTotals || {};
			const days = plan.days || [];
			d.body.append(
				x( 'h1', null, c.site ? `${ c.site }: your meal plan` : 'Your meal plan' ),
				x( 'p', 'sub', `${ fmt( plan.calories ) } kcal target · ${ DIETS[ plan.diet ] || cap( plan.diet ) } · ${ plan.mealsPerDay } meals/day · avg ${ fmt( avg.calories ) } kcal, P ${ fmt( avg.protein ) } g, C ${ fmt( avg.carbs ) } g, F ${ fmt( avg.fat ) } g` )
			);
			days.forEach( ( day, di ) => {
				if ( days.length > 1 ) d.body.append( x( 'h2', null, `${ t.day || 'Day' } ${ day.dayIndex || di + 1 } · ${ fmt( day.totals.calories ) } kcal` ) );
				( day.meals || [] ).forEach( ( m ) => {
					const r = m.recipe || {};
					const img = m.imageUrl || r.imageUrl;
					const time = ( r.prepTimeMin || 0 ) + ( r.cookTimeMin || 0 );
					const imgEl = img ? Object.assign( d.createElement( 'img' ), { src: img, alt: '' } ) : x( 'div' );
					d.body.append(
						x( 'div', 'meal', imgEl, x( 'div', null,
							x( 'div', 'head',
								x( 'div', 'kicker', ( t.meals || {} )[ m.type ] || cap( m.type ) ),
								x( 'h3', null, m.name || r.title ),
								x( 'p', 'meta', `${ fmt( m.calories ) } kcal · P ${ fmt( m.protein ) } g · C ${ fmt( m.carbs ) } g · F ${ fmt( m.fat ) } g${ time ? ` · ${ time } min` : '' }` ) ),
							r.description ? x( 'p', null, r.description ) : null,
							m.foods && m.foods.length ? [ x( 'h4', null, t.ingredients || 'Ingredients' ), x( 'ul', null, m.foods.map( ( f ) => x( 'li', null, `${ f.portion } ${ f.name }` ) ) ) ] : null,
							r.instructions && r.instructions.length ? [ x( 'h4', null, t.method || 'Method' ), x( 'ol', null, r.instructions.map( ( st ) => x( 'li', null, st ) ) ) ] : null
						) )
					);
				} );
			} );
			d.body.append( x( 'p', 'foot', `${ c.site || '' } · ${ location.hostname } · Meal plan and recipes by Your Move Nutrition. Estimates, not medical advice.` ) );
			const imgs = [ ...d.images ];
			let printed = false;
			const go = () => {
				if ( printed ) return;
				printed = true;
				w.focus();
				w.print();
			};
			Promise.all( imgs.map( ( i ) => ( i.complete ? 0 : new Promise( ( r ) => ( i.onload = i.onerror = r ) ) ) ) ).then( go );
			setTimeout( go, 4000 );
		}

		function logButton( m ) {
			const meal = [ 'breakfast', 'lunch', 'dinner', 'snack' ].includes( m.type ) ? m.type : 'snack';
			const btn = h( 'button', { class: 'ymn-btn ymn-btn-small ymn-btn-primary', type: 'button' }, t.addToDiary || 'Add to diary' );
			btn.addEventListener( 'click', async () => {
				btn.disabled = true;
				try {
					await api( 'diary', {
						method: 'POST',
						body: {
							meal,
							source: 'mealplan',
							displayName: m.name || ( m.recipe && m.recipe.title ),
							servingG: 0,
							quantity: 1,
							calories: m.calories,
							protein: m.protein,
							carbs: m.carbs,
							fat: m.fat,
						},
					} );
					btn.textContent = '✓ ' + ( t.added || 'Added' );
				} catch ( e ) {
					btn.disabled = false;
					btn.textContent = e.message || t.error;
				}
			} );
			return btn;
		}
	}

	document.querySelectorAll( '.ymn-mealplan' ).forEach( init );
} )();
