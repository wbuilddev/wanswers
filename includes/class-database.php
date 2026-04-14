<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( class_exists( 'Wanswers_Database' ) ) return;

class Wanswers_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $votes_table = $wpdb->prefix . 'wanswers_votes';
        $sql_votes   = "CREATE TABLE IF NOT EXISTS {$votes_table} (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id    BIGINT(20) UNSIGNED NOT NULL,
            user_id    BIGINT(20) UNSIGNED NOT NULL,
            vote       TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_post (user_id, post_id),
            KEY post_id (post_id)
        ) {$charset};";

        $subs_table = $wpdb->prefix . 'wanswers_subscriptions';
        $sql_subs   = "CREATE TABLE IF NOT EXISTS {$subs_table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            user_id     BIGINT(20) UNSIGNED NOT NULL,
            email       VARCHAR(200) NOT NULL,
            token       VARCHAR(64)  NOT NULL DEFAULT '',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_question (user_id, question_id),
            KEY question_id (question_id),
            KEY token (token)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_votes );
        dbDelta( $sql_subs );

        add_option( 'wanswers_db_version', WANSWERS_VERSION );
    }

    public static function deactivate() {}

    /**
     * Migrate data from old cc_qa_ prefixes to wanswers_ prefixes.
     * Runs once on activation if old data exists. Safe to run multiple times.
     */
    public static function migrate_from_cc_qa() {
        global $wpdb;

        // Skip if already migrated
        if ( get_option( 'wanswers_migrated_from_cc_qa', false ) ) {
            return;
        }

        // Check if old data exists
        $old_version = get_option( 'cc_qa_db_version', '' );
        if ( ! $old_version ) {
            // Fresh install, nothing to migrate
            return;
        }

        // ── Rename custom tables ──
        $old_votes = $wpdb->prefix . 'cc_qa_votes';
        $new_votes = $wpdb->prefix . 'wanswers_votes';
        $old_subs  = $wpdb->prefix . 'cc_qa_subscriptions';
        $new_subs  = $wpdb->prefix . 'wanswers_subscriptions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_votes ) ) === $old_votes ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "RENAME TABLE `{$old_votes}` TO `{$new_votes}`" );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_subs ) ) === $old_subs ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "RENAME TABLE `{$old_subs}` TO `{$new_subs}`" );
        }

        // ── Rename post types ──
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->posts,
            array( 'post_type' => 'wanswers_question' ),
            array( 'post_type' => 'cc_question' ),
            array( '%s' ),
            array( '%s' )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->posts,
            array( 'post_type' => 'wanswers_answer' ),
            array( 'post_type' => 'cc_answer' ),
            array( '%s' ),
            array( '%s' )
        );

        // ── Rename taxonomy ──
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->term_taxonomy,
            array( 'taxonomy' => 'wanswers_question_topic' ),
            array( 'taxonomy' => 'cc_question_topic' ),
            array( '%s' ),
            array( '%s' )
        );

        // ── Rename post meta keys ──
        $meta_renames = array(
            '_cc_qa_votes'           => '_wanswers_votes',
            '_cc_qa_answer_count'    => '_wanswers_answer_count',
            '_cc_qa_accepted'        => '_wanswers_accepted',
            '_cc_qa_accepted_answer' => '_wanswers_accepted_answer',
            '_cc_qa_replies'         => '_wanswers_replies',
        );
        foreach ( $meta_renames as $old_key => $new_key ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_key' => $new_key ),
                array( 'meta_key' => $old_key ),
                array( '%s' ),
                array( '%s' )
            );
        }

        // ── Rename user meta keys ──
        $user_meta_renames = array(
            '_cc_qa_badges'            => '_wanswers_badges',
            '_cc_qa_lifetime_upvotes'  => '_wanswers_lifetime_upvotes',
            '_cc_qa_lifetime_downvotes'=> '_wanswers_lifetime_downvotes',
        );
        foreach ( $user_meta_renames as $old_key => $new_key ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->usermeta,
                array( 'meta_key' => $new_key ),
                array( 'meta_key' => $old_key ),
                array( '%s' ),
                array( '%s' )
            );
        }

        // ── Rename options ──
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $old_opts = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'cc\_qa\_%'"
        );
        foreach ( $old_opts as $opt ) {
            $new_name = str_replace( 'cc_qa_', 'wanswers_', $opt->option_name );
            // Only migrate if new option does not already exist
            if ( false === get_option( $new_name ) ) {
                update_option( $new_name, $opt->option_value );
            }
            delete_option( $opt->option_name );
        }

        // ── Rename cron hook ──
        $ts = wp_next_scheduled( 'cc_qa_weekly_digest' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'cc_qa_weekly_digest' );
        }
        wp_clear_scheduled_hook( 'cc_qa_weekly_digest' );

        // Mark migration complete
        update_option( 'wanswers_migrated_from_cc_qa', 1 );
    }

    // ── Vote helpers ──────────────────────────────────────────

    public static function get_vote_count( $post_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wanswers_votes' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COALESCE(SUM(vote),0) FROM `{$table}` WHERE post_id = %d", $post_id )
        );
    }

    public static function user_voted( $post_id, $user_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wanswers_votes' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM `{$table}` WHERE post_id = %d AND user_id = %d", $post_id, $user_id )
        );
    }

    public static function add_vote( $post_id, $user_id, $vote = 1 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wanswers_votes';

        if ( self::user_voted( $post_id, $user_id ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert( $table, array(
            'post_id'    => $post_id,
            'user_id'    => $user_id,
            'vote'       => $vote,
            'created_at' => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%d', '%d', '%s' ) );

        $count = self::get_vote_count( $post_id );
        update_post_meta( $post_id, '_wanswers_votes', $count );

        // Track lifetime votes on the post author — never reset with leaderboard resets
        $post_author = (int) get_post_field( 'post_author', $post_id );
        if ( $post_author ) {
            $meta_key = ( 1 === (int) $vote ) ? '_wanswers_lifetime_upvotes' : '_wanswers_lifetime_downvotes';
            $current  = (int) get_user_meta( $post_author, $meta_key, true );
            update_user_meta( $post_author, $meta_key, $current + 1 );
        }

        return $count;
    }

    /**
     * Backfill lifetime vote counts for a user from the votes table.
     *
     * The _wanswers_lifetime_upvotes / _wanswers_lifetime_downvotes meta keys were
     * introduced in v2.5. Users who had votes cast before that version have no
     * stored meta. This method counts all historical votes from the DB and writes
     * the meta once, then never runs again for that user (meta is already set).
     *
     * Called lazily on profile page load so it's a one-time cost per user.
     */
    public static function backfill_lifetime_votes( $user_id ) {
        global $wpdb;

        $user_id   = (int) $user_id;
        $posts_tbl = esc_sql( $wpdb->prefix . 'posts' );
        $votes_tbl = esc_sql( $wpdb->prefix . 'wanswers_votes' );

        // Only backfill if meta has never been set (get_user_meta returns '' not '0' when absent)
        $up_raw   = get_user_meta( $user_id, '_wanswers_lifetime_upvotes',   false );
        $down_raw = get_user_meta( $user_id, '_wanswers_lifetime_downvotes', false );

        $needs_up   = empty( $up_raw );
        $needs_down = empty( $down_raw );

        if ( ! $needs_up && ! $needs_down ) {
            return; // Already populated — nothing to do.
        }

        // Count upvotes received on all posts authored by this user
        if ( $needs_up ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $up = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$votes_tbl}` v
                 INNER JOIN `{$posts_tbl}` p ON p.ID = v.post_id
                 WHERE p.post_author = %d AND v.vote = 1
                   AND p.post_type IN ('wanswers_question','wanswers_answer')
                   AND p.post_status = 'publish'",
                $user_id
            ) );
            update_user_meta( $user_id, '_wanswers_lifetime_upvotes', $up );
        }

        if ( $needs_down ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $down = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$votes_tbl}` v
                 INNER JOIN `{$posts_tbl}` p ON p.ID = v.post_id
                 WHERE p.post_author = %d AND v.vote = -1
                   AND p.post_type IN ('wanswers_question','wanswers_answer')
                   AND p.post_status = 'publish'",
                $user_id
            ) );
            update_user_meta( $user_id, '_wanswers_lifetime_downvotes', $down );
        }
    }

    // ── Subscription helpers ──────────────────────────────────

    public static function subscribe( $question_id, $user_id, $email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wanswers_subscriptions';
        $token = bin2hex( random_bytes( 16 ) ); // 32-char random hex, never exposes user IDs

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->replace( $table, array(
            'question_id' => $question_id,
            'user_id'     => $user_id,
            'email'       => $email,
            'token'       => $token,
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
        ), array( '%d', '%d', '%s', '%s', '%s' ) );

        return $token;
    }

    /**
     * Get a single subscription row by its unsubscribe token.
     */
    public static function get_sub_by_token( $token ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wanswers_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE token = %s LIMIT 1", $token )
        );
    }

    /**
     * Delete a specific subscription by its unsubscribe token.
     */
    public static function unsubscribe_by_token( $token ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wanswers_subscriptions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (bool) $wpdb->delete( $table, array( 'token' => $token ), array( '%s' ) );
    }

    /**
     * Delete ALL subscriptions for a user — used by digest global unsubscribe.
     */
    public static function unsubscribe_all_for_user( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wanswers_subscriptions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
    }

    /**
     * Returns one row per unique email/user for the weekly digest mailer.
     * Carries one representative token per user for the global unsubscribe link.
     */
    public static function get_digest_subscribers() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wanswers_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            "SELECT user_id, email, MIN(token) AS token FROM `{$table}` GROUP BY user_id, email"
        );
    }

    public static function get_subscribers( $question_id ) {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'wanswers_subscriptions' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare( "SELECT user_id, email, token FROM `{$table}` WHERE question_id = %d", $question_id )
        );
    }

    public static function get_all_subscribers() {
        global $wpdb;
        $subs  = esc_sql( $wpdb->prefix . 'wanswers_subscriptions' );
        $users = esc_sql( $wpdb->prefix . 'users' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            "SELECT DISTINCT s.email, u.display_name FROM `{$subs}` s LEFT JOIN `{$users}` u ON u.ID = s.user_id"
        );
    }
}
