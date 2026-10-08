/**
 * Calorie tracker UI for logged-in members. Talks only to this site's
 * REST proxy (/wp-json/ymove/v1). Vanilla DOM, no framework on the page.
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const t = cfg.i18n || {};
	const MEALS = [ 'breakfast', 'lunch', 'dinner', 'snack' ];

	/* ------------------------------------------------------------ helpers */

	function h( tag, attrs, ...children ) {
		const el = document.createElement( tag );
		for ( const [ k, v ] of Object.entries( attrs || {} ) ) {
			if ( v === null || v === undefined || v === false ) continue;
			if ( k === 'class' ) el.className = v;
			else if ( k.startsWith( 'on' ) ) el.addEventListener( k.slice( 2 ).toLowerCase(), v );
			else if ( k === 'html' ) el.innerHTML = v;
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
	const todayISO = () => {
		const d = new Date();
		return `${ d.getFullYear() }-${ String( d.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( d.getDate() ).padStart( 2, '0' ) }`;
	};
	const shiftDay = ( iso, n ) => {
		const d = new Date( iso + 'T12:00:00' );
		d.setDate( d.getDate() + n );
		return d.toISOString().slice( 0, 10 );
	};
	const dayLabel = ( iso ) => {
		if ( iso === todayISO() ) return t.today || 'Today';
		return new Date( iso + 'T12:00:00' ).toLocaleDateString( undefined, { weekday: 'short', day: 'numeric', month: 'short' } );
	};
	const foodName = ( f ) => f.displayName || f.shortName || f.name || '';

	class ApiError extends Error {
		constructor( body, status ) {
			super( body.message || t.error || 'Error' );
			this.code = body.code;
			this.status = status;
			this.upgradeUrl = body.upgradeUrl;
		}
	}
	async function api( path, opts = {} ) {
		const r = await fetch( restUrl( path ), {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, ...( opts.body ? { 'Content-Type': 'application/json' } : {} ) },
			body: opts.body ? JSON.stringify( opts.body ) : undefined,
		} );
		const body = await r.json().catch( () => ( {} ) );
		if ( ! r.ok ) throw new ApiError( body, r.status );
		return body;
	}
	const friendly = ( e ) => {
		if ( e.status === 429 ) return t.throttled;
		if ( e.code === 'ymn_plan' ) return t.upgrade;
		if ( e.status === 404 ) return t.notFound;
		return e.message || t.error;
	};

	/* ------------------------------------------------------------ tracker */

	function Tracker( root ) {
		const features = JSON.parse( root.dataset.features || '{}' );
		const mount = root.querySelector( '.ymn-mount' );
		const state = { date: todayISO(), day: null, week: null, view: 'day', adding: null, recent: null };

		/* --------------------------------------------------- data loading */
		async function loadDay() {
			state.day = await api( 'diary?date=' + state.date );
			render();
		}
		async function loadWeek() {
			state.week = await api( 'diary/week?to=' + state.date );
			render();
		}
		async function loadRecent() {
			if ( state.recent ) return state.recent;
			state.recent = ( await api( 'diary/recent' ) ).data || [];
			return state.recent;
		}

		/* ----------------------------------------------------- rendering */
		function render() {
			mount.innerHTML = '';
			mount.append( header() );
			if ( state.view === 'week' ) {
				mount.append( weekView() );
				return;
			}
			if ( ! state.day ) {
				mount.append( h( 'p', { class: 'ymn-loading' }, t.loading ) );
				return;
			}
			mount.append( summary() );
			MEALS.forEach( ( m ) => mount.append( mealSection( m ) ) );
			if ( state.adding ) mount.append( addSheet( state.adding ) );
		}

		function header() {
			return h(
				'div',
				{ class: 'ymn-head' },
				h(
					'div',
					{ class: 'ymn-datenav' },
					h( 'button', { class: 'ymn-icon', type: 'button', 'aria-label': 'Previous day', onClick: () => go( -1 ) }, '‹' ),
					h( 'button', { class: 'ymn-datelabel', type: 'button', onClick: () => go( 0 ) }, dayLabel( state.date ) ),
					h( 'button', { class: 'ymn-icon', type: 'button', 'aria-label': 'Next day', disabled: state.date >= todayISO(), onClick: () => go( 1 ) }, '›' )
				),
				h(
					'div',
					{ class: 'ymn-tabs' },
					h( 'button', { class: 'ymn-tab' + ( state.view === 'day' ? ' is-active' : '' ), type: 'button', onClick: () => setView( 'day' ) }, 'Day' ),
					features.week !== false && h( 'button', { class: 'ymn-tab' + ( state.view === 'week' ? ' is-active' : '' ), type: 'button', onClick: () => setView( 'week' ) }, 'Week' ),
					h( 'button', { class: 'ymn-tab', type: 'button', onClick: exportCsv, title: 'Download my diary as CSV' }, 'Export' )
				)
			);
		}

		function go( n ) {
			state.date = n === 0 ? todayISO() : shiftDay( state.date, n );
			state.day = null;
			state.week = null;
			state.adding = null;
			render();
			state.view === 'week' ? loadWeek() : loadDay();
		}
		function setView( v ) {
			state.view = v;
			state.adding = null;
			render();
			if ( v === 'week' && ! state.week ) loadWeek();
		}

		function bar( label, value, target, unit = 'g' ) {
			const pct = target > 0 ? Math.min( 100, ( value / target ) * 100 ) : 0;
			return h(
				'div',
				{ class: 'ymn-bar' },
				h( 'div', { class: 'ymn-bar-head' }, h( 'span', null, label ), h( 'span', null, `${ fmt( value ) } / ${ fmt( target ) } ${ unit }` ) ),
				h( 'div', { class: 'ymn-bar-track' }, h( 'div', { class: 'ymn-bar-fill' + ( value > target && target > 0 ? ' is-over' : '' ), style: `width:${ pct }%` } ) )
			);
		}

		function summary() {
			const { totals, targets } = state.day;
			const left = targets.kcal - totals.kcal;
			return h(
				'div',
				{ class: 'ymn-summary' },
				h(
					'div',
					{ class: 'ymn-kcal' },
					h( 'div', { class: 'ymn-kcal-big' }, h( 'strong', null, fmt( totals.kcal ) ), h( 'span', null, ` / ${ fmt( targets.kcal ) } ${ t.kcal || 'kcal' }` ) ),
					h( 'div', { class: 'ymn-kcal-left' + ( left < 0 ? ' is-over' : '' ) }, `${ fmt( Math.abs( left ) ) } ${ left < 0 ? t.over || 'over' : t.remaining || 'remaining' }` ),
					h( 'button', { class: 'ymn-link', type: 'button', onClick: editTargets }, targets.isDefault ? 'Set my targets' : 'Edit targets' )
				),
				h( 'div', { class: 'ymn-bars' }, bar( t.protein || 'Protein', totals.protein, targets.protein ), bar( t.carbs || 'Carbs', totals.carbs, targets.carbs ), bar( t.fat || 'Fat', totals.fat, targets.fat ) )
			);
		}

		function mealSection( meal ) {
			const entries = state.day.entries.filter( ( e ) => e.meal === meal );
			const kcal = entries.reduce( ( s, e ) => s + e.kcal, 0 );
			return h(
				'section',
				{ class: 'ymn-meal' },
				h(
					'header',
					{ class: 'ymn-meal-head' },
					h( 'h3', null, ( t.meals || {} )[ meal ] || meal ),
					h( 'span', { class: 'ymn-meal-kcal' }, entries.length ? `${ fmt( kcal ) } ${ t.kcal || 'kcal' }` : '' ),
					h( 'button', { class: 'ymn-btn ymn-btn-small', type: 'button', onClick: () => openAdd( meal ) }, '+ ' + ( t.add || 'Add' ) )
				),
				entries.length
					? h(
							'ul',
							{ class: 'ymn-entries' },
							entries.map( ( e ) =>
								h(
									'li',
									{ class: 'ymn-entry' },
									h(
										'div',
										{ class: 'ymn-entry-main' },
										h( 'span', { class: 'ymn-entry-name' }, e.displayName, e.brand ? h( 'small', null, ' ' + e.brand ) : null ),
										h( 'span', { class: 'ymn-entry-qty' }, `${ fmt( e.quantity, 2 ) } × ${ fmt( e.servingG ) } g` )
									),
									h( 'div', { class: 'ymn-entry-macros' }, `P ${ fmt( e.protein ) } · C ${ fmt( e.carbs ) } · F ${ fmt( e.fat ) }` ),
									h( 'strong', { class: 'ymn-entry-kcal' }, fmt( e.kcal ) ),
									h( 'button', { class: 'ymn-icon ymn-del', type: 'button', 'aria-label': t.remove || 'Remove', onClick: () => removeEntry( e ) }, '×' )
								)
							)
					  )
					: h( 'p', { class: 'ymn-empty' }, 'Nothing logged yet.' )
			);
		}

		async function removeEntry( e ) {
			state.day.entries = state.day.entries.filter( ( x ) => x.id !== e.id );
			recalc();
			render();
			try {
				await api( 'diary/' + e.id, { method: 'DELETE' } );
			} catch ( err ) {
				loadDay();
			}
		}
		function recalc() {
			const tot = { kcal: 0, protein: 0, carbs: 0, fat: 0 };
			state.day.entries.forEach( ( e ) => {
				tot.kcal += e.kcal;
				tot.protein += e.protein;
				tot.carbs += e.carbs;
				tot.fat += e.fat;
			} );
			state.day.totals = tot;
		}

		function editTargets() {
			const tg = state.day.targets;
			const input = ( name, val ) => h( 'label', null, h( 'span', null, name ), h( 'input', { type: 'number', min: 0, name, value: val, inputmode: 'numeric' } ) );
			const form = h(
				'form',
				{
					class: 'ymn-targets',
					onSubmit: async ( ev ) => {
						ev.preventDefault();
						const f = ev.target.elements;
						const body = { kcal: +f.kcal.value, protein: +f.protein.value, carbs: +f.carbs.value, fat: +f.fat.value };
						state.day.targets = ( await api( 'targets', { method: 'PUT', body } ) ).data;
						render();
					},
				},
				h( 'div', { class: 'ymn-grid' }, input( 'kcal', tg.kcal ), input( 'protein', tg.protein ), input( 'carbs', tg.carbs ), input( 'fat', tg.fat ) ),
				h( 'div', { class: 'ymn-actions' }, h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'submit' }, 'Save targets' ), h( 'button', { class: 'ymn-btn', type: 'button', onClick: render }, t.cancel || 'Cancel' ) ),
				h( 'p', { class: 'ymn-note' }, 'Tip: the calorie calculator can fill these in for you.' )
			);
			mount.querySelector( '.ymn-summary' ).replaceWith( form );
		}

		/* ------------------------------------------------------- week view */
		function weekView() {
			if ( ! state.week ) return h( 'p', { class: 'ymn-loading' }, t.loading );
			const { days, targets } = state.week;
			const max = Math.max( targets.kcal * 1.2, ...days.map( ( d ) => d.kcal ) );
			const logged = days.filter( ( d ) => d.entries > 0 );
			const avg = logged.length ? logged.reduce( ( s, d ) => s + d.kcal, 0 ) / logged.length : 0;
			return h(
				'div',
				{ class: 'ymn-week' },
				h(
					'div',
					{ class: 'ymn-week-chart', style: `--target:${ ( targets.kcal / max ) * 100 }%` },
					days.map( ( d ) =>
						h(
							'div',
							{ class: 'ymn-week-col', title: `${ d.date}: ${ fmt( d.kcal ) } kcal` },
							h( 'div', { class: 'ymn-week-bar' + ( d.kcal > targets.kcal ? ' is-over' : '' ), style: `height:${ ( d.kcal / max ) * 100 }%` } ),
							h( 'span', { class: 'ymn-week-day' }, new Date( d.date + 'T12:00:00' ).toLocaleDateString( undefined, { weekday: 'narrow' } ) )
						)
					),
					h( 'div', { class: 'ymn-week-target' } )
				),
				h(
					'div',
					{ class: 'ymn-week-stats' },
					h( 'div', { class: 'ymn-stat' }, h( 'span', { class: 'ymn-stat-value' }, fmt( avg ) ), h( 'span', { class: 'ymn-stat-label' }, 'avg kcal on logged days' ) ),
					h( 'div', { class: 'ymn-stat' }, h( 'span', { class: 'ymn-stat-value' }, `${ logged.length }/7` ), h( 'span', { class: 'ymn-stat-label' }, 'days logged' ) ),
					h( 'div', { class: 'ymn-stat' }, h( 'span', { class: 'ymn-stat-value' }, fmt( targets.kcal ) ), h( 'span', { class: 'ymn-stat-label' }, 'daily target' ) )
				)
			);
		}

		/* -------------------------------------------------------- add sheet */
		function openAdd( meal ) {
			state.adding = { meal, tab: 'search', results: [], query: '', status: '', scanner: null, analysis: null };
			render();
			mount.querySelector( '.ymn-sheet input[name="q"]' )?.focus();
		}
		function closeAdd() {
			state.adding?.scanner?.stop();
			state.adding = null;
			render();
		}

		function addSheet( a ) {
			const tabs = [ [ 'search', 'Search' ], [ 'recent', 'Recent' ] ];
			if ( features.barcode !== false ) tabs.push( [ 'scan', 'Scan' ] );
			if ( features.photo !== false ) tabs.push( [ 'photo', 'Photo' ] );
			if ( features.text !== false ) tabs.push( [ 'text', 'Describe' ] );

			const body = h( 'div', { class: 'ymn-sheet-body' } );
			const sheet = h(
				'div',
				{ class: 'ymn-sheet', role: 'dialog', 'aria-label': 'Add food' },
				h(
					'div',
					{ class: 'ymn-sheet-head' },
					h( 'strong', null, `${ t.add || 'Add' } → ${ ( t.meals || {} )[ a.meal ] || a.meal }` ),
					h( 'button', { class: 'ymn-icon', type: 'button', 'aria-label': 'Close', onClick: closeAdd }, '×' )
				),
				h(
					'div',
					{ class: 'ymn-tabs' },
					tabs.map( ( [ key, label ] ) =>
						h(
							'button',
							{
								class: 'ymn-tab' + ( a.tab === key ? ' is-active' : '' ),
								type: 'button',
								onClick: () => {
									a.scanner?.stop();
									a.scanner = null;
									a.tab = key;
									a.status = '';
									a.analysis = null;
									renderSheetBody( body, a );
									sheet.querySelectorAll( '.ymn-tab' ).forEach( ( b ) => b.classList.toggle( 'is-active', b.textContent === label ) );
								},
							},
							label
						)
					)
				),
				body
			);
			renderSheetBody( body, a );
			return sheet;
		}

		function renderSheetBody( body, a ) {
			body.innerHTML = '';
			const status = h( 'p', { class: 'ymn-status', role: 'status' }, a.status );
			const list = h( 'ul', { class: 'ymn-foods' } );

			const showFoods = ( foods, source ) => {
				list.innerHTML = '';
				if ( ! foods.length ) {
					status.textContent = t.noResults;
					return;
				}
				foods.forEach( ( f ) => list.append( foodRow( f, source, a ) ) );
			};

			if ( a.tab === 'search' ) {
				let timer;
				const input = h( 'input', {
					type: 'search',
					name: 'q',
					placeholder: 'Search foods, brands...',
					value: a.query,
					autocomplete: 'off',
					onInput: ( ev ) => {
						a.query = ev.target.value;
						clearTimeout( timer );
						if ( a.query.trim().length < 2 ) return;
						timer = setTimeout( async () => {
							status.textContent = t.loading;
							try {
								const res = await api( 'foods/search?q=' + encodeURIComponent( a.query.trim() ) );
								status.textContent = '';
								showFoods( res.data || [], 'search' );
							} catch ( e ) {
								status.textContent = friendly( e );
							}
						}, 350 );
					},
				} );
				body.append( input, status, list );
				if ( a.results.length ) showFoods( a.results, 'search' );
			} else if ( a.tab === 'recent' ) {
				status.textContent = t.loading;
				body.append( status, list );
				loadRecent().then( ( foods ) => {
					status.textContent = foods.length ? '' : 'Foods you log will show up here for quick re-adding.';
					showFoods( foods, 'recent' );
				} );
			} else if ( a.tab === 'scan' ) {
				const cam = h( 'div', { class: 'ymn-cam' } );
				const manual = h(
					'form',
					{
						class: 'ymn-inline',
						onSubmit: ( ev ) => {
							ev.preventDefault();
							lookupBarcode( ev.target.elements.upc.value );
						},
					},
					h( 'input', { type: 'text', name: 'upc', inputmode: 'numeric', pattern: '[0-9]*', placeholder: 'Or type the barcode number' } ),
					h( 'button', { class: 'ymn-btn', type: 'submit' }, 'Look up' )
				);
				const lookupBarcode = async ( code ) => {
					const digits = String( code ).replace( /\D+/g, '' );
					if ( digits.length < 6 ) return;
					status.textContent = t.loading;
					try {
						const res = await api( 'foods/barcode/' + digits );
						status.textContent = '';
						showFoods( [ res.data ], 'barcode' );
					} catch ( e ) {
						status.textContent = friendly( e );
					}
				};
				body.append( cam, manual, status, list );
				if ( window.ymoveScanner ) {
					a.scanner = window.ymoveScanner.open( cam, {
						onResult: ( code ) => {
							a.scanner = null;
							lookupBarcode( code );
						},
						onError: ( msg ) => {
							a.scanner = null;
							status.textContent = msg || t.cameraMissing;
						},
					} );
				}
			} else if ( a.tab === 'photo' ) {
				const file = h( 'input', { type: 'file', accept: 'image/*', capture: 'environment', hidden: true, onChange: ( ev ) => ev.target.files[ 0 ] && analyzePhoto( ev.target.files[ 0 ] ) } );
				const pick = h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'button', onClick: () => file.click() }, '📷 Take or choose a photo' );
				const analyzePhoto = async ( f ) => {
					if ( ! ( await consent() ) ) return;
					status.textContent = t.analyzing;
					list.innerHTML = '';
					try {
						const { data, type } = await resizeImage( f );
						const res = await api( 'log/photo', { method: 'POST', body: { image: data, media_type: type } } );
						status.textContent = '';
						showAnalysis( res.data, 'photo' );
					} catch ( e ) {
						status.textContent = friendly( e );
						if ( e.upgradeUrl ) status.append( ' ', h( 'a', { href: e.upgradeUrl, target: '_blank', rel: 'noopener' }, 'Upgrade' ) );
					}
				};
				body.append( pick, file, h( 'p', { class: 'ymn-note' }, 'Snap your plate. The AI estimates each food and its portion; you can untick anything before adding.' ), status, list );
			} else if ( a.tab === 'text' ) {
				const ta = h( 'textarea', { rows: 3, placeholder: 'e.g. two scrambled eggs, a slice of toast with butter and a cappuccino' } );
				const btn = h( 'button', {
					class: 'ymn-btn ymn-btn-primary',
					type: 'button',
					onClick: async () => {
						if ( ta.value.trim().length < 3 ) return;
						status.textContent = t.analyzing;
						list.innerHTML = '';
						try {
							const res = await api( 'log/text', { method: 'POST', body: { text: ta.value.trim() } } );
							status.textContent = '';
							showAnalysis( res.data, 'text' );
						} catch ( e ) {
							status.textContent = friendly( e );
							if ( e.upgradeUrl ) status.append( ' ', h( 'a', { href: e.upgradeUrl, target: '_blank', rel: 'noopener' }, 'Upgrade' ) );
						}
					},
				}, 'Analyze' );
				body.append( ta, btn, status, list );
			}

			// Shared renderer for AI analysis results (photo + text).
			function showAnalysis( data, source ) {
				list.innerHTML = '';
				const items = ( data?.items || [] ).map( ( it ) => ( { ...it, checked: !! it.nutrition } ) );
				if ( ! items.length ) {
					status.textContent = 'No foods recognised. Try a clearer photo or describe the meal.';
					return;
				}
				const rows = items.map( ( it ) =>
					h(
						'li',
						{ class: 'ymn-food ymn-ai' + ( it.nutrition ? '' : ' is-unmatched' ) },
						h( 'label', { class: 'ymn-ai-row' },
							h( 'input', { type: 'checkbox', checked: it.checked, disabled: ! it.nutrition, onChange: ( ev ) => ( it.checked = ev.target.checked ) } ),
							h( 'span', { class: 'ymn-food-name' }, it.name, h( 'small', null, ` ~${ fmt( it.estimatedGrams ) } g · ${ it.confidence } ${ t.confidence || 'confidence' }` ) ),
							it.nutrition ? h( 'span', { class: 'ymn-food-kcal' }, fmt( it.nutrition.calories ) + ' kcal' ) : h( 'small', null, t.noMatch )
						)
					)
				);
				list.append( ...rows );
				const totals = data.totals || {};
				list.append(
					h(
						'li',
						{ class: 'ymn-ai-foot' },
						h( 'span', null, `${ fmt( totals.calories ) } kcal · P ${ fmt( totals.protein ) } · C ${ fmt( totals.carbs ) } · F ${ fmt( totals.fat ) }` ),
						h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'button', onClick: async ( ev ) => {
							ev.target.disabled = true;
							const chosen = items.filter( ( it ) => it.checked && it.nutrition );
							for ( const it of chosen ) {
								await addEntry( {
									foodId: it.matchedFood?.id || '',
									displayName: it.name,
									servingG: it.estimatedGrams,
									grams: it.estimatedGrams,
									quantity: 1,
									calories: it.nutrition.calories,
									protein: it.nutrition.protein,
									carbs: it.nutrition.carbs,
									fat: it.nutrition.fat,
								}, source, a.meal, false );
							}
							closeAdd();
							loadDay();
						} }, `${ t.add || 'Add' } selected` )
					)
				);
			}
		}

		function foodRow( f, source, a ) {
			const qty = h( 'input', { type: 'number', min: 0.25, step: 0.25, value: 1, class: 'ymn-qty', 'aria-label': 'Servings' } );
			const btn = h( 'button', { class: 'ymn-btn ymn-btn-small ymn-btn-primary', type: 'button' }, t.add || 'Add' );
			btn.addEventListener( 'click', async () => {
				btn.disabled = true;
				btn.textContent = '...';
				try {
					await addEntry( { ...f, quantity: parseFloat( qty.value ) || 1 }, source, a.meal, true );
					btn.textContent = '✓ ' + ( t.added || 'Added' );
					state.recent = null;
					setTimeout( closeAdd, 350 );
				} catch ( e ) {
					btn.disabled = false;
					btn.textContent = t.add || 'Add';
				}
			} );
			return h(
				'li',
				{ class: 'ymn-food' },
				f.imageUrl ? h( 'img', { src: f.imageUrl, alt: '', loading: 'lazy', class: 'ymn-thumb' } ) : h( 'span', { class: 'ymn-thumb ymn-thumb-empty' } ),
				h(
					'div',
					{ class: 'ymn-food-main' },
					h( 'span', { class: 'ymn-food-name' }, foodName( f ), f.brand && ! foodName( f ).includes( f.brand ) ? h( 'small', null, ' ' + f.brand ) : null ),
					h( 'span', { class: 'ymn-food-serving' }, `${ f.servingDescription || fmt( f.servingSize ) + ' g' } · ${ fmt( f.calories ) } kcal · P ${ fmt( f.protein ) } C ${ fmt( f.carbs ) } F ${ fmt( f.fat ) }` )
				),
				h( 'div', { class: 'ymn-food-act' }, qty, btn )
			);
		}

		async function addEntry( f, source, meal, refresh ) {
			const body = {
				date: state.date,
				meal,
				source,
				foodId: f.id || f.foodId || '',
				displayName: foodName( f ) || f.displayName,
				brand: f.brand || '',
				servingG: f.servingSize ?? f.servingG ?? 0,
				quantity: f.quantity ?? 1,
				grams: f.grams,
				calories: f.calories,
				protein: f.protein,
				carbs: f.carbs,
				fat: f.fat,
				fiber: f.fiber,
				sugar: f.sugar,
				sodium: f.sodium,
			};
			const res = await api( 'diary', { method: 'POST', body } );
			if ( refresh && state.day ) {
				state.day.entries.push( res.data );
				recalc();
			}
			return res.data;
		}

		/* -------------------------------------------------------- utilities */
		function consent() {
			try {
				if ( localStorage.getItem( 'ymn_photo_consent' ) === '1' ) return Promise.resolve( true );
			} catch ( e ) {
				/* ignore */
			}
			return new Promise( ( resolve ) => {
				const dlg = h(
					'div',
					{ class: 'ymn-modal', role: 'dialog', 'aria-modal': 'true' },
					h(
						'div',
						{ class: 'ymn-modal-box' },
						h( 'h3', null, t.consentTitle ),
						h( 'p', null, t.consentBody ),
						h(
							'div',
							{ class: 'ymn-actions' },
							h( 'button', { class: 'ymn-btn ymn-btn-primary', type: 'button', onClick: () => {
								try {
									localStorage.setItem( 'ymn_photo_consent', '1' );
								} catch ( e ) {
									/* ignore */
								}
								dlg.remove();
								resolve( true );
							} }, t.consentAccept ),
							h( 'button', { class: 'ymn-btn', type: 'button', onClick: () => {
								dlg.remove();
								resolve( false );
							} }, t.cancel )
						)
					)
				);
				document.body.append( dlg );
			} );
		}

		function resizeImage( file, max = 1280 ) {
			return new Promise( ( resolve, reject ) => {
				const img = new Image();
				const url = URL.createObjectURL( file );
				img.onload = () => {
					const scale = Math.min( 1, max / Math.max( img.width, img.height ) );
					const c = document.createElement( 'canvas' );
					c.width = Math.round( img.width * scale );
					c.height = Math.round( img.height * scale );
					c.getContext( '2d' ).drawImage( img, 0, 0, c.width, c.height );
					URL.revokeObjectURL( url );
					const dataUrl = c.toDataURL( 'image/jpeg', 0.82 );
					resolve( { data: dataUrl.split( ',' )[ 1 ], type: 'image/jpeg' } );
				};
				img.onerror = () => reject( new Error( 'Could not read image' ) );
				img.src = url;
			} );
		}

		async function exportCsv() {
			const rows = ( await api( 'diary/all' ) ).data || [];
			const head = [ 'date', 'meal', 'food', 'brand', 'quantity', 'serving_g', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg', 'source' ];
			const esc = ( v ) => `"${ String( v ?? '' ).replace( /"/g, '""' ) }"`;
			const csv = [ head.join( ',' ) ]
				.concat( rows.map( ( r ) => [ r.date, r.meal, r.displayName, r.brand, r.quantity, r.servingG, r.kcal, r.protein, r.carbs, r.fat, r.fiber, r.sugar, r.sodium, r.source ].map( esc ).join( ',' ) ) )
				.join( '\n' );
			const a = document.createElement( 'a' );
			a.href = URL.createObjectURL( new Blob( [ csv ], { type: 'text/csv' } ) );
			a.download = 'food-diary.csv';
			a.click();
		}

		render();
		loadDay().catch( ( e ) => {
			mount.innerHTML = '';
			mount.append( h( 'p', { class: 'ymn-notice' }, friendly( e ) ) );
		} );
	}

	document.querySelectorAll( '.ymn-tracker .ymn-mount' ).forEach( ( m ) => Tracker( m.closest( '.ymn-tracker' ) ) );
} )();
