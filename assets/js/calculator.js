/**
 * Calorie + BMI calculators. Pure client-side; no API involved.
 *
 * BMR formulas: Mifflin-St Jeor (1990, default), revised Harris-Benedict
 * (Roza & Shizgal 1984), Katch-McArdle (needs body fat %). TDEE = BMR x
 * activity factor, goal adjustment in kcal/day, macros from a percentage
 * split at 4 / 4 / 9 kcal per gram. BMI = kg / m^2, WHO adult categories.
 *
 * Lead capture modes (data-lead): off | optional | required. In "required"
 * the numbers stay hidden until the visitor submits an email.
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const store = {
		get( k ) {
			try {
				return JSON.parse( localStorage.getItem( 'ymn_' + k ) );
			} catch ( e ) {
				return null;
			}
		},
		set( k, v ) {
			try {
				localStorage.setItem( 'ymn_' + k, JSON.stringify( v ) );
			} catch ( e ) {
				/* private mode */
			}
		},
	};

	const round = ( n, d = 0 ) => Math.round( n * 10 ** d ) / 10 ** d;
	const CATS = [
		[ 18.5, 'Underweight' ],
		[ 25, 'Healthy weight' ],
		[ 30, 'Overweight' ],
		[ 35, 'Obesity class I' ],
		[ 40, 'Obesity class II' ],
		[ Infinity, 'Obesity class III' ],
	];
	const fmt = ( n, d = 0 ) => Number( n ).toLocaleString( undefined, { maximumFractionDigits: d } );

	function readBody( form, units ) {
		const f = ( name ) => parseFloat( form.elements[ name ]?.value );
		let heightCm, weightKg;
		if ( units === 'imperial' ) {
			heightCm = ( ( f( 'height_ft' ) || 0 ) * 12 + ( f( 'height_in' ) || 0 ) ) * 2.54;
			weightKg = f( 'weight_lb' ) * 0.45359237;
		} else {
			heightCm = f( 'height_cm' );
			weightKg = f( 'weight_kg' );
		}
		return { heightCm, weightKg };
	}

	function setUnits( root, units ) {
		root.dataset.units = units;
		root.querySelectorAll( '.ymn-unit' ).forEach( ( b ) => b.classList.toggle( 'is-active', b.dataset.units === units ) );
		root.querySelectorAll( '.ymn-metric' ).forEach( ( e ) => ( e.hidden = units !== 'metric' ) );
		root.querySelectorAll( '.ymn-imperial' ).forEach( ( e ) => ( e.hidden = units !== 'imperial' ) );
		store.set( 'units', units );
	}

	function wireUnits( root ) {
		setUnits( root, store.get( 'units' ) || root.dataset.units || cfg.units || 'metric' );
		root.querySelectorAll( '.ymn-unit' ).forEach( ( b ) => b.addEventListener( 'click', () => setUnits( root, b.dataset.units ) ) );
	}

	function showError( form, msg ) {
		const el = form.querySelector( '.ymn-error' );
		if ( ! el ) return;
		el.textContent = msg;
		el.hidden = ! msg;
	}

	/* ------------------------------------------------------------- Leads */

	/**
	 * Wire the email form inside a result panel. `getResults` returns the
	 * payload to store/email. In gated mode a successful submit reveals the
	 * numbers and remembers the unlock for this browser.
	 */
	function wireLead( result, getResults ) {
		const lead = result.querySelector( 'form.ymn-lead' );
		if ( ! lead ) return;
		const gated = result.classList.contains( 'is-gated' );
		const errEl = lead.querySelector( '.ymn-lead-error' );

		const unlock = () => {
			result.classList.remove( 'is-gated' );
			const gate = result.querySelector( '.ymn-gate' );
			if ( gate ) gate.hidden = true;
		};
		const fail = ( msg ) => {
			if ( ! errEl ) return;
			errEl.textContent = msg;
			errEl.hidden = false;
		};

		if ( gated && store.get( 'unlocked' ) ) unlock();
		mountTurnstile( lead );

		lead.addEventListener( 'submit', async ( ev ) => {
			ev.preventDefault();
			const results = getResults();
			if ( ! results ) return;
			const email = lead.elements.email.value.trim();
			if ( ! /.+@.+\..+/.test( email ) ) {
				fail( 'Please enter a valid email address.' );
				return;
			}
			if ( lead.elements.consent && ! lead.elements.consent.checked ) {
				fail( 'Please tick the consent box.' );
				return;
			}
			const btn = lead.querySelector( 'button' );
			btn.disabled = true;
			try {
				const captcha = await captchaToken( lead );
				const r = await fetch( restUrl( 'leads' ), {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( {
						email,
						captcha,
						consent: !! ( lead.elements.consent && lead.elements.consent.checked ),
						website: lead.elements.website.value,
						page: location.href,
						results,
					} ),
				} );
				const body = await r.json().catch( () => ( {} ) );
				if ( ! r.ok ) throw new Error( body.message || 'Could not send. Please try again.' );
				if ( errEl ) errEl.hidden = true;
				lead.querySelector( '.ymn-lead-done' ).hidden = false;
				lead.querySelectorAll( 'label' ).forEach( ( l ) => ( l.hidden = true ) );
				if ( gated ) {
					store.set( 'unlocked', 1 );
					unlock();
				}
			} catch ( e ) {
				btn.disabled = false;
				fail( e.message );
			}
		} );
	}

	/* ------------------------------------------------------------- Steps */

	/**
	 * Multi-step layout: one fieldset visible at a time, Next validates the
	 * visible fields, progress list mirrors the current step.
	 */
	function wireSteps( root, form ) {
		const steps = [ ...form.querySelectorAll( '.ymn-step' ) ];
		if ( ! steps.length ) return;
		const progress = [ ...form.querySelectorAll( '.ymn-progress li' ) ];
		let current = 0;
		const show = ( i ) => {
			current = Math.max( 0, Math.min( steps.length - 1, i ) );
			steps.forEach( ( st, k ) => ( st.hidden = k !== current ) );
			progress.forEach( ( li, k ) => {
				li.classList.toggle( 'is-active', k === current );
				li.classList.toggle( 'is-done', k < current );
			} );
			showError( form, '' );
			steps[ current ].querySelector( 'input:not([hidden]), select' )?.focus( { preventScroll: true } );
		};
		const REQUIRED = [ 'age', 'height_cm', 'weight_kg', 'weight_lb', 'height_ft', 'bodyfat' ];
		const stepValid = () =>
			[ ...steps[ current ].querySelectorAll( 'input[type="number"]' ) ].every( ( el ) => {
				const wrap = el.closest( '.ymn-metric, .ymn-imperial' );
				if ( wrap && wrap.hidden ) return true; // other unit system
				const v = parseFloat( el.value );
				if ( isNaN( v ) ) return ! REQUIRED.includes( el.name ); // height_in may stay empty
				const min = parseFloat( el.min ), max = parseFloat( el.max );
				return ( isNaN( min ) || v >= min ) && ( isNaN( max ) || v <= max );
			} );
		form.addEventListener( 'click', ( ev ) => {
			if ( ev.target.closest( '[data-step-next]' ) ) {
				if ( ! stepValid() ) {
					showError( form, 'Please complete this step first.' );
					return;
				}
				show( current + 1 );
			} else if ( ev.target.closest( '[data-step-back]' ) ) {
				show( current - 1 );
			}
		} );
		form.addEventListener( 'keydown', ( ev ) => {
			if ( ev.key === 'Enter' && current < steps.length - 1 && ev.target.tagName !== 'BUTTON' ) {
				ev.preventDefault();
				form.querySelector( '.ymn-step:not([hidden]) [data-step-next]' )?.click();
			}
		} );
	}

	/* -------------------------------------------------------------- Chat */

	const el = ( tag, cls, ...kids ) => {
		const e = document.createElement( tag );
		if ( cls ) e.className = cls;
		kids.flat().forEach( ( k ) => k !== null && k !== undefined && e.append( k instanceof Node ? k : document.createTextNode( String( k ) ) ) );
		return e;
	};
	const wait = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

	/**
	 * Chat layout: asks the form's questions one at a time and fills in the
	 * real (hidden) form, then submits it and moves the results into the
	 * conversation. Without JS the plain form is shown instead.
	 */
	function wireChat( root, form, result ) {
		if ( root.dataset.layout !== 'chat' ) return;
		root.classList.add( 'is-chatting' );
		const f = form.elements;
		const log = el( 'div', 'ymn-chat-log' );
		log.setAttribute( 'role', 'log' );
		log.setAttribute( 'aria-live', 'polite' );
		const composer = el( 'div', 'ymn-chat-composer' );
		const home = result.parentNode;
		form.before( log, composer );

		// Fixed-height and floating chats scroll inside the log, never the page.
		const boxed = root.classList.contains( 'ymn-chat-fixed' ) || root.classList.contains( 'ymn-chat-floating' );
		const scroll = () => ( boxed ? ( log.scrollTop = log.scrollHeight ) : composer.scrollIntoView( { behavior: 'smooth', block: 'nearest' } ) );
		async function say( text ) {
			const typing = el( 'div', 'ymn-chat-msg is-bot is-typing', el( 'span' ), el( 'span' ), el( 'span' ) );
			log.append( typing );
			scroll();
			await wait( Math.min( 900, 250 + text.length * 12 ) );
			typing.replaceWith( el( 'div', 'ymn-chat-msg is-bot', text ) );
			scroll();
		}
		const me = ( text ) => log.append( el( 'div', 'ymn-chat-msg is-me', text ) );
		const optionsOf = ( select ) => [ ...select.options ].map( ( o ) => ( { value: o.value, label: o.textContent } ) );
		const set = ( field, value ) => {
			field.value = value;
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		// One question: chips for choices, or number inputs with range checks.
		function ask( q ) {
			return new Promise( ( resolve ) => {
				composer.innerHTML = '';
				if ( q.choices ) {
					const chips = el( 'div', 'ymn-chat-chips' );
					q.choices.forEach( ( c ) => {
						const b = el( 'button', 'ymn-chat-chip', c.label );
						b.type = 'button';
						b.addEventListener( 'click', () => {
							composer.innerHTML = '';
							me( c.label );
							q.pick( c.value );
							resolve();
						} );
						chips.append( b );
					} );
					composer.append( chips );
					scroll(); // A tall row of chips shrinks a boxed log; keep the question in view.
					chips.querySelector( 'button' )?.focus( { preventScroll: true } );
					return;
				}
				const inputs = q.fields.map( ( fld ) => {
					const i = el( 'input' );
					Object.assign( i, { type: 'number', inputMode: 'decimal', placeholder: fld.placeholder || '', min: fld.el.min, max: fld.el.max, step: fld.el.step || 'any' } );
					i.setAttribute( 'aria-label', fld.placeholder || q.text );
					return i;
				} );
				const send = el( 'button', 'ymn-btn ymn-btn-primary', '➤' );
				send.type = 'submit';
				send.setAttribute( 'aria-label', 'Send' );
				const row = el( 'form', 'ymn-chat-row', inputs, send );
				row.noValidate = true;
				row.addEventListener( 'submit', async ( ev ) => {
					ev.preventDefault();
					const bad = q.fields.findIndex( ( fld, k ) => {
						const v = parseFloat( inputs[ k ].value );
						if ( isNaN( v ) ) return ! fld.optional;
						return v < parseFloat( fld.el.min ) || v > parseFloat( fld.el.max );
					} );
					if ( bad >= 0 ) {
						const fld = q.fields[ bad ];
						await say( `Hmm, that doesn't look right. Somewhere between ${ fld.el.min } and ${ fld.el.max } ${ fld.unit || '' }, please.`.replace( ' ,', ',' ) );
						inputs[ bad ].focus();
						return;
					}
					q.fields.forEach( ( fld, k ) => set( fld.el, inputs[ k ].value ) );
					composer.innerHTML = '';
					me( q.echo( inputs.map( ( i ) => i.value ) ) );
					resolve();
				} );
				composer.append( row );
				scroll();
				inputs[ 0 ].focus( { preventScroll: true } );
			} );
		}

		const units = () => root.dataset.units;
		const script = () => {
			const q = [];
			if ( f.sex ) q.push( { text: "Hi! I'll work out your daily calories in a few quick questions. First, are you…", choices: optionsOf( f.sex ), pick: ( v ) => set( f.sex, v ) } );
			if ( f.age ) q.push( { text: 'How old are you?', fields: [ { el: f.age, placeholder: 'Age', unit: 'years' } ], echo: ( v ) => `${ v[ 0 ] } years` } );
			if ( root.querySelector( '.ymn-unit' ) ) {
				q.push( { text: 'Do you measure in metric or imperial?', choices: [ { value: 'metric', label: 'Metric (kg, cm)' }, { value: 'imperial', label: 'Imperial (lb, ft)' } ], pick: ( v ) => root.querySelector( `.ymn-unit[data-units="${ v }"]` )?.click() } );
			}
			q.push( () =>
				units() === 'imperial'
					? { text: 'How tall are you?', fields: [ { el: f.height_ft, placeholder: 'ft', unit: 'ft' }, { el: f.height_in, placeholder: 'in', unit: 'in', optional: true } ], echo: ( v ) => `${ v[ 0 ] } ft ${ v[ 1 ] || 0 } in` }
					: { text: 'How tall are you?', fields: [ { el: f.height_cm, placeholder: 'cm', unit: 'cm' } ], echo: ( v ) => `${ v[ 0 ] } cm` }
			);
			q.push( () =>
				units() === 'imperial'
					? { text: 'And your weight?', fields: [ { el: f.weight_lb, placeholder: 'lb', unit: 'lb' } ], echo: ( v ) => `${ v[ 0 ] } lb` }
					: { text: 'And your weight?', fields: [ { el: f.weight_kg, placeholder: 'kg', unit: 'kg' } ], echo: ( v ) => `${ v[ 0 ] } kg` }
			);
			if ( f.bodyfat ) q.push( { text: 'Roughly what is your body fat percentage?', fields: [ { el: f.bodyfat, placeholder: '%', unit: '%' } ], echo: ( v ) => `${ v[ 0 ] }%` } );
			if ( f.activity ) q.push( { text: 'How active are you on a typical week?', choices: optionsOf( f.activity ), pick: ( v ) => set( f.activity, v ) } );
			if ( f.goal && f.goal.tagName === 'SELECT' ) q.push( { text: "What's your goal?", choices: optionsOf( f.goal ), pick: ( v ) => set( f.goal, v ) } );
			if ( f.split ) q.push( { text: 'Last one: how would you like your macros split?', choices: optionsOf( f.split ), pick: ( v ) => set( f.split, v ) } );
			return q;
		};

		async function run() {
			for ( let q of script() ) {
				if ( typeof q === 'function' ) q = q();
				await say( q.text );
				await ask( q );
			}
			await say( 'Crunching the numbers…' );
			form.requestSubmit();
			await wait( 200 );
			if ( result.classList.contains( 'has-result' ) ) {
				const target = root.querySelector( '[data-out="target"]' )?.textContent;
				await say( target ? `Here you go: about ${ target } kcal a day for your goal.` : 'Here you go.' );
				log.append( el( 'div', 'ymn-chat-msg is-bot is-result', result ) );
			} else {
				await say( form.querySelector( '.ymn-error' )?.textContent || 'Something went wrong, let us try again.' );
			}
			composer.innerHTML = '';
			const again = el( 'button', 'ymn-btn ymn-btn-small', '↺ Start over' );
			again.type = 'button';
			again.addEventListener( 'click', () => {
				result.classList.remove( 'has-result' );
				result.hidden = true;
				home.append( result );
				log.innerHTML = '';
				run();
			} );
			composer.append( again );
			scroll();
		}

		// Floating: start talking on first open, so the typing animation isn't spent while closed.
		const float = root.closest( 'details.ymn-chat-float' );
		if ( ! float ) {
			run();
			return;
		}
		let started = false;
		const launcher = float.querySelector( 'summary' );
		float.addEventListener( 'toggle', () => {
			if ( ! float.open ) return;
			if ( ! started ) {
				started = true;
				run();
			} else {
				composer.querySelector( 'button, input' )?.focus( { preventScroll: true } );
			}
		} );
		const close = () => {
			float.open = false;
			launcher.focus();
		};
		root.querySelector( '.ymn-chat-close' )?.addEventListener( 'click', close );
		float.addEventListener( 'keydown', ( ev ) => ev.key === 'Escape' && float.open && close() );
		if ( float.open ) float.dispatchEvent( new Event( 'toggle' ) );
	}

	/* ----------------------------------------------------------- Captcha */

	/**
	 * Resolve a captcha token for the lead form, or '' when none configured.
	 * reCAPTCHA v3 is invisible; Turnstile renders a small widget into the
	 * form's .ymn-captcha box.
	 */
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
		// Turnstile: token is delivered by the widget's callback and cached on the form.
		return Promise.resolve( lead.dataset.turnstileToken || '' );
	}

	function mountTurnstile( lead ) {
		const c = cfg.captcha || {};
		const box = lead.querySelector( '.ymn-captcha[data-provider="turnstile"]' );
		if ( ! box || c.provider !== 'turnstile' || ! c.siteKey ) return;
		const render = () => {
			if ( ! window.turnstile ) return setTimeout( render, 300 );
			window.turnstile.render( box, {
				sitekey: c.siteKey,
				size: 'flexible',
				callback: ( token ) => ( lead.dataset.turnstileToken = token ),
				'expired-callback': () => ( lead.dataset.turnstileToken = '' ),
			} );
		};
		render();
	}

	/* -------------------------------------------------------- Calculator */

	function bmrFor( formula, { sex, age, heightCm, weightKg, bodyFat } ) {
		if ( formula === 'katch' && bodyFat > 0 ) {
			return 370 + 21.6 * weightKg * ( 1 - bodyFat / 100 );
		}
		if ( formula === 'harris' ) {
			return sex === 'male' ? 88.362 + 13.397 * weightKg + 4.799 * heightCm - 5.677 * age : 447.593 + 9.247 * weightKg + 3.098 * heightCm - 4.33 * age;
		}
		return 10 * weightKg + 6.25 * heightCm - 5 * age + ( sex === 'male' ? 5 : -161 );
	}

	function initCalculator( root ) {
		const form = root.querySelector( 'form.ymn-form' );
		const result = root.querySelector( '.ymn-result' );
		if ( ! form || ! result ) return;
		const out = ( k ) => root.querySelector( `[data-out="${ k }"]` );
		const formula = root.dataset.formula || 'mifflin';
		wireUnits( root );
		if ( root.dataset.goal && form.elements.goal ) form.elements.goal.value = root.dataset.goal;

		let last = null;

		function compute( quiet ) {
			const units = root.dataset.units;
			const { heightCm, weightKg } = readBody( form, units );
			const age = parseFloat( form.elements.age.value );
			const sex = form.elements.sex.value;
			const activity = parseFloat( form.elements.activity.value );
			const goalEl = form.elements.goal;
			const goal = goalEl ? goalEl.value : 'maintain';
			const goalOpt = goalEl && goalEl.tagName === 'SELECT' ? goalEl.options[ goalEl.selectedIndex ] : goalEl;
			const delta = goalOpt && goalOpt.dataset ? parseFloat( goalOpt.dataset.delta || 0 ) : 0;
			const bodyFat = form.elements.bodyfat ? parseFloat( form.elements.bodyfat.value ) : 0;

			const ok = age >= 13 && age <= 100 && heightCm >= 100 && heightCm <= 250 && weightKg >= 30 && weightKg <= 300 && ( formula !== 'katch' || ( bodyFat >= 3 && bodyFat <= 70 ) );
			if ( ! ok ) {
				if ( ! quiet ) showError( form, formula === 'katch' ? 'Please fill in age, height, weight and body fat %.' : 'Please fill in age, height and weight.' );
				return;
			}
			showError( form, '' );

			const bmr = bmrFor( formula, { sex, age, heightCm, weightKg, bodyFat } );
			const tdee = bmr * activity;
			let target = tdee + ( isNaN( delta ) ? 0 : delta );
			const floor = sex === 'male' ? 1500 : 1200;
			let note = '';
			if ( target < floor ) {
				target = floor;
				note = 'Your goal was raised to a safe minimum. Faster loss than this is not recommended without medical supervision.';
			}

			last = { bmr: round( bmr ), tdee: round( tdee ), target: round( target ), goal, formula };
			out( 'bmr' ).textContent = fmt( last.bmr );
			out( 'tdee' ).textContent = fmt( last.tdee );
			out( 'target' ).textContent = fmt( last.target );

			if ( form.elements.split ) {
				const [ p, c, f ] = form.elements.split.value.split( ',' ).map( Number );
				last.protein_g = round( ( target * p ) / 100 / 4 );
				last.carbs_g = round( ( target * c ) / 100 / 4 );
				last.fat_g = round( ( target * f ) / 100 / 9 );
				out( 'protein' ).textContent = fmt( last.protein_g ) + ' g';
				out( 'carbs' ).textContent = fmt( last.carbs_g ) + ' g';
				out( 'fat' ).textContent = fmt( last.fat_g ) + ' g';
			}
			if ( out( 'bmi' ) ) {
				const m = heightCm / 100;
				const bmi = weightKg / ( m * m );
				last.bmi = round( bmi, 1 );
				last.category = CATS.find( ( c ) => bmi < c[ 0 ] )[ 1 ];
				out( 'bmi' ).textContent = fmt( bmi, 1 );
				if ( out( 'category' ) ) out( 'category' ).textContent = last.category;
			}
			if ( out( 'note' ) ) out( 'note' ).textContent = note;
			result.hidden = false;
			result.classList.add( 'has-result' );
			if ( ! quiet ) result.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			store.set( 'calc', { ...last, protein: last.protein_g, carbs: last.carbs_g, fat: last.fat_g } );
		}

		form.addEventListener( 'submit', ( ev ) => {
			ev.preventDefault();
			compute( false );
		} );

		if ( root.dataset.instant === '1' ) {
			let timer;
			form.addEventListener( 'input', () => {
				clearTimeout( timer );
				timer = setTimeout( () => compute( true ), 250 );
			} );
			form.addEventListener( 'change', () => compute( true ) );
		}

		wireSteps( root, form );
		wireChat( root, form, result );

		// Save as tracker target (rendered by PHP only for connected members).
		root.querySelector( '[data-action="save-target"]' )?.addEventListener( 'click', async ( ev ) => {
			if ( ! last ) return;
			const btn = ev.currentTarget;
			btn.disabled = true;
			try {
				const r = await fetch( restUrl( 'targets' ), {
					method: 'PUT',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify( { kcal: last.target, protein: last.protein_g || 0, carbs: last.carbs_g || 0, fat: last.fat_g || 0 } ),
				} );
				btn.textContent = r.ok ? '✓ Saved as your target' : 'Could not save';
			} catch ( e ) {
				btn.textContent = 'Could not save';
			}
		} );

		wireLead( result, () => last );
	}

	/* ----------------------------------------------------------------- BMI */

	function initBmi( root ) {
		const form = root.querySelector( 'form.ymn-form' );
		const result = root.querySelector( '.ymn-result' );
		if ( ! form || ! result ) return;
		const out = ( k ) => root.querySelector( `[data-out="${ k }"]` );
		wireUnits( root );
		let last = null;

		const compute = ( quiet ) => {
			const units = root.dataset.units;
			const { heightCm, weightKg } = readBody( form, units );
			if ( ! ( heightCm >= 100 && heightCm <= 250 ) || ! ( weightKg >= 30 && weightKg <= 300 ) ) {
				if ( ! quiet ) showError( form, 'Please fill in height and weight.' );
				return;
			}
			showError( form, '' );
			const m = heightCm / 100;
			const bmi = weightKg / ( m * m );
			const cat = CATS.find( ( c ) => bmi < c[ 0 ] )[ 1 ];
			const lo = 18.5 * m * m;
			const hi = 24.9 * m * m;
			const range = units === 'imperial' ? `${ fmt( lo / 0.45359237 ) } - ${ fmt( hi / 0.45359237 ) } lb` : `${ fmt( lo, 1 ) } - ${ fmt( hi, 1 ) } kg`;
			out( 'bmi' ).textContent = fmt( bmi, 1 );
			out( 'category' ).textContent = cat;
			out( 'range' ).textContent = range;
			out( 'marker' ).style.left = Math.min( 100, Math.max( 0, ( ( bmi - 15 ) / 25 ) * 100 ) ) + '%';
			last = { bmi: round( bmi, 1 ), category: cat, healthy_range: range };
			result.hidden = false;
			result.classList.add( 'has-result' );
		};
		form.addEventListener( 'submit', ( ev ) => {
			ev.preventDefault();
			compute( false );
		} );
		if ( root.dataset.instant === '1' ) {
			let timer;
			form.addEventListener( 'input', () => {
				clearTimeout( timer );
				timer = setTimeout( () => compute( true ), 250 );
			} );
		}

		wireLead( result, () => last );
	}

	document.querySelectorAll( '.ymn-calculator' ).forEach( initCalculator );
	document.querySelectorAll( '.ymn-bmi' ).forEach( initBmi );
} )();
