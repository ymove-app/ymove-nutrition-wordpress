/**
 * Camera barcode scanner. Uses the native BarcodeDetector API where the
 * browser ships it, and lazily loads the bundled ZXing build everywhere else. Always pair it
 * with a manual-entry field: cameras need HTTPS and permission.
 *
 * window.ymoveScanner.open(container, { onResult(code), onError(msg) }) -> { stop() }
 */
( () => {
	const cfg = window.ymoveNutrition || {};
	const FORMATS = [ 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128' ];
	let zxingPromise = null;

	function loadZxing() {
		if ( window.ZXing ) return Promise.resolve( window.ZXing );
		if ( zxingPromise ) return zxingPromise;
		zxingPromise = new Promise( ( resolve, reject ) => {
			const s = document.createElement( 'script' );
			s.src = cfg.zxingUrl;
			s.onload = () => resolve( window.ZXing );
			s.onerror = () => reject( new Error( 'zxing failed to load' ) );
			document.head.appendChild( s );
		} );
		return zxingPromise;
	}

	async function hasNative() {
		if ( ! ( 'BarcodeDetector' in window ) ) return false;
		try {
			const supported = await window.BarcodeDetector.getSupportedFormats();
			return supported.some( ( f ) => FORMATS.includes( f ) );
		} catch ( e ) {
			return false;
		}
	}

	function open( container, { onResult, onError } ) {
		let stopped = false;
		let stream = null;
		let raf = 0;
		let zxReader = null;

		container.innerHTML = '<div class="ymn-scan"><video class="ymn-scan-video" playsinline muted autoplay></video><div class="ymn-scan-frame"></div><p class="ymn-scan-hint"></p></div>';
		const video = container.querySelector( 'video' );
		const hint = container.querySelector( '.ymn-scan-hint' );

		const stop = () => {
			stopped = true;
			cancelAnimationFrame( raf );
			if ( zxReader ) {
				try {
					zxReader.reset();
				} catch ( e ) {
					/* noop */
				}
			}
			if ( stream ) stream.getTracks().forEach( ( t ) => t.stop() );
			container.innerHTML = '';
		};

		const fail = ( msg ) => {
			stop();
			onError && onError( msg );
		};

		const done = ( code ) => {
			if ( stopped ) return;
			const digits = String( code ).replace( /\D+/g, '' );
			if ( digits.length < 6 ) return;
			stop();
			onResult( digits );
		};

		( async () => {
			if ( ! navigator.mediaDevices?.getUserMedia ) {
				return fail( cfg.i18n?.cameraMissing || 'No camera available.' );
			}
			const native = await hasNative();
			if ( native ) {
				try {
					stream = await navigator.mediaDevices.getUserMedia( { video: { facingMode: { ideal: 'environment' } }, audio: false } );
				} catch ( e ) {
					return fail( e.name === 'NotAllowedError' ? cfg.i18n?.cameraDenied : cfg.i18n?.cameraMissing );
				}
				if ( stopped ) return stream.getTracks().forEach( ( t ) => t.stop() );
				video.srcObject = stream;
				await video.play().catch( () => {} );
				const detector = new window.BarcodeDetector( { formats: FORMATS } );
				hint.textContent = 'Point the camera at the barcode';
				const tick = async () => {
					if ( stopped ) return;
					if ( video.readyState >= 2 ) {
						try {
							const codes = await detector.detect( video );
							if ( codes.length ) return done( codes[ 0 ].rawValue );
						} catch ( e ) {
							/* frame not ready */
						}
					}
					raf = requestAnimationFrame( tick );
				};
				tick();
				return;
			}

			// Fallback: ZXing.
			hint.textContent = cfg.i18n?.loading || 'Loading...';
			let ZXing;
			try {
				ZXing = await loadZxing();
			} catch ( e ) {
				return fail( cfg.i18n?.error || 'Scanner failed to load.' );
			}
			if ( stopped ) return;
			const hints = new Map();
			hints.set( ZXing.DecodeHintType.POSSIBLE_FORMATS, [
				ZXing.BarcodeFormat.EAN_13,
				ZXing.BarcodeFormat.EAN_8,
				ZXing.BarcodeFormat.UPC_A,
				ZXing.BarcodeFormat.UPC_E,
				ZXing.BarcodeFormat.CODE_128,
			] );
			zxReader = new ZXing.BrowserMultiFormatReader( hints, 300 );
			hint.textContent = 'Point the camera at the barcode';
			try {
				await zxReader.decodeFromConstraints( { video: { facingMode: { ideal: 'environment' } }, audio: false }, video, ( result, err ) => {
					if ( result ) done( result.getText() );
					else if ( err && err.name === 'NotAllowedError' ) fail( cfg.i18n?.cameraDenied );
				} );
			} catch ( e ) {
				fail( e.name === 'NotAllowedError' ? cfg.i18n?.cameraDenied : cfg.i18n?.cameraMissing );
			}
		} )();

		return { stop };
	}

	window.ymoveScanner = { open };
} )();
