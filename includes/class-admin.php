<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'CC_QA_Admin' ) ) return;

class CC_QA_Admin {

    public static function init() {
        add_action( 'admin_menu',    array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_init',    array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_init',    array( __CLASS__, 'handle_reset_leaderboard' ) );
        add_action( 'admin_init',    array( __CLASS__, 'handle_digest_actions' ) );
        add_action( 'updated_option', array( __CLASS__, 'on_option_saved' ), 10, 3 );
        add_action( 'wp_head',       array( __CLASS__, 'output_custom_css' ) );
        add_filter( 'manage_cc_question_posts_columns',       array( __CLASS__, 'question_columns' ) );
        add_action( 'manage_cc_question_posts_custom_column', array( __CLASS__, 'question_column_data' ), 10, 2 );
    }

    /** Output admin-supplied custom CSS on the front-end. */
    public static function output_custom_css() {
        $css = trim( self::get( 'cc_qa_custom_css' ) );
        if ( $css ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS sanitized with wp_strip_all_tags; safe for style block
            echo '<style id="cc-qa-custom-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n";
        }
    }

    /* ── Defaults ── */
    public static function defaults() {
        return array(
            'cc_qa_page_id'               => 0,
            'cc_qa_questions_per_page'    => 10,
            'cc_qa_answers_per_page'      => 5,
            'cc_qa_answers_on_single'     => 50,
            'cc_qa_min_question_length'   => 10,
            'cc_qa_min_answer_length'     => 20,
            'cc_qa_question_title_max'    => 200,
            'cc_qa_email_max_recipients'  => 500,
            'cc_qa_notify_new_questions'  => 1,
            'cc_qa_notify_new_answers'    => 1,
            'cc_qa_moderate_questions'    => 0,
            // Rate limiting
            'cc_qa_rate_limit_questions'  => 3,
            'cc_qa_rate_limit_answers'    => 3,
            'cc_qa_rate_limit_votes'      => 3,
            'cc_qa_rate_limit_window'     => 10,
            // Archive page content
            'cc_qa_archive_title'         => 'Community Q&A',
            'cc_qa_archive_subtitle'      => 'Ask questions and get answers from the community.',
            'cc_qa_archive_meta_desc'     => '',
            'cc_qa_archive_seo_title'     => '',
            // Leaderboard layout on archive / shortcode pages
            'cc_qa_leaderboard_position'  => 'none',
            // Noindex shortcode pages to avoid duplicate content
            'cc_qa_noindex_shortcode'     => 1,
            // Disable built-in schema (for sites using RankMath, Yoast, etc.)
            'cc_qa_disable_schema'        => 0,
            // Weekly digest
            'cc_qa_digest_enabled'        => 0,
            'cc_qa_digest_day'            => 'monday',
            // Leaderboard display
            'cc_qa_leaderboard_limit'     => 10,
            'cc_qa_sidebar_sticky'        => 1,
            // Custom CSS
            'cc_qa_custom_css'            => '',
            // Homepage mode
            'cc_qa_homepage_mode'         => 0,
            // Footer credit
            'cc_qa_footer_credit'         => 0,
        );
    }

