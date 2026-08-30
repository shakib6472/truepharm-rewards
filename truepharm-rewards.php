<?php
/**
 * Plugin Name: TruePharm Rewards
 * Description: Store credit, referral and volume reward logic for TruePharm USA. Kept as a site specific plugin so the data and rules survive a theme change.
 * Version:     1.0.0
 * Author:      Black Diamond
 * Text Domain: truepharm
 *
 * @package TruePharm_Rewards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TP_REWARDS_PLUGIN_FILE', __FILE__ );
define( 'TP_REWARDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TP_REWARDS_PLUGIN_VERSION', '1.0.0' );

require_once TP_REWARDS_PLUGIN_DIR . 'includes/rewards.php';
