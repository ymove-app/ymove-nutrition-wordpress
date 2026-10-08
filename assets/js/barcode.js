/**
 * Standalone barcode lookup block: scan or type a code, show the label.
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	// Works with pretty and plain permalinks (plain = ?rest_route=/..., so a query must join with &).
	const restUrl = ( ( p ) => { const i = p.indexOf( '?' ); const base = cfg.restUrl + ( i < 0 ? p : p.slice( 0, i ) ); return i < 0 ? base : base + ( base.includes( '?' ) ? '&' : '?' ) + p.slice( i + 1 ); } );
	const t = cfg.i18n || {};
	const fmt = ( n, d = 1 ) => ( n === null || n === undefined ? '-' : Number( n ).toLocaleString( undefined, { maximumFractionDigits: d } ) );

	function label( f ) {
		const rows = [
			[ 'Total Fat', f.fat, 'g', 1 ],
			[ 'Saturated Fat', f.saturatedFat, 'g', 0 ],
			[ 'Cholesterol', f.cholesterol, 'mg', 1 ],
			[ 'Sodium', f.sodium, 'mg', 1 ],
			[ 'Total Carbohydrate', f.carbs, 'g', 1 ],
			[ 'Dietary Fiber', f.fiber, 'g', 0 ],
			[ 'Total Sugars', f.sugar, 'g', 0 ],
			[ 'Protein', f.protein, 'g', 1 ],
		].filter( ( r ) => r[ 1 ] !== null && r[ 1 ] !== undefined );
		const esc = ( s ) => String( s ?? '' ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );
		return `<div class="ymn-label">
			${ f.imageUrl ? `<img class="ymn-label-img" src="${ esc( f.imageUrl ) }" alt="">` : '' }
			<div class="ymn-label-title">Nutrition Facts</div>
			<div class="ymn-label-food">${ esc( f.displayName || f.shortName || f.name ) }</div>
			<div class="ymn-label-serving">Serving size <strong>${ esc( f.servingDescription || fmt( f.servingSize, 0 ) + ' g' ) }</strong></div>
			<div class="ymn-label-cal"><span>Calories</span><strong>${ fmt( f.calories, 0 ) }</strong></div>
			<table class="ymn-label-table"><tbody>${ rows.map( ( r ) => `<tr class="${ r[ 3 ] ? 'is-main' : 'is-sub' }"><th scope="row">${ r[ 0 ] }</th><td>${ fmt( r[ 1 ] ) } ${ r[ 2 ] }</td></tr>` ).join( '' ) }</tbody></table>
			${ f.brand ? `<div class="ymn-label-brand">${ esc( f.brand ) }</div>` : '' }
			${ f.barcode ? `<div class="ymn-label-brand">EAN/UPC ${ esc( f.barcode ) }</div>` : '' }
		</div>`;
	}

	function init( root ) {
		const mount = root.querySelector( '.ymn-mount' );
		mount.innerHTML = `
			<div class="ymn-cam"></div>
			<form class="ymn-inline ymn-barcode-form">
				<input type="text" name="upc" inputmode="numeric" pattern="[0-9]*" placeholder="Or type the barcode number" aria-label="Barcode number">
				<button class="ymn-btn ymn-btn-primary" type="submit">Look up</button>
			</form>
			<button type="button" class="ymn-btn ymn-btn-small ymn-rescan" hidden>Scan another</button>
			<p class="ymn-status" role="status"></p>
			<div class="ymn-barcode-result"></div>`;
		const cam = mount.querySelector( '.ymn-cam' );
		const status = mount.querySelector( '.ymn-status' );
		const out = mount.querySelector( '.ymn-barcode-result' );
		const rescan = mount.querySelector( '.ymn-rescan' );
		let scanner = null;

		const lookup = async ( code ) => {
			const digits = String( code ).replace( /\D+/g, '' );
			if ( digits.length < 6 ) return;
			status.textContent = t.loading || 'Loading...';
			out.innerHTML = '';
			try {
				const r = await fetch( restUrl( 'foods/barcode/' + digits ), { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } } );
				const body = await r.json().catch( () => ( {} ) );
				if ( ! r.ok ) throw Object.assign( new Error( body.message ), { status: r.status } );
				status.textContent = '';
				out.innerHTML = label( body.data );
			} catch ( e ) {
				status.textContent = e.status === 404 ? t.notFound : e.status === 429 ? t.throttled : e.message || t.error;
			}
			rescan.hidden = false;
		};

		const start = () => {
			rescan.hidden = true;
			if ( ! window.ymoveScanner ) return;
			scanner = window.ymoveScanner.open( cam, {
				onResult: ( code ) => {
					scanner = null;
					lookup( code );
				},
				onError: ( msg ) => {
					scanner = null;
					status.textContent = msg || t.cameraMissing;
				},
			} );
		};

		mount.querySelector( 'form' ).addEventListener( 'submit', ( ev ) => {
			ev.preventDefault();
			scanner?.stop();
			scanner = null;
			lookup( ev.target.elements.upc.value );
		} );
		rescan.addEventListener( 'click', start );
		start();
	}

	document.querySelectorAll( '.ymn-barcode' ).forEach( init );
} )();
