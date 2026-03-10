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
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'output_custom_css' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_filter( 'manage_cc_question_posts_columns',       array( __CLASS__, 'question_columns' ) );
        add_action( 'manage_cc_question_posts_custom_column', array( __CLASS__, 'question_column_data' ), 10, 2 );
    }

    /** Enqueue admin CSS only on the plugin settings page. */
    public static function enqueue_admin_assets( $hook ) {
        if ( 'cc_question_page_cc-qa-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'cc-qa-admin', CC_QA_URL . 'assets/css/admin.css', array(), CC_QA_VERSION );
    }

    /** Output admin-supplied custom CSS on the front-end via wp_add_inline_style. */
    public static function output_custom_css() {
        $css = trim( self::get( 'cc_qa_custom_css' ) );
        if ( $css && wp_style_is( 'cc-qa-style', 'enqueued' ) ) {
            wp_add_inline_style( 'cc-qa-style', wp_strip_all_tags( $css ) );
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
              <a href="https://wbuild.dev/questions/" target="_blank" rel="noopener" class="wa-header-link">Live Demo ↗</a>
              <a href="https://wbuild.dev/wanswers/" target="_blank" rel="noopener" class="wa-header-link">Docs ↗</a>
              <span class="wa-badge">v<?php echo esc_html( CC_QA_VERSION ); ?></span>
            </div>
          </div>

          <!-- ══ QUICK LINKS ══ -->
          <div id="wanswers-quicklinks">
            <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener" class="wa-quicklink">
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
            <a href="https://github.com/wbuilddev/wanswers" target="_blank" rel="noopener" class="wa-quicklink">
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
                          <span>Your Q&amp;A feed is live at <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/' ) ); ?> ↗</a></span>
                        <?php else : ?>
                          <span>Q&amp;A feed is at <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener">/questions/ ↗</a>. Enable to serve it at <code>/</code> instead.</span>
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
                  <div class="wa-section-desc">Heading, subtitle and SEO metadata for your <a href="<?php echo esc_url( $archive_url ); ?>" target="_blank" rel="noopener">/questions/ page ↗</a>, no template editing needed</div>
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below per action
        $action = isset( $_POST['cc_qa_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_qa_action'] ) ) : '';
        if ( empty( $action ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( 'send_digest_now' === $action ) {
            check_admin_referer( 'cc_qa_digest_actions', 'cc_qa_digest_nonce' );
            CC_QA_Digest::send();
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Weekly digest sent to all subscribers.', 'wanswers' ) . '</p></div>';
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below via check_admin_referer
        $action = isset( $_POST['cc_qa_action'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_qa_action'] ) ) : '';
        if ( 'reset_leaderboard' !== $action ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'cc_qa_reset_leaderboard', 'cc_qa_reset_nonce' );
        CC_QA_Leaderboard::reset_stats();
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Leaderboard has been reset. Scores now count from today. Lifetime vote counts are unchanged.', 'wanswers' ) . '</p></div>';
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
