<?php

/*
* Plugin Name:       TruePharm Rewards
* Plugin URI:        https://github.com/shakib6472/truepharm-rewards
* Description:       Store credit, referral and volume reward logic for TruePharm USA. Kept as a site specific plugin so the data and rules survive a theme change.
* Version:           1.0.0
* Requires at least: 5.2
* Requires PHP:      7.2
* Author:            Shakib Shown
* Author URI:        https://github.com/shakib6472/
* License:           GPL v2 or later
* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:       truepharm
* Domain Path:       /languages
* @package TruePharm_Rewards
*/

if (!defined('ABSPATH')) {
exit; // Exit if accessed directly.
}

 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TP_REWARDS_PLUGIN_FILE', __FILE__ );
define( 'TP_REWARDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TP_REWARDS_PLUGIN_VERSION', '1.0.0' );

require_once TP_REWARDS_PLUGIN_DIR . 'includes/rewards.php';
