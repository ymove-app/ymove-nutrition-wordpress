/**
 * Admin: plan/usage display, members detail, notice dismissal.
 */
( () => {
	const cfg = window.ymoveNutritionAdmin || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const api = ( path ) =>
		fetch( restUrl( path ), { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } } ).then( async ( r ) => {
			const b = await r.json().catch( () => ( {} ) );
			if ( ! r.ok ) throw new Error( b.message || r.statusText );
			return b;
		} );
	const esc = ( s ) => String( s ?? '' ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );
	const fmt = ( n ) => Number( n || 0 ).toLocaleString();

	const PLAN_LABEL = { demo: 'Demo', trial: 'Trial', basic: 'Basic', pro: 'Pro', scale: 'Scale', enterprise: 'Enterprise' };

	function planHtml( d ) {
		const plan = ( d.plan || '' ).toLowerCase();
		const aiOk = [ 'pro', 'scale', 'enterprise', 'trial', 'demo' ].includes( plan );
		let html = `<p class="ymove-plan"><span class="ymove-badge">${ esc( PLAN_LABEL[ plan ] || d.plan ) }</span> `;
		html += aiOk
			? '<span class="ymove-ok">Food search, barcodes and AI photo/text logging are available.</span>'
			: '<span class="ymove-warn">Food search and barcodes are available. AI photo and text logging need the Pro plan.</span> <a target="_blank" rel="noopener" href="https://ymove.app/nutrition-api/signup?plan=pro&source=wordpress-plugin">Upgrade</a>';
		html += '</p>';
		if ( d.status ) html += `<p>Status: <strong>${ esc( d.status ) }</strong>${ d.trialEndsAt ? ` (trial ends ${ esc( new Date( d.trialEndsAt ).toLocaleDateString() ) })` : '' }</p>`;
		return html;
	}

	async function loadUsage( fresh ) {
		const summary = document.getElementById( 'ymove-usage-summary' );
		const planBox = document.getElementById( 'ymove-usage-plan' );
		try {
			const res = await api( 'admin/usage' + ( fresh ? '?fresh=1' : '' ) );
			const d = res.data || {};
			if ( summary ) summary.innerHTML = planHtml( d );
			if ( planBox ) planBox.innerHTML = planHtml( d );
			renderLocal( res.local || {} );
		} catch ( e ) {
			const msg = `<p class="ymove-warn">Could not reach the Your Move API: ${ esc( e.message ) }. Check the key.</p>`;
			if ( summary ) summary.innerHTML = msg;
			if ( planBox ) planBox.innerHTML = msg;
		}
	}

	function renderLocal( local ) {
		const table = document.querySelector( '#ymove-usage-table tbody' );
		const chart = document.getElementById( 'ymove-usage-chart' );
		if ( ! table ) return;
		const days = [];
		for ( let i = 29; i >= 0; i-- ) {
			const d = new Date();
			d.setUTCDate( d.getUTCDate() - i );
			days.push( d.toISOString().slice( 0, 10 ) );
		}
		const types = [ 'search', 'barcode', 'food', 'photo', 'text', 'mealplan', 'recipe' ];
		const max = Math.max( 1, ...days.map( ( day ) => types.reduce( ( s, t ) => s + ( local[ day ]?.[ t ] || 0 ), 0 ) ) );
		chart.innerHTML = days
			.map( ( day ) => {
				const total = types.reduce( ( s, t ) => s + ( local[ day ]?.[ t ] || 0 ), 0 );
				return `<div class="ymove-chart-col" title="${ day }: ${ total }"><div class="ymove-chart-bar" style="height:${ ( total / max ) * 100 }%"></div></div>`;
			} )
			.join( '' );
		table.innerHTML = days
			.slice()
			.reverse()
			.filter( ( day ) => local[ day ] )
			.map( ( day ) => `<tr><td>${ day }</td>${ types.map( ( t ) => `<td>${ fmt( local[ day ]?.[ t ] ) }</td>` ).join( '' ) }</tr>` )
			.join( '' ) || '<tr><td colspan="6">No API calls yet.</td></tr>';
	}

	document.getElementById( 'ymove-usage-refresh' )?.addEventListener( 'click', () => loadUsage( true ) );
	if ( document.getElementById( 'ymove-usage-summary' ) || document.getElementById( 'ymove-usage-plan' ) ) loadUsage( false );

	// Members screen.
	document.querySelectorAll( '.ymove-member' ).forEach( ( a ) =>
		a.addEventListener( 'click', async ( ev ) => {
			ev.preventDefault();
			const box = document.getElementById( 'ymove-member-detail' );
			box.hidden = false;
			box.innerHTML = '<p>Loading...</p>';
			try {
				const d = await api( 'admin/member/' + a.dataset.id );
				const max = Math.max( d.targets.kcal * 1.2, ...d.days.map( ( x ) => x.kcal ) );
				box.innerHTML = `<h2>${ esc( d.user.name ) }</h2>
					<p>Target ${ fmt( d.targets.kcal ) } kcal &middot; P ${ d.targets.protein } / C ${ d.targets.carbs } / F ${ d.targets.fat } g</p>
					<div class="ymove-chart">${ d.days.map( ( x ) => `<div class="ymove-chart-col" title="${ x.date }: ${ fmt( x.kcal ) } kcal"><div class="ymove-chart-bar ${ x.kcal > d.targets.kcal ? 'is-over' : '' }" style="height:${ ( x.kcal / max ) * 100 }%"></div></div>` ).join( '' ) }</div>
					<h3>Today</h3>
					${ d.today.length ? `<table class="widefat striped"><tbody>${ d.today.map( ( e ) => `<tr><td>${ esc( e.meal ) }</td><td>${ esc( e.displayName ) }</td><td>${ e.quantity } x ${ e.servingG } g</td><td>${ fmt( e.kcal ) } kcal</td><td>${ esc( e.source ) }</td></tr>` ).join( '' ) }</tbody></table>` : '<p>Nothing logged today.</p>' }`;
			} catch ( e ) {
				box.innerHTML = `<p class="ymove-warn">${ esc( e.message ) }</p>`;
			}
		} )
	);

	// Dismissible connect notice.
	document.querySelector( '.ymove-connect-notice' )?.addEventListener( 'click', ( ev ) => {
		if ( ! ev.target.classList.contains( 'notice-dismiss' ) ) return;
		const body = new FormData();
		body.append( 'action', 'ymove_dismiss_connect' );
		body.append( '_ajax_nonce', cfg.ajaxNonce );
		fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body } );
	} );
} )();
