<?php
/**
 * Question Archive Template
 *
 * Loaded for /questions/ — the browse/list view.
 * Also served at / when Homepage Mode is enabled in Q&A Settings.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// When running as the front page, the canonical and login-redirect URL is /
// rather than the CPT archive URL.
$_qa_is_homepage = (bool) Wanswers_Admin::get( 'wanswers_homepage_mode' ) && is_front_page();
$_qa_page_url    = $_qa_is_homepage ? home_url( '/' ) : get_post_type_archive_link( 'wanswers_question' );

wp_enqueue_style(  'wanswers-style',  WANSWERS_URL . 'assets/css/wanswers.css',  array(), WANSWERS_VERSION );
wp_enqueue_script( 'wanswers-script', WANSWERS_URL . 'assets/js/wanswers.js', array(), WANSWERS_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
wp_localize_script( 'wanswers-script', 'WANSWERS', wanswers_js_config( $_qa_page_url ) );

// SEO title: use admin override if set, else heading + site name
$_qa_seo_title   = Wanswers_Admin::get( 'wanswers_archive_seo_title' );
$_qa_heading     = Wanswers_Admin::get( 'wanswers_archive_title' ) ?: 'Community Q&A';
$_qa_meta_desc   = Wanswers_Admin::get( 'wanswers_archive_meta_desc' )
                ?: Wanswers_Admin::get( 'wanswers_archive_subtitle' )
                ?: 'Ask questions and get real answers from the community.';

add_filter( 'pre_get_document_title', function() use ( $_qa_seo_title, $_qa_heading ) {
    $title = $_qa_seo_title ?: ( $_qa_heading . ' — ' . get_bloginfo( 'name' ) );
    return $title;
} );

add_action( 'wp_head', function() use ( $_qa_meta_desc, $_qa_page_url ) {
    echo '<meta name="description" content="' . esc_attr( $_qa_meta_desc ) . '">' . "\n";
    // Explicit canonical so there's no ambiguity whether running at / or /questions/
    echo '<link rel="canonical" href="' . esc_url( $_qa_page_url ) . '">' . "\n";
}, 1 );

get_header();

// Render the list view directly — output is escaped within render_list_view()

echo wp_kses_post( Wanswers_Shortcode::render_list_view() );

get_footer();
