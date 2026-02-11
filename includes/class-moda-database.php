<?php

if (!defined('ABSPATH')) {
    exit;
}

class Moda_Database {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $stylists_table = $wpdb->prefix . 'moda_stylists';
        $celebrities_table = $wpdb->prefix . 'moda_celebrities';
        $links_table = $wpdb->prefix . 'moda_stylist_celebrity';
        $reps_table = $wpdb->prefix . 'moda_stylist_reps';

        $sql_stylists = "CREATE TABLE {$stylists_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(100) NULL,
            instagram VARCHAR(255) NULL,
            website VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_full_name (full_name),
            KEY idx_updated_at (updated_at)
        ) {$charset_collate};";

        $sql_celebrities = "CREATE TABLE {$celebrities_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(255) NOT NULL,
            industry VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_full_name (full_name),
            KEY idx_industry (industry)
        ) {$charset_collate};";

        $sql_links = "CREATE TABLE {$links_table} (
            stylist_id BIGINT UNSIGNED NOT NULL,
            celebrity_id BIGINT UNSIGNED NOT NULL,
            notes VARCHAR(255) NULL,
            UNIQUE KEY uniq_stylist_celebrity (stylist_id, celebrity_id),
            KEY idx_celebrity_stylist (celebrity_id, stylist_id),
            KEY idx_stylist (stylist_id)
        ) {$charset_collate};";

        $sql_reps = "CREATE TABLE {$reps_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stylist_id BIGINT UNSIGNED NOT NULL,
            rep_name VARCHAR(255) NOT NULL,
            company VARCHAR(255) NULL,
            rep_email VARCHAR(255) NULL,
            rep_phone VARCHAR(100) NULL,
            territory VARCHAR(100) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_stylist_id (stylist_id),
            KEY idx_rep_name (rep_name)
        ) {$charset_collate};";

        dbDelta($sql_stylists);
        dbDelta($sql_celebrities);
        dbDelta($sql_links);
        dbDelta($sql_reps);
    }
}

