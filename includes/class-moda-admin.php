<?php

if (!defined('ABSPATH')) {
    exit;
}

class Moda_Admin {
    private Moda_Stylist_Repository $repository;

    public function __construct(Moda_Stylist_Repository $repository) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'handle_post_actions'));
    }

    public function register_menu(): void {
        add_menu_page(
            'Moda Database',
            'Moda Database',
            'manage_options',
            'moda-database',
            array($this, 'render_stylists_page'),
            'dashicons-database',
            58
        );

        add_submenu_page(
            'moda-database',
            'Celebrities',
            'Celebrities',
            'manage_options',
            'moda-celebrities',
            array($this, 'render_celebrities_page')
        );

        add_submenu_page(
            null,
            'Stylist Detail',
            'Stylist Detail',
            'manage_options',
            'moda-stylist-detail',
            array($this, 'render_stylist_detail_page')
        );
    }

    public function handle_post_actions(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['moda_action'])) {
            return;
        }

        check_admin_referer('moda_admin_action', 'moda_nonce');

        $action = sanitize_text_field(wp_unslash((string) $_POST['moda_action']));
        $stylist_id = isset($_POST['stylist_id']) ? (int) $_POST['stylist_id'] : 0;
        $redirect = admin_url('admin.php?page=moda-database');

        if ($stylist_id > 0) {
            $redirect = admin_url('admin.php?page=moda-stylist-detail&id=' . $stylist_id);
        }

        if ($action === 'add_rep' && $stylist_id > 0) {
            $rep_name = sanitize_text_field(wp_unslash((string) ($_POST['rep_name'] ?? '')));
            if ($rep_name === '') {
                $this->redirect_with_notice($redirect, 'Rep name is required.', 'error');
            }

            $id = $this->repository->add_rep($stylist_id, array(
                'rep_name' => $rep_name,
                'company' => sanitize_text_field(wp_unslash((string) ($_POST['company'] ?? ''))),
                'rep_email' => sanitize_email(wp_unslash((string) ($_POST['rep_email'] ?? ''))),
                'rep_phone' => sanitize_text_field(wp_unslash((string) ($_POST['rep_phone'] ?? ''))),
                'territory' => sanitize_text_field(wp_unslash((string) ($_POST['territory'] ?? ''))),
            ));

            if ($id <= 0) {
                $this->redirect_with_notice($redirect, 'Failed to add rep.', 'error');
            }
            $this->redirect_with_notice($redirect, 'Rep added.', 'success');
        }

        if ($action === 'delete_rep') {
            $rep_id = (int) ($_POST['rep_id'] ?? 0);
            $ok = $rep_id > 0 && $this->repository->delete_rep($rep_id);
            if (!$ok) {
                $this->redirect_with_notice($redirect, 'Failed to delete rep.', 'error');
            }
            $this->redirect_with_notice($redirect, 'Rep deleted.', 'success');
        }

        if ($action === 'attach_celebrity' && $stylist_id > 0) {
            $celebrity_id_raw = sanitize_text_field(wp_unslash((string) ($_POST['celebrity_id'] ?? '')));
            $celebrity_name = sanitize_text_field(wp_unslash((string) ($_POST['celebrity_name'] ?? '')));
            $notes = sanitize_text_field(wp_unslash((string) ($_POST['notes'] ?? '')));

            $celebrity_id = null;
            if ($celebrity_id_raw !== '' && ctype_digit($celebrity_id_raw)) {
                $celebrity_id = (int) $celebrity_id_raw;
            }

            $result = $this->repository->attach_celebrity_to_stylist($stylist_id, $celebrity_id, $celebrity_name, $notes);
            if (!$result['ok']) {
                $this->redirect_with_notice($redirect, $result['error'], 'error');
            }
            $this->redirect_with_notice($redirect, 'Celebrity attached.', 'success');
        }

        if ($action === 'detach_celebrity' && $stylist_id > 0) {
            $celebrity_id = (int) ($_POST['celebrity_id'] ?? 0);
            $ok = $celebrity_id > 0 && $this->repository->detach_celebrity_from_stylist($stylist_id, $celebrity_id);
            if (!$ok) {
                $this->redirect_with_notice($redirect, 'Failed to detach celebrity.', 'error');
            }
            $this->redirect_with_notice($redirect, 'Celebrity detached.', 'success');
        }
    }

    public function render_stylists_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
        $celebrity = isset($_GET['celebrity']) ? sanitize_text_field(wp_unslash((string) $_GET['celebrity'])) : '';
        $stylist_id = isset($_GET['stylist_id']) ? (int) $_GET['stylist_id'] : 0;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $result = $this->repository->list_stylists($page, 20, $q, 'updated_at', $celebrity, $stylist_id > 0 ? $stylist_id : null);
        $items = $result['items'];
        $meta = $result['meta'];

        echo '<div class="wrap">';
        echo '<h1>Moda Database</h1>';
        $this->render_notice();

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="moda-database" />';
        echo '<p>';
        echo '<label for="stylist_id"><strong>Stylist ID:</strong></label> ';
        echo '<input id="stylist_id" type="number" min="1" name="stylist_id" value="' . ($stylist_id > 0 ? (int) $stylist_id : '') . '" style="width:110px;" />';
        echo '&nbsp;&nbsp;';
        echo '<label for="q"><strong>Search stylist:</strong></label> ';
        echo '<input id="q" type="text" name="q" value="' . esc_attr($q) . '" />';
        echo '&nbsp;&nbsp;';
        echo '<label for="celebrity"><strong>Filter by celebrity:</strong></label> ';
        echo '<input id="celebrity" type="text" name="celebrity" value="' . esc_attr($celebrity) . '" placeholder="ID or name" />';
        echo '&nbsp;&nbsp;<button class="button button-primary" type="submit">Filter</button>';
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Updated</th><th>Celebrities</th>';
        echo '</tr></thead><tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="6">No stylists found.</td></tr>';
        } else {
            foreach ($items as $item) {
                $detail_url = admin_url('admin.php?page=moda-stylist-detail&id=' . (int) $item['id']);
                echo '<tr>';
                echo '<td>' . (int) $item['id'] . '</td>';
                echo '<td><a href="' . esc_url($detail_url) . '">' . esc_html($item['full_name']) . '</a></td>';
                echo '<td>' . esc_html((string) $item['email']) . '</td>';
                echo '<td>' . esc_html((string) $item['phone']) . '</td>';
                echo '<td>' . esc_html((string) $item['updated_at']) . '</td>';
                echo '<td>' . (int) $item['celebrity_count'] . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        $this->render_pagination($meta, array(
            'page' => 'moda-database',
            'q' => $q,
            'celebrity' => $celebrity,
            'stylist_id' => $stylist_id > 0 ? $stylist_id : '',
        ));
        echo '</div>';
    }

    public function render_celebrities_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
        $celebrity_id = isset($_GET['celebrity_id']) ? (int) $_GET['celebrity_id'] : 0;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

        $result = $this->repository->list_celebrities($page, 20, $q, $celebrity_id > 0 ? $celebrity_id : null);
        $items = $result['items'];
        $meta = $result['meta'];

        echo '<div class="wrap">';
        echo '<h1>Celebrities</h1>';
        $this->render_notice();

        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="moda-celebrities" />';
        echo '<p>';
        echo '<label for="celebrity_id"><strong>Celebrity ID:</strong></label> ';
        echo '<input id="celebrity_id" type="number" min="1" name="celebrity_id" value="' . ($celebrity_id > 0 ? (int) $celebrity_id : '') . '" style="width:120px;" />';
        echo '&nbsp;&nbsp;';
        echo '<label for="q"><strong>Search name:</strong></label> ';
        echo '<input id="q" type="text" name="q" value="' . esc_attr($q) . '" />';
        echo '&nbsp;&nbsp;<button class="button button-primary" type="submit">Filter</button>';
        echo '</p>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>ID</th><th>Name</th><th>Industry</th><th>Stylist Count</th><th>Updated</th><th>Open Stylists</th>';
        echo '</tr></thead><tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="6">No celebrities found.</td></tr>';
        } else {
            foreach ($items as $item) {
                $stylists_url = add_query_arg(
                    array(
                        'page' => 'moda-database',
                        'celebrity' => (int) $item['id'],
                    ),
                    admin_url('admin.php')
                );

                echo '<tr>';
                echo '<td>' . (int) $item['id'] . '</td>';
                echo '<td>' . esc_html((string) $item['full_name']) . '</td>';
                echo '<td>' . esc_html((string) $item['industry']) . '</td>';
                echo '<td>' . (int) $item['stylist_count'] . '</td>';
                echo '<td>' . esc_html((string) $item['updated_at']) . '</td>';
                echo '<td><a href="' . esc_url($stylists_url) . '">View Stylists</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        $this->render_pagination($meta, array(
            'page' => 'moda-celebrities',
            'q' => $q,
            'celebrity_id' => $celebrity_id > 0 ? $celebrity_id : '',
        ));
        echo '</div>';
    }

    public function render_stylist_detail_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die('Invalid stylist id.');
        }

        $stylist = $this->repository->get_stylist($id);
        if (!$stylist) {
            wp_die('Stylist not found.');
        }

        echo '<div class="wrap">';
        echo '<h1>Stylist Detail</h1>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=moda-database')) . '">&larr; Back to list</a></p>';
        $this->render_notice();

        echo '<h2>' . esc_html($stylist['full_name']) . '</h2>';
        echo '<p><strong>Email:</strong> ' . esc_html((string) $stylist['email']) . '</p>';
        echo '<p><strong>Phone:</strong> ' . esc_html((string) $stylist['phone']) . '</p>';
        echo '<p><strong>Instagram:</strong> ' . esc_html((string) $stylist['instagram']) . '</p>';
        echo '<p><strong>Website:</strong> ' . esc_html((string) $stylist['website']) . '</p>';

        echo '<hr />';
        echo '<h2>Representatives</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Territory</th><th></th></tr></thead><tbody>';
        if (empty($stylist['reps'])) {
            echo '<tr><td colspan="6">No reps.</td></tr>';
        } else {
            foreach ($stylist['reps'] as $rep) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $rep['rep_name']) . '</td>';
                echo '<td>' . esc_html((string) $rep['company']) . '</td>';
                echo '<td>' . esc_html((string) $rep['rep_email']) . '</td>';
                echo '<td>' . esc_html((string) $rep['rep_phone']) . '</td>';
                echo '<td>' . esc_html((string) $rep['territory']) . '</td>';
                echo '<td>';
                echo '<form method="post" style="margin:0;">';
                wp_nonce_field('moda_admin_action', 'moda_nonce');
                echo '<input type="hidden" name="moda_action" value="delete_rep" />';
                echo '<input type="hidden" name="stylist_id" value="' . (int) $id . '" />';
                echo '<input type="hidden" name="rep_id" value="' . (int) $rep['id'] . '" />';
                echo '<button class="button button-secondary" type="submit">Delete</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h3>Add Rep</h3>';
        echo '<form method="post">';
        wp_nonce_field('moda_admin_action', 'moda_nonce');
        echo '<input type="hidden" name="moda_action" value="add_rep" />';
        echo '<input type="hidden" name="stylist_id" value="' . (int) $id . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Rep Name</th><td><input type="text" name="rep_name" required /></td></tr>';
        echo '<tr><th>Company</th><td><input type="text" name="company" /></td></tr>';
        echo '<tr><th>Email</th><td><input type="email" name="rep_email" /></td></tr>';
        echo '<tr><th>Phone</th><td><input type="text" name="rep_phone" /></td></tr>';
        echo '<tr><th>Territory</th><td><input type="text" name="territory" /></td></tr>';
        echo '</tbody></table>';
        echo '<button class="button button-primary" type="submit">Add Rep</button>';
        echo '</form>';

        echo '<hr />';
        echo '<h2>Celebrities</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Industry</th><th>Notes</th><th></th></tr></thead><tbody>';
        if (empty($stylist['celebrities'])) {
            echo '<tr><td colspan="5">No celebrities attached.</td></tr>';
        } else {
            foreach ($stylist['celebrities'] as $celebrity) {
                echo '<tr>';
                echo '<td>' . (int) $celebrity['id'] . '</td>';
                echo '<td>' . esc_html((string) $celebrity['full_name']) . '</td>';
                echo '<td>' . esc_html((string) $celebrity['industry']) . '</td>';
                echo '<td>' . esc_html((string) $celebrity['notes']) . '</td>';
                echo '<td>';
                echo '<form method="post" style="margin:0;">';
                wp_nonce_field('moda_admin_action', 'moda_nonce');
                echo '<input type="hidden" name="moda_action" value="detach_celebrity" />';
                echo '<input type="hidden" name="stylist_id" value="' . (int) $id . '" />';
                echo '<input type="hidden" name="celebrity_id" value="' . (int) $celebrity['id'] . '" />';
                echo '<button class="button button-secondary" type="submit">Detach</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        echo '<h3>Attach Celebrity</h3>';
        echo '<form method="post">';
        wp_nonce_field('moda_admin_action', 'moda_nonce');
        echo '<input type="hidden" name="moda_action" value="attach_celebrity" />';
        echo '<input type="hidden" name="stylist_id" value="' . (int) $id . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Celebrity ID (existing)</th><td><input type="number" min="1" name="celebrity_id" /></td></tr>';
        echo '<tr><th>Celebrity Name (create if missing)</th><td><input type="text" name="celebrity_name" /></td></tr>';
        echo '<tr><th>Notes</th><td><input type="text" name="notes" /></td></tr>';
        echo '</tbody></table>';
        echo '<button class="button button-primary" type="submit">Attach Celebrity</button>';
        echo '</form>';

        echo '</div>';
    }

    private function render_notice(): void {
        $notice = isset($_GET['moda_notice']) ? sanitize_text_field(wp_unslash((string) $_GET['moda_notice'])) : '';
        $type = isset($_GET['moda_notice_type']) ? sanitize_text_field(wp_unslash((string) $_GET['moda_notice_type'])) : '';
        if ($notice === '') {
            return;
        }
        $class = $type === 'success' ? 'notice notice-success' : 'notice notice-error';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice) . '</p></div>';
    }

    private function redirect_with_notice(string $url, string $message, string $type): void {
        $url = add_query_arg(
            array(
                'moda_notice' => $message,
                'moda_notice_type' => $type,
            ),
            $url
        );
        wp_safe_redirect($url);
        exit;
    }

    private function render_pagination(array $meta, array $base_args): void {
        if ($meta['total_pages'] <= 1) {
            return;
        }

        $current = (int) $meta['page'];
        $total = (int) $meta['total_pages'];
        $window = 2;
        $pages = array(1, $total);
        for ($p = max(1, $current - $window); $p <= min($total, $current + $window); $p++) {
            $pages[] = $p;
        }
        $pages = array_values(array_unique($pages));
        sort($pages);

        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';

        if ($current > 1) {
            $prev_url = add_query_arg(array_merge($base_args, array('paged' => $current - 1)), admin_url('admin.php'));
            echo '<a class="button" style="margin-right:6px;" href="' . esc_url($prev_url) . '">&laquo; Prev</a>';
        }

        $last_printed = 0;
        foreach ($pages as $page_num) {
            if ($last_printed > 0 && $page_num > $last_printed + 1) {
                echo '<span style="margin:0 6px;">...</span>';
            }

            if ($page_num === $current) {
                echo '<span class="button button-primary" style="margin-right:6px;pointer-events:none;">' . (int) $page_num . '</span>';
            } else {
                $url = add_query_arg(array_merge($base_args, array('paged' => $page_num)), admin_url('admin.php'));
                echo '<a class="button" style="margin-right:6px;" href="' . esc_url($url) . '">' . (int) $page_num . '</a>';
            }
            $last_printed = $page_num;
        }

        if ($current < $total) {
            $next_url = add_query_arg(array_merge($base_args, array('paged' => $current + 1)), admin_url('admin.php'));
            echo '<a class="button" style="margin-left:2px;" href="' . esc_url($next_url) . '">Next &raquo;</a>';
        }

        echo '<span style="margin-left:10px;color:#666;">Page ' . (int) $current . ' of ' . (int) $total . '</span>';
        echo '</div></div>';
    }
}
