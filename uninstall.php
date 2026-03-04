<?php
/**
 * wAnswers — Uninstall
 *
 * Runs when the plugin is deleted via WP Admin → Plugins → Delete.
 * Removes all plugin data: custom tables, options, scheduled events.
 *
 * @package wanswers
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cc_qa_votes`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cc_qa_subscriptions`" );

// Delete all plugin options
$options = array(
    'cc_qa_page_id',
    'cc_qa_questions_per_page',
    'cc_qa_answers_per_page',
    'cc_qa_answers_on_single',
    'cc_qa_min_question_length',
    'cc_qa_min_answer_length',
    'cc_qa_question_title_max',
    'cc_qa_email_max_recipients',
    'cc_qa_notify_new_questions',
    'cc_qa_notify_new_answers',
    'cc_qa_moderate_questions',
    'cc_qa_rate_limit_questions',
    'cc_qa_rate_limit_answers',
    'cc_qa_rate_limit_votes',
    'cc_qa_rate_limit_window',
    'cc_qa_archive_title',
    'cc_qa_archive_subtitle',
    'cc_qa_archive_meta_desc',
    'cc_qa_archive_seo_title',
    'cc_qa_leaderboard_position',
    'cc_qa_noindex_shortcode',
    'cc_qa_digest_enabled',
    'cc_qa_digest_day',
    'cc_qa_leaderboard_limit',
    'cc_qa_sidebar_sticky',
    'cc_qa_custom_css',
    'cc_qa_homepage_mode',
    'cc_qa_footer_credit',
    'cc_qa_db_version',
    'cc_qa_leaderboard_reset_date',
);
foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear scheduled cron events
wp_clear_scheduled_hook( 'cc_qa_weekly_digest' );

// Flush rewrite rules
flush_rewrite_rules();
