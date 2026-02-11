<?php

if (!defined('ABSPATH')) {
    exit;
}

class Moda_Seeder {
    private Moda_Stylist_Repository $repository;
    private \wpdb $wpdb;

    public function __construct(Moda_Stylist_Repository $repository) {
        global $wpdb;
        $this->repository = $repository;
        $this->wpdb = $wpdb;
    }

    public function register(): void {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('moda seed', array($this, 'seed_command'));
            WP_CLI::add_command('moda verify', array($this, 'verify_command'));
        }
    }

    /**
     * Seed sample data for performance testing.
     *
     * ## OPTIONS
     *
     * [--stylists=<count>]
     * : Number of stylists to generate.
     *
     * [--celebs=<count>]
     * : Number of celebrities to generate.
     *
     * [--links=<count>]
     * : Number of stylist-celebrity links to generate.
     *
     * ## EXAMPLES
     *
     *     wp moda seed --stylists=2000 --celebs=5000 --links=30000
     */
    public function seed_command(array $args, array $assoc_args): void {
        $stylists = isset($assoc_args['stylists']) ? (int) $assoc_args['stylists'] : 2000;
        $celebs = isset($assoc_args['celebs']) ? (int) $assoc_args['celebs'] : 5000;
        $links = isset($assoc_args['links']) ? (int) $assoc_args['links'] : 30000;

        if ($stylists <= 0 || $celebs <= 0 || $links <= 0) {
            WP_CLI::error('All values must be > 0.');
            return;
        }

        WP_CLI::log(sprintf('Seeding: stylists=%d, celebs=%d, links=%d', $stylists, $celebs, $links));
        $start = microtime(true);
        $this->repository->seed_data($stylists, $celebs, $links);
        $duration = microtime(true) - $start;
        WP_CLI::success(sprintf('Seed completed in %.2f seconds.', $duration));
    }

    /**
     * Verify core plugin requirements quickly from WP-CLI.
     *
     * ## OPTIONS
     *
     * [--min-stylists=<count>]
     * : Assert stylists row count is at least this value.
     *
     * [--min-celebs=<count>]
     * : Assert celebrities row count is at least this value.
     *
     * [--min-links=<count>]
     * : Assert link row count is at least this value.
     *
     * ## EXAMPLES
     *
     *     wp moda verify
     *     wp moda verify --min-stylists=2000 --min-celebs=5000 --min-links=30000
     */
    public function verify_command(array $args, array $assoc_args): void {
        $prefix = $this->wpdb->prefix;
        $tables = array(
            'stylists' => $prefix . 'moda_stylists',
            'celebrities' => $prefix . 'moda_celebrities',
            'links' => $prefix . 'moda_stylist_celebrity',
            'reps' => $prefix . 'moda_stylist_reps',
        );

        $required_columns = array(
            'stylists' => array('id', 'full_name', 'email', 'phone', 'instagram', 'website', 'created_at', 'updated_at'),
            'celebrities' => array('id', 'full_name', 'industry', 'created_at', 'updated_at'),
            'links' => array('stylist_id', 'celebrity_id', 'notes'),
            'reps' => array('id', 'stylist_id', 'rep_name', 'company', 'rep_email', 'rep_phone', 'territory', 'created_at', 'updated_at'),
        );

        $required_indexes = array(
            'stylists' => array('PRIMARY', 'idx_full_name', 'idx_updated_at'),
            'celebrities' => array('PRIMARY', 'idx_full_name', 'idx_industry'),
            'links' => array('uniq_stylist_celebrity', 'idx_celebrity_stylist', 'idx_stylist'),
            'reps' => array('PRIMARY', 'idx_stylist_id', 'idx_rep_name'),
        );

        $errors = array();
        foreach ($tables as $key => $table) {
            $exists = (string) $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                $errors[] = "Missing table: {$table}";
                continue;
            }

            $columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
            foreach ($required_columns[$key] as $column) {
                if (!in_array($column, $columns, true)) {
                    $errors[] = "Missing column {$table}.{$column}";
                }
            }

            $index_rows = $this->wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
            $index_names = array_unique(array_map(static function ($row) {
                return $row['Key_name'];
            }, $index_rows));
            foreach ($required_indexes[$key] as $index_name) {
                if (!in_array($index_name, $index_names, true)) {
                    $errors[] = "Missing index {$table}.{$index_name}";
                }
            }
        }

        $count_stylists = 0;
        $count_celebs = 0;
        $count_links = 0;
        $count_reps = 0;
        if (empty($errors)) {
            $count_stylists = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$tables['stylists']}");
            $count_celebs = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$tables['celebrities']}");
            $count_links = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$tables['links']}");
            $count_reps = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$tables['reps']}");
        }

        $min_stylists = isset($assoc_args['min-stylists']) ? (int) $assoc_args['min-stylists'] : null;
        $min_celebs = isset($assoc_args['min-celebs']) ? (int) $assoc_args['min-celebs'] : null;
        $min_links = isset($assoc_args['min-links']) ? (int) $assoc_args['min-links'] : null;

        if (empty($errors) && $min_stylists !== null && $count_stylists < $min_stylists) {
            $errors[] = "Stylists count {$count_stylists} is below expected minimum {$min_stylists}.";
        }
        if (empty($errors) && $min_celebs !== null && $count_celebs < $min_celebs) {
            $errors[] = "Celebrities count {$count_celebs} is below expected minimum {$min_celebs}.";
        }
        if (empty($errors) && $min_links !== null && $count_links < $min_links) {
            $errors[] = "Links count {$count_links} is below expected minimum {$min_links}.";
        }

        WP_CLI::log("Counts: stylists={$count_stylists}, celebrities={$count_celebs}, links={$count_links}, reps={$count_reps}");

        if (!empty($errors)) {
            foreach ($errors as $error) {
                WP_CLI::warning($error);
            }
            WP_CLI::error('Verification failed.');
            return;
        }

        WP_CLI::success('Verification passed.');
    }
}
