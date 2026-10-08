<?php
/**
 * Calculator lead capture: store, email the visitor their results, notify
 * the owner, fire the webhook (Zapier / Make / n8n) and the PHP action.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Leads {

	/**
	 * Handle one captured lead end to end. $results is the sanitized scalar
	 * map the calculator posted (bmr, tdee, target, goal, protein_g, ...).
	 */
	public static function capture( string $email, array $results, string $page, bool $consent, ?array $message = null ): void {
		$id = DB::insert_lead( $email, $results, $page, $consent );

		if ( $message ) {
			Mailer::send( array_merge( $message, array(
				'to'       => $email,
				'reply_to' => is_email( Settings::get( 'lead_notify_email' ) ) ? Settings::get( 'lead_notify_email' ) : get_option( 'admin_email' ),
				'context'  => 'lead',
			) ) );
		} else {
			self::email_visitor( $email, $results );
		}
		self::notify_owner( $email, $results, $page );
		self::webhook( $id, $email, $results, $page, $consent );

		/**
		 * Fires after a calculator lead is stored and emailed. Hook your CRM here.
		 *
		 * @param string $email
		 * @param array  $results
		 * @param string $page
		 * @param bool   $consent
		 */
		do_action( 'ymove_calculator_lead', $email, $results, $page, $consent );
	}

	/* ------------------------------------------------------------ Visitor */

	private static function email_visitor( string $email, array $r ): void {
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = trim( (string) Settings::get( 'lead_subject' ) ) ?: sprintf(
			/* translators: %s: site name */
			__( 'Your calorie results from %s', 'ymove-nutrition' ),
			$site
		);
		$subject = str_replace( array( '{site}', '{target}' ), array( $site, num( $r['target'] ?? 0 ) ), $subject );
		$message = self::results_message( $r );

		Mailer::send( array_merge( $message, array(
			'to'       => $email,
			'subject'  => $subject,
			'reply_to' => is_email( Settings::get( 'lead_notify_email' ) ) ? Settings::get( 'lead_notify_email' ) : get_option( 'admin_email' ),
			'context'  => 'lead',
		) ) );
	}

	/**
	 * The results email as a structured message (see Mailer::send): owner
	 * intro, the numbers, owner CTA button and the formula note.
	 */
	public static function results_message( array $r ): array {
		$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$intro = trim( (string) Settings::get( 'lead_intro' ) );
		$intro = $intro ? str_replace( '{site}', $site, $intro ) : __( 'Here are the numbers you calculated. Save this email so you have them handy.', 'ymove-nutrition' );

		$goal = '';
		foreach ( Settings::goals() as $g ) {
			if ( $g['key'] === ( $r['goal'] ?? '' ) ) {
				$goal = lcfirst( preg_replace( '/\s*\(.*$/', '', $g['label'] ) );
			}
		}

		$rows = array();
		if ( ! empty( $r['target'] ) ) {
			/* translators: %s: goal, e.g. "lose weight" */
			$rows[] = array( sprintf( __( 'Daily calories to %s', 'ymove-nutrition' ), $goal ?: __( 'reach your goal', 'ymove-nutrition' ) ), num( $r['target'] ) . ' kcal', true );
		}
		if ( ! empty( $r['bmr'] ) ) {
			$rows[] = array( __( 'BMR (calories at rest)', 'ymove-nutrition' ), num( $r['bmr'] ) . ' kcal', false );
		}
		if ( ! empty( $r['tdee'] ) ) {
			$rows[] = array( __( 'TDEE (maintenance)', 'ymove-nutrition' ), num( $r['tdee'] ) . ' kcal', false );
		}
		if ( ! empty( $r['protein_g'] ) ) {
			$rows[] = array( __( 'Protein', 'ymove-nutrition' ), num( $r['protein_g'] ) . ' g', false );
			$rows[] = array( __( 'Carbohydrates', 'ymove-nutrition' ), num( $r['carbs_g'] ?? 0 ) . ' g', false );
			$rows[] = array( __( 'Fat', 'ymove-nutrition' ), num( $r['fat_g'] ?? 0 ) . ' g', false );
		}
		if ( ! empty( $r['bmi'] ) ) {
			$rows[] = array( __( 'BMI', 'ymove-nutrition' ), num( $r['bmi'], 1 ) . ( ! empty( $r['category'] ) ? ' (' . $r['category'] . ')' : '' ), true );
		}

		$formula_names = array( 'mifflin' => 'Mifflin-St Jeor', 'harris' => 'Harris-Benedict', 'katch' => 'Katch-McArdle' );
		$note          = '';
		if ( ! empty( $r['target'] ) || ! empty( $r['bmr'] ) ) {
			/* translators: %s: formula name */
			$note = sprintf( __( 'Calculated with the %s formula. These are estimates, not medical advice.', 'ymove-nutrition' ), $formula_names[ $r['formula'] ?? 'mifflin' ] ?? 'Mifflin-St Jeor' );
		}

		$cta_label = trim( (string) Settings::get( 'lead_cta_label' ) );
		$cta_url   = trim( (string) Settings::get( 'lead_cta_url' ) );

		/**
		 * Filter the structured results email before it is sent.
		 *
		 * @param array $message heading, intro, rows, cta, note.
		 * @param array $results
		 */
		return (array) apply_filters( 'ymove_lead_email', array(
			'heading' => $site,
			'intro'   => $intro,
			'rows'    => $rows,
			'cta'     => $cta_label && $cta_url ? array( $cta_label, $cta_url ) : array(),
			'note'    => $note,
		), $r );
	}

	/* -------------------------------------------------------------- Owner */

	private static function notify_owner( string $email, array $r, string $page ): void {
		$notify = Settings::get( 'lead_notify_email' );
		if ( ! $notify || ! is_email( $notify ) ) {
			return;
		}
		$rows = array( array( __( 'Email', 'ymove-nutrition' ), $email, true ), array( __( 'Page', 'ymove-nutrition' ), $page, false ) );
		foreach ( $r as $k => $v ) {
			$rows[] = array( (string) $k, (string) $v, false );
		}
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		Mailer::send( array(
			'to'       => $notify,
			/* translators: %s: site name */
			'subject'  => sprintf( 'mealplan' === ( $results['type'] ?? '' ) ? __( '[%s] New meal plan lead', 'ymove-nutrition' ) : __( '[%s] New calorie calculator lead', 'ymove-nutrition' ), $site ),
			'heading'  => 'mealplan' === ( $results['type'] ?? '' ) ? __( 'New meal plan lead', 'ymove-nutrition' ) : __( 'New calculator lead', 'ymove-nutrition' ),
			'intro'    => '',
			'rows'     => $rows,
			'cta'      => array( __( 'View all leads', 'ymove-nutrition' ), admin_url( 'options-general.php?page=ymove-nutrition&tab=leads' ) ),
			'reply_to' => $email,
			'context'  => 'owner',
		) );
	}

	/* ------------------------------------------------------------ Webhook */

	/**
	 * POST the lead as JSON to the configured URL. Non-blocking: Zapier,
	 * Make and n8n all accept this shape as a catch hook.
	 */
	private static function webhook( int $id, string $email, array $r, string $page, bool $consent ): void {
		$url = Settings::get( 'lead_webhook' );
		if ( ! $url ) {
			return;
		}
		wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array(
					'id'         => $id,
					'email'      => $email,
					'consent'    => $consent,
					'page'       => $page,
					'site'       => home_url(),
					'created_at' => gmdate( 'c' ),
					'results'    => $r,
				) ),
			)
		);
	}

	/* ------------------------------------------------------------ Captcha */

	/**
	 * Verify a reCAPTCHA v3 or Turnstile token server-side. Returns true when
	 * no captcha is configured.
	 */
	public static function verify_captcha( string $token ): bool {
		$provider = Settings::get( 'captcha_provider' );
		$secret   = (string) Settings::get( 'captcha_secret' );
		if ( ! $provider || ! $secret ) {
			return true;
		}
		if ( '' === $token ) {
			return false;
		}
		$url = 'recaptcha' === $provider
			? 'https://www.google.com/recaptcha/api/siteverify'
			: 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
		$res = wp_remote_post( $url, array(
			'timeout' => 8,
			'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => client_ip() ),
		) );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['success'] ) ) {
			return false;
		}
		if ( 'recaptcha' === $provider ) {
			// v3 returns a score; 0.5 is Google's suggested threshold.
			return ( (float) ( $body['score'] ?? 0 ) ) >= (float) apply_filters( 'ymove_recaptcha_min_score', 0.5 );
		}
		return true;
	}

	/* ------------------------------------------------------------- Export */

	public static function csv(): string {
		$cols = array( 'id', 'created_at', 'email', 'consent', 'target', 'goal', 'bmr', 'tdee', 'protein_g', 'carbs_g', 'fat_g', 'bmi', 'page_url' );
		$csv  = self::csv_row( $cols );
		foreach ( DB::leads( 10000 ) as $l ) {
			$r    = $l['results'];
			$csv .= self::csv_row( array( $l['id'], $l['created_at'], $l['email'], $l['consent'], $r['target'] ?? '', $r['goal'] ?? '', $r['bmr'] ?? '', $r['tdee'] ?? '', $r['protein_g'] ?? '', $r['carbs_g'] ?? '', $r['fat_g'] ?? '', $r['bmi'] ?? '', $l['page_url'] ) );
		}
		return $csv;
	}

	/**
	 * One RFC 4180 line. Cells a spreadsheet would run as a formula
	 * (=, +, -, @) get a leading apostrophe.
	 */
	private static function csv_row( array $cells ): string {
		return implode(
			',',
			array_map(
				function ( $v ) {
					$v = (string) $v;
					if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $v ) ) {
						$v = "'" . $v;
					}
					return '"' . str_replace( '"', '""', $v ) . '"';
				},
				$cells
			)
		) . "\r\n";
	}
}
