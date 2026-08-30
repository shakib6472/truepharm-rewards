<?php
/**
 * TruePharm Rewards — custom points engine (user meta, no plugin).
 *
 * Earn: 5% back in credit on completed orders, plus a referral reward once the
 * referred customer places a qualifying order. Redeem: single-use WooCommerce coupons.
 *
 * @package TruePharm_Rewards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------
 * Constants
 * ------------------------------------------------------------------- */
if ( ! defined( 'TP_REWARDS_POINTS_PER_DOLLAR' ) ) {
	define( 'TP_REWARDS_POINTS_PER_DOLLAR', 1 );
}
if ( ! defined( 'TP_REWARDS_POINTS_VALUE' ) ) {
	define( 'TP_REWARDS_POINTS_VALUE', 0.05 ); // 1 point = $0.05, so a $1 spend returns 5% in credit.
}
if ( ! defined( 'TP_REWARDS_REFERRAL_POINTS' ) ) {
	define( 'TP_REWARDS_REFERRAL_POINTS', 200 ); // $10 for the referrer.
}
if ( ! defined( 'TP_REWARDS_REFERRAL_DISCOUNT' ) ) {
	define( 'TP_REWARDS_REFERRAL_DISCOUNT', 10 ); // $10 off for the referred customer.
}
if ( ! defined( 'TP_REWARDS_REFERRAL_MIN_ORDER' ) ) {
	define( 'TP_REWARDS_REFERRAL_MIN_ORDER', 50 ); // Both sides need a $50 order.
}

/** Minimum / increment for redemption. */
if ( ! defined( 'TP_REWARDS_REDEEM_STEP' ) ) {
	define( 'TP_REWARDS_REDEEM_STEP', 100 );
}

/** User meta keys. */
const TP_REWARDS_POINTS_KEY    = 'tp_rewards_points';
const TP_REWARDS_LEDGER_KEY    = 'tp_rewards_ledger';
const TP_REWARDS_REFCODE_KEY   = 'tp_rewards_referral_code';
const TP_REWARDS_REFERRED_KEY  = 'tp_rewards_referred_by';
const TP_REWARDS_BIRTHDAY_KEY  = 'tp_rewards_birthday';
const TP_REWARDS_REVIEWED_KEY  = 'tp_rewards_reviewed_products';
const TP_REWARDS_BDAY_YEAR_KEY = 'tp_rewards_birthday_year';
const TP_REWARDS_COOKIE        = 'tp_referral_code';
const TP_REWARDS_REFPAID_KEY   = 'tp_rewards_referral_paid';
const TP_REWARDS_REFCOUPON_KEY = 'tp_rewards_referral_coupon';

/* ---------------------------------------------------------------------
 * Core balance + ledger
 * ------------------------------------------------------------------- */
