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
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is trusted core data
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wanswers_votes`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is trusted core data
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wanswers_subscriptions`" );

// Delete all plugin options
$options = array(
    'wanswers_page_id',
    'wanswers_questions_per_page',
    'wanswers_answers_per_page',
    'wanswers_answers_on_single',
    'wanswers_min_question_length',
    'wanswers_min_answer_length',
    'wanswers_question_title_max',
    'wanswers_email_max_recipients',
    'wanswers_notify_new_questions',
    'wanswers_notify_new_answers',
    'wanswers_moderate_questions',
    'wanswers_rate_limit_questions',
    'wanswers_rate_limit_answers',
    'wanswers_rate_limit_votes',
    'wanswers_rate_limit_window',
    'wanswers_archive_title',
    'wanswers_archive_subtitle',
    'wanswers_archive_meta_desc',
    'wanswers_archive_seo_title',
    'wanswers_leaderboard_position',
    'wanswers_noindex_shortcode',
    'wanswers_digest_enabled',
    'wanswers_digest_day',
    'wanswers_leaderboard_limit',
    'wanswers_sidebar_sticky',
    'wanswers_homepage_mode',
    'wanswers_footer_credit',
    'wanswers_db_version',
    'wanswers_leaderboard_reset_date',
);
foreach ( $options as $option ) {
    delete_option( $option );
}
// Migration flag
delete_option( 'wanswers_migrated_from_cc_qa' );
// Legacy tables (in case migration ran but old tables lingered)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is trusted core data
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cc_qa_votes`" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is trusted core data
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cc_qa_subscriptions`" );

// Clear scheduled cron events
wp_clear_scheduled_hook( 'wanswers_weekly_digest' );

// Flush rewrite rules
flush_rewrite_rules();
