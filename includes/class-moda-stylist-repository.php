<?php

if (!defined('ABSPATH')) {
    exit;
}

class Moda_Stylist_Repository {
    private \wpdb $wpdb;
    private string $stylists_table;
    private string $celebrities_table;
    private string $links_table;
    private string $reps_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->stylists_table = $wpdb->prefix . 'moda_stylists';
        $this->celebrities_table = $wpdb->prefix . 'moda_celebrities';
        $this->links_table = $wpdb->prefix . 'moda_stylist_celebrity';
        $this->reps_table = $wpdb->prefix . 'moda_stylist_reps';
    }

    public function list_stylists(
        int $page = 1,
        int $per_page = 20,
        string $q = '',
        string $sort = 'updated_at',
        string $celebrity_filter = '',
        ?int $stylist_id = null
    ): array {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;
        $sort_sql = $sort === 'name' ? 's.full_name ASC' : 's.updated_at DESC';

        $joins = '';
        $where_parts = array('1=1');
        $params = array();

        if ($stylist_id !== null && $stylist_id > 0) {
            $where_parts[] = 's.id = %d';
            $params[] = $stylist_id;
        }

        if ($q !== '') {
            $where_parts[] = 's.full_name LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($q) . '%';
        }

        if ($celebrity_filter !== '') {
            if (ctype_digit($celebrity_filter)) {
                $joins .= " INNER JOIN {$this->links_table} l_filter ON l_filter.stylist_id = s.id";
                $where_parts[] = 'l_filter.celebrity_id = %d';
                $params[] = (int) $celebrity_filter;
            } else {
                $joins .= " INNER JOIN {$this->links_table} l_filter ON l_filter.stylist_id = s.id
                            INNER JOIN {$this->celebrities_table} c_filter ON c_filter.id = l_filter.celebrity_id";
                $where_parts[] = 'c_filter.full_name LIKE %s';
                $params[] = '%' . $this->wpdb->esc_like($celebrity_filter) . '%';
            }
        }

        $where_sql = implode(' AND ', $where_parts);

        $count_sql = "SELECT COUNT(DISTINCT s.id)
            FROM {$this->stylists_table} s
            {$joins}
            WHERE {$where_sql}";
        $count_query = !empty($params) ? $this->wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int) $this->wpdb->get_var($count_query);

        $data_sql = "SELECT
                s.id,
                s.full_name,
                s.email,
                s.phone,
                s.instagram,
                s.website,
                s.created_at,
                s.updated_at,
                COUNT(DISTINCT l.celebrity_id) AS celebrity_count
            FROM {$this->stylists_table} s
            LEFT JOIN {$this->links_table} l ON l.stylist_id = s.id
            {$joins}
            WHERE {$where_sql}
            GROUP BY s.id
            ORDER BY {$sort_sql}
            LIMIT %d OFFSET %d";

        $data_params = $params;
        $data_params[] = $per_page;
        $data_params[] = $offset;
        $data_query = $this->wpdb->prepare($data_sql, $data_params);
        $items = $this->wpdb->get_results($data_query, ARRAY_A);

        return array(
            'items' => $items,
            'meta' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ),
        );
    }

    public function list_celebrities(
        int $page = 1,
        int $per_page = 20,
        string $q = '',
        ?int $celebrity_id = null
    ): array {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;

        $where_parts = array('1=1');
        $params = array();

        if ($celebrity_id !== null && $celebrity_id > 0) {
            $where_parts[] = 'c.id = %d';
            $params[] = $celebrity_id;
        }

        if ($q !== '') {
            $where_parts[] = 'c.full_name LIKE %s';
            $params[] = '%' . $this->wpdb->esc_like($q) . '%';
        }

        $where_sql = implode(' AND ', $where_parts);

        $count_sql = "SELECT COUNT(*) FROM {$this->celebrities_table} c WHERE {$where_sql}";
        $count_query = !empty($params) ? $this->wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int) $this->wpdb->get_var($count_query);

        $data_sql = "SELECT
                c.id,
                c.full_name,
                c.industry,
                c.created_at,
                c.updated_at,
                COUNT(DISTINCT l.stylist_id) AS stylist_count
            FROM {$this->celebrities_table} c
            LEFT JOIN {$this->links_table} l ON l.celebrity_id = c.id
            WHERE {$where_sql}
            GROUP BY c.id
            ORDER BY c.full_name ASC
            LIMIT %d OFFSET %d";

        $data_params = $params;
        $data_params[] = $per_page;
        $data_params[] = $offset;
        $data_query = $this->wpdb->prepare($data_sql, $data_params);
        $items = $this->wpdb->get_results($data_query, ARRAY_A);

        return array(
            'items' => $items,
            'meta' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ),
        );
    }

    public function get_stylist(int $id): ?array {
        $stylist_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->stylists_table} WHERE id = %d",
            $id
        );
        $stylist = $this->wpdb->get_row($stylist_sql, ARRAY_A);
        if (!$stylist) {
            return null;
        }

        $reps_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->reps_table} WHERE stylist_id = %d ORDER BY rep_name ASC",
            $id
        );
        $reps = $this->wpdb->get_results($reps_sql, ARRAY_A);

        $celebs_sql = $this->wpdb->prepare(
            "SELECT c.id, c.full_name, c.industry, l.notes
             FROM {$this->links_table} l
             INNER JOIN {$this->celebrities_table} c ON c.id = l.celebrity_id
             WHERE l.stylist_id = %d
             ORDER BY c.full_name ASC",
            $id
        );
        $celebrities = $this->wpdb->get_results($celebs_sql, ARRAY_A);

        $stylist['reps'] = $reps;
        $stylist['celebrities'] = $celebrities;
        return $stylist;
    }

    public function get_celebrity(int $id): ?array {
        $celebrity_sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->celebrities_table} WHERE id = %d",
            $id
        );
        $celebrity = $this->wpdb->get_row($celebrity_sql, ARRAY_A);
        if (!$celebrity) {
            return null;
        }

        $stylists_sql = $this->wpdb->prepare(
            "SELECT s.id, s.full_name, s.email, s.phone, l.notes
             FROM {$this->links_table} l
             INNER JOIN {$this->stylists_table} s ON s.id = l.stylist_id
             WHERE l.celebrity_id = %d
             ORDER BY s.full_name ASC",
            $id
        );
        $celebrity['stylists'] = $this->wpdb->get_results($stylists_sql, ARRAY_A);

        return $celebrity;
    }

    public function create_stylist(array $data): int {
        $now = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->stylists_table,
            array(
                'full_name' => $data['full_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'instagram' => $data['instagram'] ?? null,
                'website' => $data['website'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function update_stylist(int $id, array $data): bool {
        if (empty($data)) {
            return false;
        }

        $data['updated_at'] = current_time('mysql');
        $format = array();
        foreach (array_keys($data) as $field) {
            if ($field === 'updated_at') {
                $format[] = '%s';
                continue;
            }
            $format[] = '%s';
        }

        $updated = $this->wpdb->update(
            $this->stylists_table,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
        return $updated !== false;
    }

    public function update_celebrity(int $id, array $data): bool {
        if (empty($data)) {
            return false;
        }

        $data['updated_at'] = current_time('mysql');
        $format = array();
        foreach (array_keys($data) as $field) {
            if ($field === 'updated_at') {
                $format[] = '%s';
                continue;
            }
            $format[] = '%s';
        }

        $updated = $this->wpdb->update(
            $this->celebrities_table,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );
        return $updated !== false;
    }

    public function attach_celebrity_to_stylist(int $stylist_id, ?int $celebrity_id, ?string $celebrity_name, ?string $notes): array {
        if ($celebrity_id === null) {
            if ($celebrity_name === null || $celebrity_name === '') {
                return array('ok' => false, 'error' => 'Provide celebrity_id or celebrity_name.');
            }

            $existing_sql = $this->wpdb->prepare(
                "SELECT id FROM {$this->celebrities_table} WHERE full_name = %s LIMIT 1",
                $celebrity_name
            );
            $existing_id = (int) $this->wpdb->get_var($existing_sql);
            if ($existing_id > 0) {
                $celebrity_id = $existing_id;
            } else {
                $now = current_time('mysql');
                $created = $this->wpdb->insert(
                    $this->celebrities_table,
                    array(
                        'full_name' => $celebrity_name,
                        'industry' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                if ($created === false) {
                    return array('ok' => false, 'error' => 'Failed to create celebrity.');
                }
                $celebrity_id = (int) $this->wpdb->insert_id;
            }
        }

        $added = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT IGNORE INTO {$this->links_table} (stylist_id, celebrity_id, notes) VALUES (%d, %d, %s)",
                $stylist_id,
                $celebrity_id,
                $notes ?? ''
            )
        );

        if ($added === false) {
            return array('ok' => false, 'error' => 'Failed to attach celebrity.');
        }

        return array('ok' => true, 'celebrity_id' => $celebrity_id);
    }

    public function detach_celebrity_from_stylist(int $stylist_id, int $celebrity_id): bool {
        $deleted = $this->wpdb->delete(
            $this->links_table,
            array('stylist_id' => $stylist_id, 'celebrity_id' => $celebrity_id),
            array('%d', '%d')
        );
        return $deleted !== false && $deleted > 0;
    }

    public function add_rep(int $stylist_id, array $data): int {
        $now = current_time('mysql');
        $inserted = $this->wpdb->insert(
            $this->reps_table,
            array(
                'stylist_id' => $stylist_id,
                'rep_name' => $data['rep_name'],
                'company' => $data['company'] ?? null,
                'rep_email' => $data['rep_email'] ?? null,
                'rep_phone' => $data['rep_phone'] ?? null,
                'territory' => $data['territory'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    public function delete_rep(int $rep_id): bool {
        $deleted = $this->wpdb->delete($this->reps_table, array('id' => $rep_id), array('%d'));
        return $deleted !== false && $deleted > 0;
    }

    public function celebrity_exists(int $celebrity_id): bool {
        $sql = $this->wpdb->prepare("SELECT id FROM {$this->celebrities_table} WHERE id = %d", $celebrity_id);
        return (int) $this->wpdb->get_var($sql) > 0;
    }

    public function stylist_exists(int $stylist_id): bool {
        $sql = $this->wpdb->prepare("SELECT id FROM {$this->stylists_table} WHERE id = %d", $stylist_id);
        return (int) $this->wpdb->get_var($sql) > 0;
    }

    public function get_celebrity_options(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = $this->wpdb->prepare(
            "SELECT id, full_name FROM {$this->celebrities_table} ORDER BY full_name ASC LIMIT %d",
            $limit
        );
        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function seed_data(int $stylists, int $celebs, int $links): void {
        $industries = array('Music', 'Film/TV', 'Sports', 'Fashion');
        $now = current_time('mysql');

        $this->seed_celebrities_batch($celebs, $industries, $now);
        $this->seed_stylists_batch($stylists, $now);

        $stylist_ids = $this->wpdb->get_col("SELECT id FROM {$this->stylists_table}");
        $celebrity_ids = $this->wpdb->get_col("SELECT id FROM {$this->celebrities_table}");
        if (empty($stylist_ids) || empty($celebrity_ids)) {
            return;
        }

        $existing_links = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->links_table}");
        $target_new_links = max(0, $links - $existing_links);
        if ($target_new_links === 0) {
            return;
        }

        $inserted_total = 0;
        $attempts = 0;
        $max_attempts = 200;
        $chunk_size = 500;

        while ($inserted_total < $target_new_links && $attempts < $max_attempts) {
            $remaining = $target_new_links - $inserted_total;
            $current = min($chunk_size, $remaining + 100);
            $placeholders = array();
            $params = array();

            for ($i = 0; $i < $current; $i++) {
                $placeholders[] = '(%d, %d, %s)';
                $params[] = (int) $stylist_ids[array_rand($stylist_ids)];
                $params[] = (int) $celebrity_ids[array_rand($celebrity_ids)];
                $params[] = sprintf('Sample collaboration %d', $existing_links + $inserted_total + $i + 1);
            }

            $sql = "INSERT IGNORE INTO {$this->links_table} (stylist_id, celebrity_id, notes) VALUES " . implode(', ', $placeholders);
            $affected = (int) $this->wpdb->query($this->wpdb->prepare($sql, $params));
            if ($affected > 0) {
                $inserted_total += $affected;
            }
            $attempts++;
        }
    }

    private function seed_celebrities_batch(int $celebs, array $industries, string $now): void {
        $chunk_size = 500;
        for ($offset = 1; $offset <= $celebs; $offset += $chunk_size) {
            $current = min($chunk_size, $celebs - $offset + 1);
            $placeholders = array();
            $params = array();

            for ($i = 0; $i < $current; $i++) {
                $index = $offset + $i;
                $placeholders[] = '(%s, %s, %s, %s)';
                $params[] = sprintf('Celebrity %d', $index);
                $params[] = $industries[array_rand($industries)];
                $params[] = $now;
                $params[] = $now;
            }

            $sql = "INSERT INTO {$this->celebrities_table} (full_name, industry, created_at, updated_at) VALUES " . implode(', ', $placeholders);
            $this->wpdb->query($this->wpdb->prepare($sql, $params));
        }
    }

    private function seed_stylists_batch(int $stylists, string $now): void {
        $chunk_size = 500;
        for ($offset = 1; $offset <= $stylists; $offset += $chunk_size) {
            $current = min($chunk_size, $stylists - $offset + 1);
            $placeholders = array();
            $params = array();

            for ($i = 0; $i < $current; $i++) {
                $index = $offset + $i;
                $placeholders[] = '(%s, %s, %s, %s, %s, %s, %s)';
                $params[] = sprintf('Stylist %d', $index);
                $params[] = sprintf('stylist%d@example.com', $index);
                $params[] = sprintf('+1-555-%04d', $index % 10000);
                $params[] = sprintf('@stylist_%d', $index);
                $params[] = sprintf('https://stylist%d.example.com', $index);
                $params[] = $now;
                $params[] = $now;
            }

            $sql = "INSERT INTO {$this->stylists_table} (full_name, email, phone, instagram, website, created_at, updated_at) VALUES " . implode(', ', $placeholders);
            $this->wpdb->query($this->wpdb->prepare($sql, $params));
        }
    }
}