function tp_rewards_get_balance( int $user_id = 0 ): int {
	if ( 0 === $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( 0 === $user_id ) {
		return 0;
	}
	return max( 0, (int) get_user_meta( $user_id, TP_REWARDS_POINTS_KEY, true ) );
}

function tp_rewards_get_ledger( int $user_id = 0 ): array {
	if ( 0 === $user_id ) {
		$user_id = get_current_user_id();
	}
	$ledger = get_user_meta( $user_id, TP_REWARDS_LEDGER_KEY, true );
	return is_array( $ledger ) ? $ledger : array();
}

/**
 * Append a ledger entry: [date, reason, points, balance].
 */
function tp_rewards_log( int $user_id, int $points, string $reason, int $balance ): void {
	$ledger   = tp_rewards_get_ledger( $user_id );
	$ledger[] = array(
		'date'    => current_time( 'mysql' ),
		'reason'  => $reason,
		'points'  => $points,
		'balance' => $balance,
	);
	update_user_meta( $user_id, TP_REWARDS_LEDGER_KEY, $ledger );
}

function tp_rewards_add_points( int $user_id, int $points, string $reason ): bool {
	if ( $user_id <= 0 || $points <= 0 ) {
		return false;
	}
	$balance = tp_rewards_get_balance( $user_id ) + $points;
	update_user_meta( $user_id, TP_REWARDS_POINTS_KEY, $balance );
	tp_rewards_log( $user_id, $points, $reason, $balance );

	/** Fires after points are added. */
	do_action( 'tp_rewards_points_added', $user_id, $points, $reason, $balance );
	return true;
}

function tp_rewards_deduct_points( int $user_id, int $points, string $reason ): bool {
	if ( $user_id <= 0 || $points <= 0 ) {
		return false;
	}
	$current = tp_rewards_get_balance( $user_id );
	if ( $current < $points ) {
		return false;
	}
	$balance = $current - $points;
	update_user_meta( $user_id, TP_REWARDS_POINTS_KEY, $balance );
	tp_rewards_log( $user_id, -$points, $reason, $balance );

	/** Fires after points are deducted. */
	do_action( 'tp_rewards_points_deducted', $user_id, $points, $reason, $balance );
	return true;
}

function tp_rewards_points_to_value( int $points ): float {
	return round( $points * TP_REWARDS_POINTS_VALUE, 2 );
}

/* ---------------------------------------------------------------------
 * Referral codes
 * ------------------------------------------------------------------- */
function tp_rewards_generate_referral_code( int $user_id ): string {
	$user = get_userdata( $user_id );
	$base = $user ? preg_replace( '/[^A-Za-z0-9]/', '', $user->user_login ) : 'USER';
	$base = strtoupper( substr( $base, 0, 5 ) );
	if ( '' === $base ) {
		$base = 'USER';
	}
	return 'TPUSA-' . $base . $user_id;
}

function tp_rewards_get_referral_code( int $user_id = 0 ): string {
	if ( 0 === $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( 0 === $user_id ) {
		return '';
	}
	$code = (string) get_user_meta( $user_id, TP_REWARDS_REFCODE_KEY, true );
	if ( '' === $code ) {
		$code = tp_rewards_generate_referral_code( $user_id );
		update_user_meta( $user_id, TP_REWARDS_REFCODE_KEY, $code );
	}
	return $code;
}

/* ---------------------------------------------------------------------
 * Back-compat accessors (used by the homepage rewards section).
 * ------------------------------------------------------------------- */
function tp_rewards_points_per_dollar(): int {
	return (int) apply_filters( 'tp_rewards_points_per_dollar', TP_REWARDS_POINTS_PER_DOLLAR );
}
function tp_rewards_redeem_points(): int {
	return (int) apply_filters( 'tp_rewards_redeem_points', TP_REWARDS_REDEEM_STEP );
}
function tp_rewards_redeem_value(): float {
	return tp_rewards_points_to_value( tp_rewards_redeem_points() );
}
function tp_rewards_redeem_value_display(): string {
	if ( function_exists( 'wc_price' ) ) {
		return wp_strip_all_tags( wc_price( tp_rewards_redeem_value(), array( 'decimals' => 0 ) ) );
	}
	return '$' . number_format_i18n( tp_rewards_redeem_value() );
}

/* ---------------------------------------------------------------------
 * Referral cookie — capture ?ref=CODE.
 * ------------------------------------------------------------------- */
function tp_rewards_capture_referral(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['ref'] ) || is_admin() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$code = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
	if ( '' !== $code && ! headers_sent() ) {
		setcookie( TP_REWARDS_COOKIE, $code, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
		$_COOKIE[ TP_REWARDS_COOKIE ] = $code;
	}
}
add_action( 'init', 'tp_rewards_capture_referral' );

/* ---------------------------------------------------------------------
 * Registration — link the referral. No points are awarded here.
 * ------------------------------------------------------------------- */
/**
 * Give the referred customer their welcome discount coupon.
 *
 * Fixed amount, single use, tied to their email, and only valid on an order
 * that meets the referral minimum.
 */
function tp_rewards_create_referral_coupon( int $user_id ): string {
	if ( ! class_exists( 'WC_Coupon' ) ) {
		return '';
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}

	$code   = strtoupper( 'TP-WELCOME-' . $user_id );
	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'fixed_cart' );
	$coupon->set_amount( TP_REWARDS_REFERRAL_DISCOUNT );
	$coupon->set_minimum_amount( TP_REWARDS_REFERRAL_MIN_ORDER );
	$coupon->set_individual_use( true );
	$coupon->set_usage_limit( 1 );
	$coupon->set_usage_limit_per_user( 1 );
	$coupon->set_email_restrictions( array( $user->user_email ) );
	$coupon->set_description( __( 'Referral welcome discount', 'truepharm' ) );
	$coupon->save();

	update_user_meta( $user_id, TP_REWARDS_REFCOUPON_KEY, $code );

	return $code;
}

function tp_rewards_on_register( int $user_id ): void {
	// Every account gets its own referral code.
	tp_rewards_get_referral_code( $user_id );

	if ( empty( $_COOKIE[ TP_REWARDS_COOKIE ] ) ) {
		return;
	}

	$code      = sanitize_text_field( wp_unslash( $_COOKIE[ TP_REWARDS_COOKIE ] ) );
	$referrers = get_users(
		array(
			'meta_key'   => TP_REWARDS_REFCODE_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'number'     => 1,
			'fields'     => 'ID',
		)
	);
	$referrer_id = ! empty( $referrers ) ? (int) $referrers[0] : 0;

	if ( $referrer_id && $referrer_id !== $user_id ) {
		update_user_meta( $user_id, TP_REWARDS_REFERRED_KEY, $referrer_id );
		tp_rewards_create_referral_coupon( $user_id );
	}

	// Clear the cookie.
	if ( ! headers_sent() ) {
		setcookie( TP_REWARDS_COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
	}
	unset( $_COOKIE[ TP_REWARDS_COOKIE ] );
}
add_action( 'user_register', 'tp_rewards_on_register' );

/* ---------------------------------------------------------------------
 * Earn — completed orders.
 * ------------------------------------------------------------------- */
/**
 * Pay the referrer once the referred customer's first qualifying order lands.
 */
function tp_rewards_settle_referral( int $user_id, float $eligible ): void {
	$referrer_id = (int) get_user_meta( $user_id, TP_REWARDS_REFERRED_KEY, true );
	if ( ! $referrer_id || get_user_meta( $user_id, TP_REWARDS_REFPAID_KEY, true ) ) {
		return;
	}
	if ( $eligible < TP_REWARDS_REFERRAL_MIN_ORDER ) {
		return;
	}

	tp_rewards_add_points( $referrer_id, TP_REWARDS_REFERRAL_POINTS, __( 'Referral completed', 'truepharm' ) );
	update_user_meta( $user_id, TP_REWARDS_REFPAID_KEY, 1 );
}

function tp_rewards_on_order_completed( int $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	if ( $order->get_meta( '_tp_rewards_awarded' ) ) {
		return; // Already awarded.
	}
	$user_id = $order->get_user_id();
	if ( ! $user_id ) {
		return;
	}

	// Credit is earned on what the customer actually paid for goods, so money
	// covered by store credit or any other coupon earns nothing new.
	$eligible = (float) $order->get_subtotal() - (float) $order->get_discount_total();
	$eligible = max( 0, $eligible );

	$points = (int) floor( $eligible ) * tp_rewards_points_per_dollar();
	if ( $points > 0 ) {
		tp_rewards_add_points(
			$user_id,
			$points,
			sprintf( /* translators: %s: order number. */ __( 'Purchase (Order #%s)', 'truepharm' ), $order->get_order_number() )
		);
	}

	tp_rewards_settle_referral( $user_id, $eligible );

	$order->update_meta_data( '_tp_rewards_awarded', 1 );
	$order->save();
}
add_action( 'woocommerce_order_status_completed', 'tp_rewards_on_order_completed' );

/* ---------------------------------------------------------------------
 * Clean up the retired birthday cron on load.
 * ------------------------------------------------------------------- */
function tp_rewards_clear_birthday_cron(): void {
	$next = wp_next_scheduled( 'tp_birthday_check' );
	if ( $next ) {
		wp_unschedule_event( $next, 'tp_birthday_check' );
	}
}
add_action( 'init', 'tp_rewards_clear_birthday_cron' );

/* ---------------------------------------------------------------------
 * Redeem — AJAX → single-use WooCommerce coupon.
 * ------------------------------------------------------------------- */
function tp_rewards_redeem(): void {
	check_ajax_referer( 'tp_ajax', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Please log in to redeem points.', 'truepharm' ) ), 403 );
	}

	$user_id = get_current_user_id();
	$points  = isset( $_POST['points_to_redeem'] ) ? absint( wp_unslash( $_POST['points_to_redeem'] ) ) : 0;

	if ( $points < TP_REWARDS_REDEEM_STEP || 0 !== $points % TP_REWARDS_REDEEM_STEP ) {
		wp_send_json_error(
			array(
				/* translators: %d: redemption step. */
				'message' => sprintf( __( 'Points must be a multiple of %d.', 'truepharm' ), TP_REWARDS_REDEEM_STEP ),
			),
			400
		);
	}

	if ( tp_rewards_get_balance( $user_id ) < $points ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient points balance.', 'truepharm' ) ), 400 );
	}

	if ( ! class_exists( 'WC_Coupon' ) ) {
		wp_send_json_error( array( 'message' => __( 'Coupons are unavailable.', 'truepharm' ) ), 500 );
	}

	$amount = tp_rewards_points_to_value( $points );
	$code   = strtoupper( 'TP-REDEEM-' . $user_id . '-' . time() );
	$user   = wp_get_current_user();

	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'fixed_cart' );
	$coupon->set_amount( $amount );
	$coupon->set_individual_use( true );
	$coupon->set_usage_limit( 1 );
	$coupon->set_usage_limit_per_user( 1 );
	$coupon->set_date_expires( time() + ( 30 * DAY_IN_SECONDS ) );
	$coupon->set_description( sprintf( /* translators: 1: points, 2: user. */ __( 'Rewards redemption: %1$d points by user #%2$d', 'truepharm' ), $points, $user_id ) );
	if ( $user && $user->user_email ) {
		$coupon->set_email_restrictions( array( $user->user_email ) );
	}
	$coupon->save();

	if ( ! tp_rewards_deduct_points( $user_id, $points, sprintf( /* translators: %s: coupon code. */ __( 'Redeemed coupon %s', 'truepharm' ), $code ) ) ) {
		// Roll back the coupon if deduction failed.
		wp_delete_post( $coupon->get_id(), true );
		wp_send_json_error( array( 'message' => __( 'Redemption failed. Please try again.', 'truepharm' ) ), 500 );
	}

	wp_send_json_success(
		array(
			'code'    => $code,
			'amount'  => wp_strip_all_tags( wc_price( $amount ) ),
			'balance' => tp_rewards_get_balance( $user_id ),
			/* translators: 1: discount, 2: code. */
			'message' => sprintf( __( 'Success! %1$s coupon "%2$s" created.', 'truepharm' ), wp_strip_all_tags( wc_price( $amount ) ), $code ),
		)
	);
}
add_action( 'wp_ajax_tp_redeem_points', 'tp_rewards_redeem' );