    /* ── Helper: get option with default ── */
    public static function get( $key ) {
        $defaults = self::defaults();
        return get_option( $key, $defaults[ $key ] ?? '' );
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=cc_question',
            'Q&A Settings',
            'Settings',
            'manage_options',
            'cc-qa-settings',
            array( __CLASS__, 'settings_page' )
        );
    }

    public static function register_settings() {
        foreach ( array_keys( self::defaults() ) as $key ) {
            register_setting( 'cc_qa_settings', $key, array(
                'sanitize_callback' => array( __CLASS__, 'sanitize_' . $key ),
            ) );
        }
    }

    /* ── Sanitizers ── */
    public static function sanitize_cc_qa_page_id( $v )              { return absint( $v ); }
    public static function sanitize_cc_qa_questions_per_page( $v )   { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_answers_per_page( $v )     { return max( 1, min( 20, absint( $v ) ) ); }
    public static function sanitize_cc_qa_answers_on_single( $v )    { return max( 5, min( 200, absint( $v ) ) ); }
    public static function sanitize_cc_qa_min_question_length( $v )  { return max( 5, min( 100, absint( $v ) ) ); }
    public static function sanitize_cc_qa_min_answer_length( $v )    { return max( 5, min( 500, absint( $v ) ) ); }
    public static function sanitize_cc_qa_question_title_max( $v )   { return max( 50, min( 500, absint( $v ) ) ); }
    public static function sanitize_cc_qa_email_max_recipients( $v ) { return max( 10, min( 5000, absint( $v ) ) ); }
    public static function sanitize_cc_qa_notify_new_questions( $v ) { return (int) (bool) $v; }
    public static function sanitize_cc_qa_notify_new_answers( $v )   { return (int) (bool) $v; }
    public static function sanitize_cc_qa_moderate_questions( $v )   { return (int) (bool) $v; }
    public static function sanitize_cc_qa_rate_limit_questions( $v ) { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_answers( $v )   { return max( 1, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_votes( $v )     { return max( 1, min( 100, absint( $v ) ) ); }
    public static function sanitize_cc_qa_rate_limit_window( $v )    { return max( 1, min( 60, absint( $v ) ) ); }
    public static function sanitize_cc_qa_archive_title( $v )        { return sanitize_text_field( $v ); }
    public static function sanitize_cc_qa_archive_subtitle( $v )     { return sanitize_textarea_field( $v ); }
    public static function sanitize_cc_qa_archive_meta_desc( $v )    { return sanitize_textarea_field( $v ); }
    public static function sanitize_cc_qa_archive_seo_title( $v )    { return sanitize_text_field( $v ); }
    public static function sanitize_cc_qa_leaderboard_position( $v ) {
        return in_array( $v, array( 'none', 'above', 'below', 'sidebar-right', 'sidebar-left' ), true ) ? $v : 'none';
    }
    public static function sanitize_cc_qa_noindex_shortcode( $v )    { return (int) (bool) $v; }
    public static function sanitize_cc_qa_disable_schema( $v )       { return (int) (bool) $v; }
    public static function sanitize_cc_qa_digest_enabled( $v )       { return (int) (bool) $v; }
    public static function sanitize_cc_qa_digest_day( $v ) {
        $days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
        return in_array( $v, $days, true ) ? $v : 'monday';
    }
    public static function sanitize_cc_qa_leaderboard_limit( $v ) { return max( 3, min( 50, absint( $v ) ) ); }
    public static function sanitize_cc_qa_sidebar_sticky( $v )    { return (int) (bool) $v; }
    public static function sanitize_cc_qa_custom_css( $v )        { return wp_strip_all_tags( $v ); }
    public static function sanitize_cc_qa_homepage_mode( $v )     { return (int) (bool) $v; }
    public static function sanitize_cc_qa_footer_credit( $v )     { return (int) (bool) $v; }

    public static function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <style>
        /* ════════════════════════════════════════════════════════════
           wAnswers Settings Page - Modern SaaS Design
        ════════════════════════════════════════════════════════════ */
        :root {
            --wa-orange:      #ff5020;
            --wa-orange-dark: #e04018;
            --wa-dark:        #111110;
            --wa-dark2:       #1e1d1b;
            --wa-text:        #1a1917;
            --wa-text2:       #4b4845;
            --wa-text3:       #8a8784;
            --wa-border:      #e4e2de;
            --wa-border2:     #f0ede9;
            --wa-bg:          #f7f5f2;
            --wa-white:       #ffffff;
            --wa-radius:      10px;
            --wa-radius-sm:   6px;
        }

        /* ── Page wrapper ── */
        #wanswers-settings { max-width: 960px; padding-bottom: 60px; }
        #wanswers-settings .wrap { margin: 0; }

        /* ── Hero header bar ── */
        #wanswers-header {
            display: flex; align-items: center; justify-content: space-between;
            background: var(--wa-dark);
            border-radius: var(--wa-radius);
            padding: 20px 28px;
            margin-bottom: 28px;
            box-shadow: 0 4px 24px rgba(0,0,0,.18);
        }
        #wanswers-header .wanswers-logo {
            display: flex; align-items: center; gap: 12px;
        }
        #wanswers-header .wanswers-logo .wa-icon {
            width: 38px; height: 38px; border-radius: 9px;
            background: var(--wa-orange);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        #wanswers-header .wanswers-logo .wa-icon svg { display: block; }
        #wanswers-header .wanswers-logo .wa-wordmark {
            font-size: 20px; font-weight: 800; color: #fff;
            letter-spacing: -0.04em; line-height: 1;
        }
        #wanswers-header .wanswers-logo .wa-wordmark span { color: var(--wa-orange); }
        #wanswers-header .wanswers-logo .wa-tagline {
            font-size: 12px; color: rgba(255,255,255,.4);
            font-weight: 400; margin-top: 3px; letter-spacing: 0;
        }
        #wanswers-header .wanswers-header-right {
            display: flex; align-items: center; gap: 10px;
        }
        #wanswers-header .wa-badge {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.5);
            font-size: 11px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; padding: 5px 10px; border-radius: 20px;
        }
        #wanswers-header .wa-header-link {
            color: rgba(255,255,255,.5); font-size: 12px; font-weight: 500;
            text-decoration: none; padding: 6px 12px; border-radius: 6px;
            border: 1px solid rgba(255,255,255,.12);
            transition: color .15s, border-color .15s, background .15s;
        }
        #wanswers-header .wa-header-link:hover {
            color: #fff; border-color: rgba(255,255,255,.25);
            background: rgba(255,255,255,.06);
        }

        /* ── Quick-links strip ── */
        #wanswers-quicklinks {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px;
        }
        .wa-quicklink {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--wa-white); border: 1px solid var(--wa-border);
            border-radius: var(--wa-radius-sm); padding: 7px 14px;
            font-size: 12px; font-weight: 600; color: var(--wa-text2);
            text-decoration: none; transition: border-color .15s, color .15s, box-shadow .15s;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .wa-quicklink:hover { border-color: var(--wa-orange); color: var(--wa-orange); box-shadow: 0 2px 8px rgba(255,80,32,.12); }
        .wa-quicklink .wa-ql-icon { font-size: 14px; line-height: 1; }

        /* ── Section cards ── */
        .wa-section {
            background: var(--wa-white);
            border: 1px solid var(--wa-border);
            border-radius: var(--wa-radius);
            margin-bottom: 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            overflow: hidden;
        }
        .wa-section-head {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--wa-border2);
            background: var(--wa-bg);
        }
        .wa-section-head .wa-section-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--wa-white); border: 1px solid var(--wa-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .wa-section-head .wa-section-title {
            font-size: 13px; font-weight: 700; color: var(--wa-text); margin: 0;
        }
        .wa-section-head .wa-section-desc {
            font-size: 12px; color: var(--wa-text3); margin: 2px 0 0; font-weight: 400;
        }
        .wa-section-body { padding: 0; }

        /* ── Rows inside sections ── */
        .wa-row {
            display: grid; grid-template-columns: 220px 1fr;
            align-items: start; gap: 0;
            border-bottom: 1px solid var(--wa-border2);
        }
        .wa-row:last-child { border-bottom: none; }
        .wa-row-label {
            padding: 16px 22px; font-size: 13px; font-weight: 600;
            color: var(--wa-text); line-height: 1.4;
        }
        .wa-row-label .wa-row-hint {
            display: block; font-size: 11px; font-weight: 400;
            color: var(--wa-text3); margin-top: 3px; line-height: 1.4;
        }
        .wa-row-control { padding: 14px 22px; }
        .wa-row-control .description {
            font-size: 12px; color: var(--wa-text3); margin-top: 6px; line-height: 1.5;
        }
        .wa-row-control code {
            background: var(--wa-bg); border: 1px solid var(--wa-border);
            padding: 1px 5px; border-radius: 3px; font-size: 11px; color: var(--wa-text2);
        }

        /* ── Toggle / checkbox rows ── */
        .wa-toggle-row {
            display: flex; align-items: flex-start; gap: 12px;
        }
        .wa-toggle {
            position: relative; display: inline-flex;
            width: 36px; height: 20px; flex-shrink: 0; margin-top: 1px;
        }
        .wa-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
        .wa-toggle-track {
            position: absolute; inset: 0; border-radius: 20px;
            background: #d1cec9; cursor: pointer;
            transition: background .2s;
        }
        .wa-toggle-track::after {
            content: ''; position: absolute; left: 3px; top: 3px;
            width: 14px; height: 14px; border-radius: 50%;
            background: #fff; transition: transform .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .wa-toggle input:checked + .wa-toggle-track { background: var(--wa-orange); }
        .wa-toggle input:checked + .wa-toggle-track::after { transform: translateX(16px); }
        .wa-toggle-body { flex: 1; }
        .wa-toggle-body strong { font-size: 13px; font-weight: 600; color: var(--wa-text); display: block; }
        .wa-toggle-body span { font-size: 12px; color: var(--wa-text3); margin-top: 2px; display: block; line-height: 1.5; }

        /* ── Status badge in toggle rows ── */
        .wa-status {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .wa-status-on  { background: #dcfce7; color: #166534; }
        .wa-status-off { background: #f1f5f9; color: #64748b; }
        .wa-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* ── Inputs ── */
        #wanswers-settings input[type="text"],
        #wanswers-settings input[type="number"],
        #wanswers-settings select,
        #wanswers-settings textarea {
            border: 1px solid var(--wa-border) !important;
            border-radius: var(--wa-radius-sm) !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.04) inset !important;
            font-size: 13px !important;
            color: var(--wa-text) !important;
            background: var(--wa-white) !important;
            transition: border-color .15s, box-shadow .15s !important;
            padding: 7px 10px !important;
        }
        #wanswers-settings input[type="text"]:focus,
        #wanswers-settings input[type="number"]:focus,
        #wanswers-settings select:focus,
        #wanswers-settings textarea:focus {
            border-color: var(--wa-orange) !important;
            box-shadow: 0 0 0 3px rgba(255,80,32,.12) !important;
            outline: none !important;
        }
        #wanswers-settings input[type="number"] { width: 80px !important; }
        #wanswers-settings select { padding-right: 28px !important; }
        #wanswers-settings textarea.wa-css-editor {
            font-family: 'SFMono-Regular', Consolas, monospace !important;
            font-size: 12px !important; line-height: 1.6 !important;
            resize: vertical !important;
        }

        /* ── Notice inline ── */
        .wa-notice {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 14px; border-radius: var(--wa-radius-sm);
            font-size: 12px; font-weight: 500; margin-top: 10px;
        }
        .wa-notice-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .wa-notice-info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .wa-notice a { color: inherit; font-weight: 700; }

        /* ── Number input with unit label ── */
        .wa-number-group { display: flex; align-items: center; gap: 8px; }
        .wa-number-group .wa-unit { font-size: 12px; color: var(--wa-text3); font-weight: 500; }

        /* ── Save bar ── */
        #wanswers-save-bar {
            background: var(--wa-white); border: 1px solid var(--wa-border);
            border-radius: var(--wa-radius); padding: 16px 22px;
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        #wanswers-save-bar p { margin: 0; font-size: 13px; color: var(--wa-text3); }
        #wanswers-save-bar .wa-save-btn {
            background: var(--wa-orange) !important; border: none !important;
            color: #fff !important; font-size: 13px !important; font-weight: 700 !important;
            padding: 9px 24px !important; border-radius: var(--wa-radius-sm) !important;
            height: auto !important; cursor: pointer !important;
            box-shadow: 0 2px 8px rgba(255,80,32,.35) !important;
            transition: background .15s, box-shadow .15s, transform .1s !important;
            letter-spacing: -.01em !important;
        }
        #wanswers-save-bar .wa-save-btn:hover {
            background: var(--wa-orange-dark) !important;
            box-shadow: 0 4px 14px rgba(255,80,32,.45) !important;
            transform: translateY(-1px) !important;
        }
        #wanswers-save-bar .wa-save-btn:active { transform: translateY(0) !important; }

        /* ── Tool action cards (digest send, leaderboard reset) ── */
        .wa-tool-card {
            background: var(--wa-white); border: 1px solid var(--wa-border);
            border-radius: var(--wa-radius); padding: 20px 22px;
            margin-bottom: 16px; display: flex; align-items: center;
            justify-content: space-between; gap: 20px; flex-wrap: wrap;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        .wa-tool-card-body h3 {
            font-size: 13px; font-weight: 700; color: var(--wa-text); margin: 0 0 4px;
        }
        .wa-tool-card-body p {
            font-size: 12px; color: var(--wa-text3); margin: 0; line-height: 1.5;
        }
        .wa-tool-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: var(--wa-radius-sm);
            font-size: 12px; font-weight: 700; cursor: pointer; border: none;
            transition: background .15s, box-shadow .15s; white-space: nowrap;
            text-decoration: none;
        }
        .wa-tool-btn-secondary {
            background: var(--wa-bg); color: var(--wa-text2);
            border: 1px solid var(--wa-border) !important;
        }
        .wa-tool-btn-secondary:hover { background: var(--wa-border2); border-color: #ccc !important; color: var(--wa-text); }
        .wa-tool-btn-danger {
            background: #fef2f2; color: #b91c1c;
            border: 1px solid #fecaca !important;
        }
        .wa-tool-btn-danger:hover { background: #fee2e2; border-color: #fca5a5 !important; }

        /* ── Shortcode pills ── */
        .wa-shortcode-grid { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
        .wa-shortcode-pill {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 10px 14px; background: var(--wa-bg);
            border: 1px solid var(--wa-border); border-radius: var(--wa-radius-sm);
        }
        .wa-shortcode-pill code {
            font-family: 'SFMono-Regular', Consolas, monospace;
            font-size: 12px; color: var(--wa-orange); background: transparent !important;
            border: none !important; padding: 0 !important; font-weight: 700; white-space: nowrap;
        }
        .wa-shortcode-pill span { font-size: 12px; color: var(--wa-text3); line-height: 1.5; }

        /* ── Settings footer ── */
        #wanswers-footer {
            margin-top: 32px; padding: 18px 22px;
            background: var(--wa-dark); border-radius: var(--wa-radius);
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        #wanswers-footer .wa-footer-brand {
            display: flex; align-items: center; gap: 10px;
        }
        #wanswers-footer .wa-footer-brand .wa-icon-sm {
            width: 28px; height: 28px; border-radius: 7px;
            background: var(--wa-orange); display: flex; align-items: center; justify-content: center;
        }
        #wanswers-footer .wa-footer-brand .wa-icon-sm svg { display: block; }
        #wanswers-footer .wa-footer-wordmark {
            font-size: 14px; font-weight: 800; color: #fff; letter-spacing: -.03em;
        }
        #wanswers-footer .wa-footer-wordmark span { color: var(--wa-orange); }
        #wanswers-footer .wa-footer-links {
            display: flex; align-items: center; gap: 6px;
        }
        #wanswers-footer .wa-footer-links a {
            color: rgba(255,255,255,.4); font-size: 12px; text-decoration: none; font-weight: 500;
            padding: 4px 8px; border-radius: 4px; transition: color .15s;
        }
        #wanswers-footer .wa-footer-links a:hover { color: rgba(255,255,255,.8); }
        #wanswers-footer .wa-footer-links .wa-dot { color: rgba(255,255,255,.15); font-size: 10px; }
        #wanswers-footer .wa-version-pill {
            background: rgba(255,255,255,.07); color: rgba(255,255,255,.35);
            font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px;
        }

        /* ── WP notices inside our wrap ── */
        #wanswers-settings .notice { border-radius: var(--wa-radius-sm); margin: 0 0 16px; }

        /* ── Digest meta info ── */
        .wa-digest-meta { font-size: 11px; color: var(--wa-text3); margin-top: 6px; }
        .wa-digest-meta strong { color: var(--wa-text2); }
        </style>

        <?php
        // Pull all saved values upfront for cleanliness
        $homepage_mode   = self::get( 'cc_qa_homepage_mode' );
        $lb_position     = self::get( 'cc_qa_leaderboard_position' );
        $digest_enabled  = self::get( 'cc_qa_digest_enabled' );
        $digest_day      = self::get( 'cc_qa_digest_day' );
        $next_ts         = wp_next_scheduled( 'cc_qa_weekly_digest' );
        $last_digest     = get_option( 'cc_qa_digest_last_sent', '' );
        $reset_date      = get_option( 'cc_qa_leaderboard_reset_date', '' );
        $archive_url     = get_post_type_archive_link( 'cc_question' );
        $lb_positions    = array(
            'none'          => 'Hidden - don\'t show leaderboard',
            'above'         => 'Above the Q&A feed',
            'below'         => 'Below the Q&A feed',
            'sidebar-right' => 'Sidebar - right of feed',
            'sidebar-left'  => 'Sidebar - left of feed',
        );
        $days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
        ?>

        <div id="wanswers-settings">
          <?php settings_errors( 'cc_qa_settings' ); ?>

          <!-- ══ HEADER ══ -->
          <div id="wanswers-header">
            <div class="wanswers-logo">
              <div class="wa-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M5 3l3.5 13L12 7l3.5 9L19 3" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M3 20h18" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".4"/>
                </svg>
              </div>
              <div>
                <div class="wa-wordmark"><span>w</span>Answers</div>
                <div class="wa-tagline">The SEO-First Q&amp;A Plugin for WordPress</div>
              </div>
            </div>
            <div class="wanswers-header-right">
              <a href="https://wbuild.dev/questions/" target="_blank" class="wa-header-link">Live Demo ↗</a>
              <a href="https://wbuild.dev/wanswers/" target="_blank" class="wa-header-link">Docs ↗</a>
              <span class="wa-badge">v<?php echo esc_html( CC_QA_VERSION ); ?></span>
            </div>
          </div>

          <!-- ══ QUICK LINKS ══ -->
          <div id="wanswers-quicklinks">
            <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" class="wa-quicklink">
              <span class="wa-ql-icon">🌐</span> View Q&amp;A Forum
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cc_question' ) ); ?>" class="wa-quicklink">
              <span class="wa-ql-icon">❓</span> Manage Questions
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=cc_question_topic&post_type=cc_question' ) ); ?>" class="wa-quicklink">
              <span class="wa-ql-icon">🏷️</span> Manage Topics
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cc_answer' ) ); ?>" class="wa-quicklink">
              <span class="wa-ql-icon">💬</span> Manage Answers
            </a>
            <a href="https://github.com/wbuilddev/wanswers" target="_blank" class="wa-quicklink">
              <span class="wa-ql-icon">⭐</span> GitHub
            </a>
          </div>

          <form method="post" action="options.php">
            <?php settings_fields( 'cc_qa_settings' ); ?>

            <!-- ══ SAVE BAR ══ -->
            <div id="wanswers-save-bar">
              <p>Configure your Q&amp;A forum settings below. Changes apply immediately on save.</p>
              <button type="submit" class="wa-save-btn">Save Settings</button>
            </div>

            <!-- ══════════════════════════════════════
                 1. HOMEPAGE MODE
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🏠</div>
                <div>
                  <div class="wa-section-title">Homepage Mode</div>
                  <div class="wa-section-desc">Serve your Q&amp;A feed directly at your site root, no page or shortcode needed</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">
                    Use Q&amp;A as homepage
                    <span class="wa-row-hint">Serves feed at <code>/</code> with 301 from <code>/questions/</code></span>
                  </div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_homepage_mode" value="1" <?php checked( $homepage_mode ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>
                          <?php if ( $homepage_mode ) : ?>
                            <span class="wa-status wa-status-on">Active</span>
                          <?php else : ?>
                            <span class="wa-status wa-status-off">Off</span>
                          <?php endif; ?>
                        </strong>
                        <?php if ( $homepage_mode ) : ?>
                          <span>Your Q&amp;A feed is live at <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/' ) ); ?> ↗</a></span>
                        <?php else : ?>
                          <span>Q&amp;A feed is at <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank">/questions/ ↗</a>. Enable to serve it at <code>/</code> instead.</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <p class="description" style="margin-top:10px;">
                      <strong>Requirement:</strong> WordPress → Settings → Reading → must be set to "Your latest posts" (not a static page).<br>
                      <strong>SEO:</strong> Canonical becomes <code><?php echo esc_html( home_url( '/' ) ); ?></code>. The <code>/questions/</code> archive 301-redirects to <code>/</code>. Individual question pages at <code>/questions/slug/</code> are unaffected.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 2. ARCHIVE PAGE CONTENT
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">📄</div>
                <div>
                  <div class="wa-section-title">Archive Page Content</div>
                  <div class="wa-section-desc">Heading, subtitle and SEO metadata for your <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank">/questions/ page ↗</a>, no template editing needed</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Page heading (H1)</div>
                  <div class="wa-row-control">
                    <input type="text" name="cc_qa_archive_title" class="large-text"
                           value="<?php echo esc_attr( self::get( 'cc_qa_archive_title' ) ); ?>"
                           placeholder="Community Q&amp;A" />
                    <p class="description">The main H1 shown at the top of /questions/.</p>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Subtitle
                    <span class="wa-row-hint">Visible to users and crawlers</span>
                  </div>
                  <div class="wa-row-control">
                    <textarea name="cc_qa_archive_subtitle" class="large-text" rows="2"
                              placeholder="Ask questions and get answers from the community."><?php echo esc_textarea( self::get( 'cc_qa_archive_subtitle' ) ); ?></textarea>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    SEO title override
                    <span class="wa-row-hint">Overrides &lt;title&gt; tag</span>
                  </div>
                  <div class="wa-row-control">
                    <input type="text" name="cc_qa_archive_seo_title" class="large-text"
                           value="<?php echo esc_attr( self::get( 'cc_qa_archive_seo_title' ) ); ?>"
                           placeholder="<?php echo esc_attr( ( self::get( 'cc_qa_archive_title' ) ?: 'Community Q&A' ) . ' — ' . get_bloginfo( 'name' ) ); ?>" />
                    <p class="description">Leave blank to use heading + site name. Yoast / RankMath will override if configured there.</p>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Meta description
                    <span class="wa-row-hint">Keep under 160 characters</span>
                  </div>
                  <div class="wa-row-control">
                    <textarea name="cc_qa_archive_meta_desc" class="large-text" rows="2"
                              placeholder="Ask questions and get answers from the community."><?php echo esc_textarea( self::get( 'cc_qa_archive_meta_desc' ) ); ?></textarea>
                    <p class="description">Leave blank to use the subtitle. Yoast / RankMath will override if configured there.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 3. LEADERBOARD
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🏆</div>
                <div>
                  <div class="wa-section-title">Leaderboard</div>
                  <div class="wa-section-desc">Position and display settings for the top contributors leaderboard</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">
                    Position
                    <span class="wa-row-hint">Where it appears on the feed page</span>
                  </div>
                  <div class="wa-row-control">
                    <select name="cc_qa_leaderboard_position">
                      <?php foreach ( $lb_positions as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $lb_position, $val ); ?>><?php echo esc_html( $label ); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <p class="description">Sidebar layouts: Q&amp;A takes ~65%, leaderboard ~35%. Stacks automatically below 900px.</p>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Max users shown
                    <span class="wa-row-hint">Per leaderboard tab (3–50)</span>
                  </div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_leaderboard_limit" min="3" max="50"
                             value="<?php echo esc_attr( self::get( 'cc_qa_leaderboard_limit' ) ); ?>" />
                      <span class="wa-unit">users</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Sticky sidebar</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_sidebar_sticky" value="1" <?php checked( self::get( 'cc_qa_sidebar_sticky' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Keep sidebar visible while scrolling</strong>
                        <span>Auto-disabled on screens narrower than 900px.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 4. SEO
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🔍</div>
                <div>
                  <div class="wa-section-title">SEO &amp; Schema</div>
                  <div class="wa-section-desc">Structured data output and duplicate content controls</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">
                    Noindex shortcode pages
                    <span class="wa-row-hint">Recommended if using both URLs</span>
                  </div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_noindex_shortcode" value="1" <?php checked( self::get( 'cc_qa_noindex_shortcode' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Add <code>noindex</code> to pages with <code>[cc_qa]</code> shortcode</strong>
                        <span>The page stays accessible to users but won't be indexed by Google. Yoast / RankMath noindex settings also work.</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Disable built-in schema
                    <span class="wa-row-hint">Use if your SEO plugin handles structured data</span>
                  </div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_disable_schema" value="1" <?php checked( self::get( 'cc_qa_disable_schema' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Turn off all JSON-LD, Open Graph, and structured data output</strong>
                        <span>Enable this if RankMath, Yoast, or another SEO plugin already generates schema for your site. The Q&A functionality is unaffected.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 5. PAGE & DISPLAY
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">⚙️</div>
                <div>
                  <div class="wa-section-title">Page &amp; Display</div>
                  <div class="wa-section-desc">Pagination, email page link, and content limits</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">
                    Q&amp;A page
                    <span class="wa-row-hint">For email notification links</span>
                  </div>
                  <div class="wa-row-control">
                    <?php wp_dropdown_pages( array( 'name' => 'cc_qa_page_id', 'show_option_none' => '— Select Page —', 'selected' => (int) self::get( 'cc_qa_page_id' ) ) ); ?>
                    <p class="description">Page with the <code>[cc_qa]</code> shortcode. Leave blank if using <code>/questions/</code> as your main URL.</p>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Questions per page</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_questions_per_page" min="1" max="50" value="<?php echo esc_attr( self::get( 'cc_qa_questions_per_page' ) ); ?>" />
                      <span class="wa-unit">before "Load more"&nbsp; (1–50)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Answers per load</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_answers_per_page" min="1" max="20" value="<?php echo esc_attr( self::get( 'cc_qa_answers_per_page' ) ); ?>" />
                      <span class="wa-unit">per batch on question page&nbsp; (1–20)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Max answers on question page</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_answers_on_single" min="5" max="200" value="<?php echo esc_attr( self::get( 'cc_qa_answers_on_single' ) ); ?>" />
                      <span class="wa-unit">max loaded at once&nbsp; (5–200)</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 6. CONTENT RULES
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">📝</div>
                <div>
                  <div class="wa-section-title">Content Rules</div>
                  <div class="wa-section-desc">Minimum lengths, title limits, and moderation</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Min question title length</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_min_question_length" min="5" max="100" value="<?php echo esc_attr( self::get( 'cc_qa_min_question_length' ) ); ?>" />
                      <span class="wa-unit">characters&nbsp; (5–100)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Min answer length</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_min_answer_length" min="5" max="500" value="<?php echo esc_attr( self::get( 'cc_qa_min_answer_length' ) ); ?>" />
                      <span class="wa-unit">characters&nbsp; (5–500)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Question title max length</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_question_title_max" min="50" max="500" value="<?php echo esc_attr( self::get( 'cc_qa_question_title_max' ) ); ?>" />
                      <span class="wa-unit">characters&nbsp; (50–500)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Moderate new questions</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_moderate_questions" value="1" <?php checked( self::get( 'cc_qa_moderate_questions' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Hold new questions for admin review before publishing</strong>
                        <span>Questions will have "Pending" status until approved.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 7. EMAIL NOTIFICATIONS
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">📧</div>
                <div>
                  <div class="wa-section-title">Email Notifications</div>
                  <div class="wa-section-desc">Instant alerts when questions are answered or replies posted</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Notify on new questions</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_notify_new_questions" value="1" <?php checked( self::get( 'cc_qa_notify_new_questions' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Email all registered members when a new question is posted</strong>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Notify on new answers</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_notify_new_answers" value="1" <?php checked( self::get( 'cc_qa_notify_new_answers' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Email question subscribers when a new answer or reply is posted</strong>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Max email recipients
                    <span class="wa-row-hint">Per new question notification</span>
                  </div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_email_max_recipients" min="10" max="5000" value="<?php echo esc_attr( self::get( 'cc_qa_email_max_recipients' ) ); ?>" />
                      <span class="wa-unit">users&nbsp; (10–5000)</span>
                    </div>
                    <p class="description">Use a transactional email provider (Postmark, SendGrid) for large lists.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 8. RATE LIMITING
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🛡️</div>
                <div>
                  <div class="wa-section-title">Rate Limiting</div>
                  <div class="wa-section-desc">Throttle submissions per user within a rolling time window. Admins and editors are never rate-limited.</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Time window</div>
                  <div class="wa-row-control">
                    <div class="wa-number-group">
                      <input type="number" name="cc_qa_rate_limit_window" min="1" max="60" value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_window' ) ); ?>" />
                      <span class="wa-unit">minutes rolling window&nbsp; (1–60)</span>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Max questions per window</div>
                  <div class="wa-row-control">
                    <input type="number" name="cc_qa_rate_limit_questions" min="1" max="50" value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_questions' ) ); ?>" />
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Max answers per window</div>
                  <div class="wa-row-control">
                    <input type="number" name="cc_qa_rate_limit_answers" min="1" max="50" value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_answers' ) ); ?>" />
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">Max votes per window</div>
                  <div class="wa-row-control">
                    <input type="number" name="cc_qa_rate_limit_votes" min="1" max="100" value="<?php echo esc_attr( self::get( 'cc_qa_rate_limit_votes' ) ); ?>" />
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 9. WEEKLY DIGEST
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">📰</div>
                <div>
                  <div class="wa-section-title">Weekly Community Digest</div>
                  <div class="wa-section-desc">Weekly email to subscribers with top questions and best answers from the past 7 days</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Enable digest</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_digest_enabled" value="1" <?php checked( $digest_enabled ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Send a weekly digest email to all Q&amp;A subscribers</strong>
                        <span>Only users subscribed to at least one question receive it.</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="wa-row">
                  <div class="wa-row-label">
                    Send on
                    <span class="wa-row-hint">Sent at 9:00 am site time</span>
                  </div>
                  <div class="wa-row-control">
                    <select name="cc_qa_digest_day">
                      <?php foreach ( $days as $d ) : ?>
                        <option value="<?php echo esc_attr( $d ); ?>" <?php selected( $digest_day, $d ); ?>><?php echo esc_html( ucfirst( $d ) ); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ( $next_ts ) : ?>
                      <div class="wa-digest-meta">Next send: <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_ts ) ); ?></strong></div>
                    <?php endif; ?>
                    <?php if ( $last_digest ) : ?>
                      <div class="wa-digest-meta">Last sent: <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_digest ) ) ); ?></strong></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 10. CUSTOM CSS
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🎨</div>
                <div>
                  <div class="wa-section-title">Custom CSS</div>
                  <div class="wa-section-desc">Override plugin styles without editing files, output in a <code>&lt;style&gt;</code> tag on every front-end page</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row" style="grid-template-columns: 1fr;">
                  <div class="wa-row-control" style="padding:16px 22px;">
                    <textarea name="cc_qa_custom_css" class="large-text wa-css-editor" rows="10"
                              placeholder="/* Example: change the primary accent colour */&#10;:root { --orange: #e63946; }"><?php echo esc_textarea( self::get( 'cc_qa_custom_css' ) ); ?></textarea>
                    <p class="description" style="margin-top:8px;">Plain CSS only, no <code>&lt;style&gt;</code> tags needed. HTML is stripped automatically. Use your browser's inspector to find class names.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══════════════════════════════════════
                 11. FOOTER CREDIT
            ══════════════════════════════════════ -->
            <div class="wa-section">
              <div class="wa-section-head">
                <div class="wa-section-icon">🔗</div>
                <div>
                  <div class="wa-section-title">Footer Credit</div>
                  <div class="wa-section-desc">Optional "Powered by wAnswers" link at the bottom of the forum, freely removable</div>
                </div>
              </div>
              <div class="wa-section-body">
                <div class="wa-row">
                  <div class="wa-row-label">Show "Powered by wAnswers"</div>
                  <div class="wa-row-control">
                    <div class="wa-toggle-row">
                      <label class="wa-toggle">
                        <input type="checkbox" name="cc_qa_footer_credit" value="1" <?php checked( self::get( 'cc_qa_footer_credit' ) ); ?> />
                        <span class="wa-toggle-track"></span>
                      </label>
                      <div class="wa-toggle-body">
                        <strong>Display a small credit link at the bottom of the Q&amp;A forum</strong>
                        <span>Appreciated but completely optional. Uncheck anytime.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ══ BOTTOM SAVE BAR ══ -->
            <div id="wanswers-save-bar" style="margin-top:8px;margin-bottom:0;">
              <p>All settings saved instantly, no cache to clear.</p>
              <button type="submit" class="wa-save-btn">Save Settings</button>
            </div>

          </form><!-- /form -->

          <!-- ══════════════════════════════════════
               TOOLS SECTION (outside main form)
          ══════════════════════════════════════ -->
          <div style="margin-top:28px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--wa-text3);margin-bottom:12px;">Tools &amp; Actions</div>

            <!-- Shortcodes -->
            <div class="wa-tool-card" style="flex-direction:column;align-items:flex-start;">
              <div class="wa-tool-card-body">
                <h3>Shortcodes</h3>
                <p>Use these shortcodes to embed the Q&amp;A forum on any page.</p>
              </div>
              <div class="wa-shortcode-grid" style="width:100%;">
                <div class="wa-shortcode-pill">
                  <code>[cc_qa]</code>
                  <span>Full Q&amp;A feed with ask form, filters, search, and pagination. Place on any page.</span>
                </div>
                <div class="wa-shortcode-pill">
                  <code>[cc_qa_leaderboard]</code>
                  <span>Standalone top contributors leaderboard. Works on any page independently.</span>
                </div>
                <div class="wa-shortcode-pill">
                  <code>[cc_qa_leaderboard limit="5"]</code>
                  <span>Show top 5 users per category tab (default is 10).</span>
                </div>
              </div>
            </div>

            <!-- Send Digest -->
            <div class="wa-tool-card">
              <div class="wa-tool-card-body">
                <h3>📬 Send Digest Now</h3>
                <p>Immediately send the weekly digest to all current subscribers, useful for testing or a one-off manual send.</p>
              </div>
              <form method="post">
                <?php wp_nonce_field( 'cc_qa_digest_actions', 'cc_qa_digest_nonce' ); ?>
                <input type="hidden" name="cc_qa_action" value="send_digest_now">
                <button type="submit" class="wa-tool-btn wa-tool-btn-secondary">Send Digest Now</button>
              </form>
            </div>

            <!-- Leaderboard Reset -->
            <div class="wa-tool-card">
              <div class="wa-tool-card-body">
                <h3>🔄 Reset Leaderboard</h3>
                <p>Scores restart from today. <strong>Lifetime upvote/downvote counts are never affected</strong>, only period scores reset.
                <?php if ( $reset_date ) : ?>
                  Last reset: <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $reset_date ) ) ); ?></strong>.
                <?php else : ?>
                  Never reset, currently showing all-time stats.
                <?php endif; ?></p>
              </div>
              <form method="post">
                <?php wp_nonce_field( 'cc_qa_reset_leaderboard', 'cc_qa_reset_nonce' ); ?>
                <input type="hidden" name="cc_qa_action" value="reset_leaderboard">
                <button type="submit" class="wa-tool-btn wa-tool-btn-danger"
                        onclick="return confirm('Reset the leaderboard? Scores will restart from today. Lifetime vote counts are preserved.');">
                  Reset Leaderboard
                </button>
              </form>
            </div>
          </div>

          <!-- ══ FOOTER ══ -->
          <div id="wanswers-footer">
            <div class="wa-footer-brand">
              <div class="wa-icon-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M5 3l3.5 13L12 7l3.5 9L19 3" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="wa-footer-wordmark"><span>w</span>Answers</div>
            </div>
            <div class="wa-footer-links">
              <a href="https://wbuild.dev/wanswers/" target="_blank" rel="noopener">Website</a>
              <span class="wa-dot">·</span>
              <a href="https://wbuild.dev/questions/" target="_blank" rel="noopener">Live Demo</a>
              <span class="wa-dot">·</span>
              <a href="https://github.com/wbuilddev/wanswers" target="_blank" rel="noopener">GitHub</a>
              <span class="wa-dot">·</span>
              <a href="https://wbuild.dev" target="_blank" rel="noopener">wBuild</a>
            </div>
            <span class="wa-version-pill">v<?php echo esc_html( CC_QA_VERSION ); ?></span>
          </div>

        </div><!-- /#wanswers-settings -->
        <?php
    }

    /**
     * Handle digest manual send from the settings page POST.
     */
    public static function handle_digest_actions() {
        if ( empty( $_POST['cc_qa_action'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( 'send_digest_now' === $_POST['cc_qa_action'] ) {
            check_admin_referer( 'cc_qa_digest_actions', 'cc_qa_digest_nonce' );
            CC_QA_Digest::send();
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Weekly digest sent to all subscribers.</p></div>';
            } );
        }
    }

    /**
     * When digest settings change, reschedule the cron event.
     */
    public static function on_option_saved( $option, $old, $new ) {
        if ( in_array( $option, array( 'cc_qa_digest_enabled', 'cc_qa_digest_day' ), true ) ) {
            CC_QA_Digest::reschedule();
        }
        // Rewrite rules must be flushed when homepage mode changes so the
        // /questions/ → / redirect takes effect immediately.
        if ( 'cc_qa_homepage_mode' === $option && $old !== $new ) {
            flush_rewrite_rules();
        }
    }

    public static function handle_reset_leaderboard() {
        if ( empty( $_POST['cc_qa_action'] ) || $_POST['cc_qa_action'] !== 'reset_leaderboard' ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'cc_qa_reset_leaderboard', 'cc_qa_reset_nonce' );
        CC_QA_Leaderboard::reset_stats();
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Leaderboard has been reset. Scores now count from today. Lifetime vote counts are unchanged.</p></div>';
        } );
    }

    public static function question_columns( $columns ) {
        return array(
            'cb'           => $columns['cb'],
            'title'        => 'Question',
            'author'       => 'Asked By',
            'qa_votes'     => 'Votes',
            'qa_answers'   => 'Answers',
            'qa_accepted'  => 'Accepted',
            'taxonomy-cc_question_topic' => 'Topic',
            'date'         => 'Date',
        );
    }

    public static function question_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'qa_votes':   echo (int) get_post_meta( $post_id, '_cc_qa_votes', true );        break;
            case 'qa_answers': echo (int) get_post_meta( $post_id, '_cc_qa_answer_count', true ); break;
            case 'qa_accepted': echo esc_html( get_post_meta( $post_id, '_cc_qa_accepted', true ) ? '✓' : '—' ); break;
        }
    }
}
