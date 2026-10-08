<?php
/**
 * Custom tables: member diary, targets, food cache, calculator leads.
 *
 * @package YMove_Nutrition
 */

namespace YMove_Nutrition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DB {

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'ymove_' . $name;
	}

	/**
	 * Create or upgrade tables. Runs on activation and when the DB version bumps.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$log     = self::table( 'log' );
		$targets = self::table( 'targets' );
		$cache   = self::table( 'food_cache' );
		$leads   = self::table( 'leads' );

		$sql = "CREATE TABLE {$log} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			logged_on DATE NOT NULL,
			meal VARCHAR(16) NOT NULL DEFAULT 'snack',
			food_id VARCHAR(64) NULL,
			source VARCHAR(16) NOT NULL DEFAULT 'search',
			display_name VARCHAR(255) NOT NULL,
			brand VARCHAR(191) NULL,
			serving_g DECIMAL(9,2) NOT NULL DEFAULT 0,
			quantity DECIMAL(8,2) NOT NULL DEFAULT 1,
			kcal DECIMAL(9,2) NOT NULL DEFAULT 0,
			protein_g DECIMAL(9,2) NOT NULL DEFAULT 0,
			carbs_g DECIMAL(9,2) NOT NULL DEFAULT 0,
			fat_g DECIMAL(9,2) NOT NULL DEFAULT 0,
			fiber_g DECIMAL(9,2) NULL,
			sugar_g DECIMAL(9,2) NULL,
			sodium_mg DECIMAL(9,2) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_day (user_id, logged_on),
			KEY user_food (user_id, food_id)
		) {$charset};
		CREATE TABLE {$targets} (
			user_id BIGINT UNSIGNED NOT NULL,
			kcal INT UNSIGNED NOT NULL DEFAULT 2000,
			protein_g INT UNSIGNED NOT NULL DEFAULT 150,
			carbs_g INT UNSIGNED NOT NULL DEFAULT 200,
			fat_g INT UNSIGNED NOT NULL DEFAULT 67,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (user_id)
		) {$charset};
		CREATE TABLE {$cache} (
			food_id VARCHAR(64) NOT NULL,
			payload LONGTEXT NOT NULL,
			fetched_at DATETIME NOT NULL,
			PRIMARY KEY  (food_id)
		) {$charset};
		CREATE TABLE {$leads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL,
			results LONGTEXT NULL,
			page_url VARCHAR(255) NULL,
			consent TINYINT(1) NOT NULL DEFAULT 0,
			ip_hash CHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'ymove_nutrition_db_version', YMOVE_NUTRITION_DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'ymove_nutrition_db_version' ) !== YMOVE_NUTRITION_DB_VERSION ) {
			self::install();
		}
	}

	/* ---------------------------------------------------------------- Diary */

	public static function entries_for_day( int $user_id, string $day ): array {
		global $wpdb;
		$table = self::table( 'log' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d AND logged_on = %s ORDER BY id ASC", $table, $user_id, $day ),
			ARRAY_A
		);
		return array_map( array( __CLASS__, 'format_entry' ), $rows ?: array() );
	}

	public static function insert_entry( int $user_id, array $e ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table( 'log' ),
			array(
				'user_id'      => $user_id,
				'logged_on'    => $e['logged_on'],
				'meal'         => $e['meal'],
				'food_id'      => $e['food_id'],
				'source'       => $e['source'],
				'display_name' => $e['display_name'],
				'brand'        => $e['brand'],
				'serving_g'    => $e['serving_g'],
				'quantity'     => $e['quantity'],
				'kcal'         => $e['kcal'],
				'protein_g'    => $e['protein_g'],
				'carbs_g'      => $e['carbs_g'],
				'fat_g'        => $e['fat_g'],
				'fiber_g'      => $e['fiber_g'],
				'sugar_g'      => $e['sugar_g'],
				'sodium_mg'    => $e['sodium_mg'],
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_entry( int $user_id, int $id ): ?array {
		global $wpdb;
		$table = self::table( 'log' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d AND user_id = %d", $table, $id, $user_id ),
			ARRAY_A
		);
		return $row ? self::format_entry( $row ) : null;
	}

	public static function update_entry( int $user_id, int $id, array $fields ): bool {
		global $wpdb;
		$allowed = array( 'meal', 'quantity', 'logged_on', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'fiber_g', 'sugar_g', 'sodium_mg' );
		$data    = array_intersect_key( $fields, array_flip( $allowed ) );
		if ( ! $data ) {
			return false;
		}
		$res = $wpdb->update( self::table( 'log' ), $data, array( 'id' => $id, 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $res;
	}

	public static function delete_entry( int $user_id, int $id ): bool {
		global $wpdb;
		$res = $wpdb->delete( self::table( 'log' ), array( 'id' => $id, 'user_id' => $user_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $res;
	}

	/**
	 * Daily totals for a date range (inclusive), for the week view.
	 */
	public static function daily_totals( int $user_id, string $from, string $to ): array {
		global $wpdb;
		$table = self::table( 'log' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT logged_on, SUM(kcal) kcal, SUM(protein_g) protein_g, SUM(carbs_g) carbs_g, SUM(fat_g) fat_g, COUNT(*) entries
				 FROM %i WHERE user_id = %d AND logged_on BETWEEN %s AND %s GROUP BY logged_on ORDER BY logged_on ASC", $table,
				$user_id,
				$from,
				$to
			),
			ARRAY_A
		);
		$out = array();
		foreach ( $rows ?: array() as $r ) {
			$out[] = array(
				'date'    => $r['logged_on'],
				'kcal'    => round( (float) $r['kcal'] ),
				'protein' => round( (float) $r['protein_g'], 1 ),
				'carbs'   => round( (float) $r['carbs_g'], 1 ),
				'fat'     => round( (float) $r['fat_g'], 1 ),
				'entries' => (int) $r['entries'],
			);
		}
		return $out;
	}

	/**
	 * Recently logged distinct foods, for the quick-add list. No API call.
	 */
	public static function recent_foods( int $user_id, int $limit = 12 ): array {
		global $wpdb;
		$table = self::table( 'log' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT food_id, display_name, brand, serving_g, kcal, protein_g, carbs_g, fat_g, fiber_g, sugar_g, sodium_mg, quantity, MAX(id) last_id, COUNT(*) times
				 FROM %i WHERE user_id = %d AND food_id IS NOT NULL AND food_id <> ''
				 GROUP BY food_id ORDER BY last_id DESC LIMIT %d", $table,
				$user_id,
				$limit
			),
			ARRAY_A
		);
		$out = array();
		foreach ( $rows ?: array() as $r ) {
			$q     = max( 0.01, (float) $r['quantity'] );
			$out[] = array(
				'id'          => $r['food_id'],
				'displayName' => $r['display_name'],
				'brand'       => $r['brand'],
				'servingSize' => (float) $r['serving_g'],
				'calories'    => round( (float) $r['kcal'] / $q, 1 ),
				'protein'     => round( (float) $r['protein_g'] / $q, 1 ),
				'carbs'       => round( (float) $r['carbs_g'] / $q, 1 ),
				'fat'         => round( (float) $r['fat_g'] / $q, 1 ),
				'fiber'       => null === $r['fiber_g'] ? null : round( (float) $r['fiber_g'] / $q, 1 ),
				'sugar'       => null === $r['sugar_g'] ? null : round( (float) $r['sugar_g'] / $q, 1 ),
				'sodium'      => null === $r['sodium_mg'] ? null : round( (float) $r['sodium_mg'] / $q, 1 ),
				'times'       => (int) $r['times'],
			);
		}
		return $out;
	}

	public static function format_entry( array $r ): array {
		return array(
			'id'          => (int) $r['id'],
			'date'        => $r['logged_on'],
			'meal'        => $r['meal'],
			'foodId'      => $r['food_id'],
			'source'      => $r['source'],
			'displayName' => $r['display_name'],
			'brand'       => $r['brand'],
			'servingG'    => (float) $r['serving_g'],
			'quantity'    => (float) $r['quantity'],
			'kcal'        => round( (float) $r['kcal'], 1 ),
			'protein'     => round( (float) $r['protein_g'], 1 ),
			'carbs'       => round( (float) $r['carbs_g'], 1 ),
			'fat'         => round( (float) $r['fat_g'], 1 ),
			'fiber'       => null === $r['fiber_g'] ? null : round( (float) $r['fiber_g'], 1 ),
			'sugar'       => null === $r['sugar_g'] ? null : round( (float) $r['sugar_g'], 1 ),
			'sodium'      => null === $r['sodium_mg'] ? null : round( (float) $r['sodium_mg'], 1 ),
		);
	}

	/* -------------------------------------------------------------- Targets */

	public static function get_targets( int $user_id ): array {
		global $wpdb;
		$table = self::table( 'targets' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d", $table, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return array( 'kcal' => 2000, 'protein' => 150, 'carbs' => 200, 'fat' => 67, 'isDefault' => true );
		}
		return array(
			'kcal'      => (int) $row['kcal'],
			'protein'   => (int) $row['protein_g'],
			'carbs'     => (int) $row['carbs_g'],
			'fat'       => (int) $row['fat_g'],
			'isDefault' => false,
		);
	}

	public static function set_targets( int $user_id, array $t ): void {
		global $wpdb;
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table( 'targets' ),
			array(
				'user_id'    => $user_id,
				'kcal'       => $t['kcal'],
				'protein_g'  => $t['protein'],
				'carbs_g'    => $t['carbs'],
				'fat_g'      => $t['fat'],
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	/* ----------------------------------------------------------- Food cache */

	public static function cached_food( string $food_id ): ?array {
		global $wpdb;
		$table = self::table( 'food_cache' );
		$json  = $wpdb->get_var( $wpdb->prepare( "SELECT payload FROM %i WHERE food_id = %s", $table, $food_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $json ) {
			return null;
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	public static function cache_food( array $food ): void {
		global $wpdb;
		if ( empty( $food['id'] ) ) {
			return;
		}
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table( 'food_cache' ),
			array(
				'food_id'    => (string) $food['id'],
				'payload'    => wp_json_encode( $food ),
				'fetched_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/* ---------------------------------------------------------------- Leads */

	public static function insert_lead( string $email, array $results, string $page_url, bool $consent = false ): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table( 'leads' ),
			array(
				'email'      => $email,
				'results'    => wp_json_encode( $results ),
				'page_url'   => $page_url,
				'consent'    => $consent ? 1 : 0,
				'ip_hash'    => hash( 'sha256', client_ip() . wp_salt( 'nonce' ) ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function leads( int $limit = 500, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table( 'leads' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d", $table, $limit, $offset ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as &$r ) {
			$r['results'] = json_decode( (string) $r['results'], true ) ?: array();
		}
		return $rows;
	}

	public static function leads_count(): int {
		global $wpdb;
		$table = self::table( 'leads' );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function delete_lead( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table( 'leads' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/* ----------------------------------------------------------- Retention */

	public static function purge_old_entries( int $days ): void {
		global $wpdb;
		if ( $days < 1 ) {
			return;
		}
		$table  = self::table( 'log' );
		$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE logged_on < %s", $table, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function delete_user_data( int $user_id ): int {
		global $wpdb;
		$n  = (int) $wpdb->delete( self::table( 'log' ), array( 'user_id' => $user_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n += (int) $wpdb->delete( self::table( 'targets' ), array( 'user_id' => $user_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $n;
	}

	public static function delete_leads_by_email( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( self::table( 'leads' ), array( 'email' => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function leads_by_email( string $email ): array {
		global $wpdb;
		$table = self::table( 'leads' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE email = %s", $table, $email ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public static function all_entries( int $user_id ): array {
		global $wpdb;
		$table = self::table( 'log' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE user_id = %d ORDER BY logged_on ASC, id ASC", $table, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( array( __CLASS__, 'format_entry' ), $rows ?: array() );
	}

	/**
	 * Members with a log, for the coach screen.
	 */
	public static function members_summary( int $limit = 200 ): array {
		global $wpdb;
		$table = self::table( 'log' );
		$since = gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS );
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_id, MAX(logged_on) last_day, COUNT(*) entries,
				        SUM(CASE WHEN logged_on >= %s THEN kcal ELSE 0 END) kcal_7d,
				        COUNT(DISTINCT CASE WHEN logged_on >= %s THEN logged_on END) days_7d
				 FROM %i GROUP BY user_id ORDER BY last_day DESC LIMIT %d",
				$since,
				$since,
				$table,
				$limit
			),
			ARRAY_A
		) ?: array();
	}
}
