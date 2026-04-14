<?php
/**
 * Plugin Name:       wAnswers - SEO-First Q&A
 * Plugin URI:        https://wbuild.dev/wanswers/
 * Description:       SEO-first community Q&A with QAPage JSON-LD schema, voting, badges, leaderboard, and email notifications. Optimised for Google and Generative Engine Optimization (GEO / AI search).
 * Version:           3.0.0
 * Author:            wBuild.dev
 * Author URI:        https://wbuild.dev
 * Text Domain:       wanswers-seo-first-qa
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( defined( 'WANSWERS_VERSION' ) ) return;

define( 'WANSWERS_VERSION',  '3.0.0' );
define( 'WANSWERS_PATH',     plugin_dir_path( __FILE__ ) );
define( 'WANSWERS_URL',      plugin_dir_url( __FILE__ ) );
define( 'WANSWERS_BASENAME', plugin_basename( __FILE__ ) );

// Settings link on Plugins page
add_filter( 'plugin_action_links_' . WANSWERS_BASENAME, 'wanswers_action_links' );
function wanswers_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=wanswers_question&page=wanswers-qa-settings' ) ) . '">' . esc_html__( 'Settings', 'wanswers-seo-first-qa' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

require_once WANSWERS_PATH . 'includes/class-admin.php';
require_once WANSWERS_PATH . 'includes/class-database.php';
require_once WANSWERS_PATH . 'includes/class-post-types.php';
require_once WANSWERS_PATH . 'includes/class-ajax.php';
require_once WANSWERS_PATH . 'includes/class-email.php';
require_once WANSWERS_PATH . 'includes/class-shortcode.php';
require_once WANSWERS_PATH . 'includes/class-leaderboard.php';
require_once WANSWERS_PATH . 'includes/class-schema.php';
require_once WANSWERS_PATH . 'includes/class-digest.php';
require_once WANSWERS_PATH . 'includes/class-badges.php';

function wanswers_init() {
    Wanswers_Post_Types::init();
    Wanswers_Ajax::init();
    Wanswers_Shortcode::init();
    Wanswers_Leaderboard::init();
    Wanswers_Admin::init();
    Wanswers_Schema::init();
    Wanswers_Digest::init();

    // Token-based unsubscribe — works without login, no nonce needed (token IS the auth)
    add_action( 'wp_ajax_nopriv_wanswers_unsubscribe', array( 'Wanswers_Email', 'handle_unsubscribe' ) );
    add_action( 'wp_ajax_wanswers_unsubscribe',        array( 'Wanswers_Email', 'handle_unsubscribe' ) );
}
add_action( 'plugins_loaded', 'wanswers_init' );

register_activation_hook( __FILE__, function() {
    try {
        // Migrate from old cc_qa_ prefixes if upgrading from pre-3.0
        Wanswers_Database::migrate_from_cc_qa();
        Wanswers_Database::install();
    } catch ( Exception $e ) {
        wp_die(
            'wAnswers activation error: ' . esc_html( $e->getMessage() ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }
    // Register CPTs and rewrite rules before flushing so /questions/author/ resolves
    Wanswers_Post_Types::register();
    Wanswers_Post_Types::register_taxonomies();
    Wanswers_Post_Types::add_rewrite_rules();
    flush_rewrite_rules();
    Wanswers_Digest::schedule();
} );
register_deactivation_hook( __FILE__, function() {
    Wanswers_Database::deactivate();
    Wanswers_Digest::unschedule();
} );

add_action( 'wp_enqueue_scripts', 'wanswers_register_assets' );
function wanswers_register_assets() {
    wp_register_style(  'wanswers-style',  WANSWERS_URL . 'assets/css/wanswers.css',  array(), WANSWERS_VERSION );
    wp_register_script( 'wanswers-script', WANSWERS_URL . 'assets/js/wanswers.js', array(), WANSWERS_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );

    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wanswers_qa' ) ) {
        wp_enqueue_style( 'wanswers-style' );
        wp_enqueue_script( 'wanswers-script' );
        wp_localize_script( 'wanswers-script', 'WANSWERS', wanswers_js_config( get_permalink() ) );
    }
}

/**
 * Shared JS config object — used by wanswers.php (shortcode), single template, and archive template.
 *
 * @param string $login_redirect URL to redirect to after login. Defaults to current permalink.
 */
function wanswers_js_config( $login_redirect = '' ) {
    if ( ! $login_redirect ) {
        $login_redirect = get_permalink();
    }
    return array(
        'ajax_url'  => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'wanswers_nonce' ),
        'logged_in' => is_user_logged_in(),
        'login_url' => wp_login_url( $login_redirect ),
        'user_id'   => get_current_user_id(),
        'title_max' => (int) Wanswers_Admin::get( 'wanswers_question_title_max' ),
        'strings'   => array(
            'vote_thanks'     => __( 'Vote recorded!', 'wanswers-seo-first-qa' ),
            'already_voted'   => __( 'You already voted on this.', 'wanswers-seo-first-qa' ),
            'login_to_vote'   => __( 'Please log in to vote.', 'wanswers-seo-first-qa' ),
            'login_to_ask'    => __( 'Please log in to ask a question.', 'wanswers-seo-first-qa' ),
            'login_to_answer' => __( 'Please log in to answer.', 'wanswers-seo-first-qa' ),
            'confirm_delete'  => __( 'Are you sure you want to delete this?', 'wanswers-seo-first-qa' ),
            'submitting'      => __( 'Submitting…', 'wanswers-seo-first-qa' ),
            'error'           => __( 'Something went wrong. Please try again.', 'wanswers-seo-first-qa' ),
        ),
    );
}
