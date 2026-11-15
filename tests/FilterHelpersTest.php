<?php
/**
 * Unit tests for recipient filter sanitization and date-range helpers.
 *
 * Run with: php tests/FilterHelpersTest.php
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Minimal WordPress sanitize_key stub for CLI tests.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Minimal WordPress absint stub for CLI tests.
	 *
	 * @param mixed $maybeint Raw integer value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * Minimal WordPress wp_timezone stub for CLI tests.
	 *
	 * @return DateTimeZone
	 */
	function wp_timezone() {
		return new DateTimeZone( 'UTC' );
	}
}

require_once __DIR__ . '/../includes/class-joopeter-email-to-product-buyers.php';

/**
 * Simple assertion helper for standalone CLI execution.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $label    Test case label.
 */
function joopeterewb_assert_same( $expected, $actual, string $label ): void {
	if ( $expected !== $actual ) {
		fwrite(
			STDERR,
			sprintf(
				"FAIL [%s]\nExpected: %s\nActual:   %s\n",
				$label,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
		exit( 1 );
	}
}

// 1. sanitize_statuses(): typical, edge (wc- prefix + duplicates), and failure/invalid inputs.
joopeterewb_assert_same(
	array( 'cancelled', 'completed', 'processing' ),
	Joopeterewb_Email_To_Product_Buyers::sanitize_statuses( array( 'processing', 'wc-cancelled', 'completed', 'processing' ) ),
	'sanitize_statuses normalizes wc- prefix, deduplicates, and sorts'
);

joopeterewb_assert_same(
	array( 'cancelled' ),
	Joopeterewb_Email_To_Product_Buyers::sanitize_statuses( 'WC-CANCELLED' ),
	'sanitize_statuses handles uppercase scalar input'
);

joopeterewb_assert_same(
	array(),
	Joopeterewb_Email_To_Product_Buyers::sanitize_statuses( array( 'trash', "processing' OR 1=1", array( 'nested' ), null ) ),
	'sanitize_statuses rejects invalid, non-scalar, and injection-like statuses'
);

joopeterewb_assert_same(
	array( 'processing', 'completed', 'on-hold' ),
	Joopeterewb_Email_To_Product_Buyers::sanitize_statuses( array(), Joopeterewb_Email_To_Product_Buyers::DEFAULT_STATUSES ),
	'sanitize_statuses returns explicit fallback when empty'
);

// 2. sanitize_years(): typical, edge (duplicates/unsorted/bounds), and failure inputs.
joopeterewb_assert_same(
	array( 2024, 2026 ),
	Joopeterewb_Email_To_Product_Buyers::sanitize_years( array( '2026', 2024, '2026' ) ),
	'sanitize_years parses, deduplicates, and sorts ascending'
);

joopeterewb_assert_same(
	array( 1970, 2100 ),
	Joopeterewb_Email_To_Product_Buyers::sanitize_years( array( '1969', '1970', '2100', '2101' ) ),
	'sanitize_years enforces inclusive 1970..2100 bounds'
);

joopeterewb_assert_same(
	array(),
	Joopeterewb_Email_To_Product_Buyers::sanitize_years( array( '2026; DROP TABLE', '-2026', 'abcd', null ) ),
	'sanitize_years rejects malformed and non-digit inputs'
);

// 3. build_year_date_ranges(): UTC conversion with non-UTC offset, local time mode, and invalid input.
$tehran_tz = new DateTimeZone( 'Asia/Tehran' ); // UTC+03:30 year-round (since 2022).
joopeterewb_assert_same(
	array(
		array(
			'start' => '2025-12-31 20:30:00',
			'end'   => '2026-12-31 20:29:59',
		),
	),
	Joopeterewb_Email_To_Product_Buyers::build_year_date_ranges( array( 2026 ), true, $tehran_tz ),
	'build_year_date_ranges converts site-local year boundaries to UTC for HPOS'
);

joopeterewb_assert_same(
	array(
		array(
			'start' => '2024-01-01 00:00:00',
			'end'   => '2024-12-31 23:59:59',
		),
		array(
			'start' => '2026-01-01 00:00:00',
			'end'   => '2026-12-31 23:59:59',
		),
	),
	Joopeterewb_Email_To_Product_Buyers::build_year_date_ranges( array( 2026, 2024 ), false, $tehran_tz ),
	'build_year_date_ranges keeps local boundaries when to_utc is false for legacy post_date'
);

joopeterewb_assert_same(
	array(),
	Joopeterewb_Email_To_Product_Buyers::build_year_date_ranges( array( 'invalid' ), true, $tehran_tz ),
	'build_year_date_ranges returns empty array for invalid years'
);

fwrite( STDOUT, "All 10 filter helper assertions passed.\n" );
