<?php

if (!defined('ABSPATH')) {
    exit;
}

class Moda_REST_Controller {
    private Moda_Stylist_Repository $repository;

    public function __construct(Moda_Stylist_Repository $repository) {
        $this->repository = $repository;
    }

    public function register(): void {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes(): void {
        register_rest_route('moda/v1', '/stylists', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'list_stylists'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_stylist'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));

        register_rest_route('moda/v1', '/stylists/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_stylist'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => 'PATCH',
                'callback' => array($this, 'update_stylist'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));

        register_rest_route('moda/v1', '/stylists/(?P<id>\d+)/celebrities', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'attach_celebrity'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));

        register_rest_route('moda/v1', '/stylists/(?P<id>\d+)/celebrities/(?P<celebrity_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'detach_celebrity'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));

        register_rest_route('moda/v1', '/stylists/(?P<id>\d+)/reps', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_rep'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));

        register_rest_route('moda/v1', '/reps/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_rep'),
                'permission_callback' => array($this, 'can_write'),
            ),
        ));
    }

    public function can_write(): bool {
        return current_user_can('manage_options');
    }

    public function list_stylists(WP_REST_Request $request) {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0) {
            $per_page = 20;
        }

        $q = sanitize_text_field((string) $request->get_param('q'));
        $sort = sanitize_text_field((string) $request->get_param('sort'));
        $celebrity = sanitize_text_field((string) $request->get_param('celebrity'));

        if ($sort !== '' && !in_array($sort, array('name', 'updated_at'), true)) {
            return $this->error('Invalid sort. Allowed: name, updated_at.', 400);
        }
        if ($sort === '') {
            $sort = 'updated_at';
        }

        $result = $this->repository->list_stylists($page, $per_page, $q, $sort, $celebrity);
        return rest_ensure_response($result);
    }

    public function get_stylist(WP_REST_Request $request) {
        $id = (int) $request['id'];
        $stylist = $this->repository->get_stylist($id);
        if (!$stylist) {
            return $this->error('Stylist not found.', 404);
        }
        return rest_ensure_response($stylist);
    }

    public function create_stylist(WP_REST_Request $request) {
        $data = $this->build_create_stylist_payload($request);
        if (empty($data['full_name'])) {
            return $this->error('full_name is required.', 400);
        }

        $id = $this->repository->create_stylist($data);
        if ($id <= 0) {
            return $this->error('Failed to create stylist.', 400);
        }

        $stylist = $this->repository->get_stylist($id);
        return new WP_REST_Response($stylist, 201);
    }

    public function update_stylist(WP_REST_Request $request) {
        $id = (int) $request['id'];
        if (!$this->repository->stylist_exists($id)) {
            return $this->error('Stylist not found.', 404);
        }

        if ($request->get_param('full_name') !== null) {
            $name = sanitize_text_field((string) $request->get_param('full_name'));
            if ($name === '') {
                return $this->error('full_name cannot be empty.', 400);
            }
        }

        $data = $this->build_update_stylist_payload($request);

        if (empty($data)) {
            return $this->error('No valid fields to update.', 400);
        }

        $ok = $this->repository->update_stylist($id, $data);
        if (!$ok) {
            return $this->error('Failed to update stylist.', 400);
        }

        return rest_ensure_response($this->repository->get_stylist($id));
    }

    public function attach_celebrity(WP_REST_Request $request) {
        $stylist_id = (int) $request['id'];
        if (!$this->repository->stylist_exists($stylist_id)) {
            return $this->error('Stylist not found.', 404);
        }

        $celebrity_id_raw = $request->get_param('celebrity_id');
        $celebrity_name_raw = $request->get_param('celebrity_name');
        $notes = sanitize_text_field((string) $request->get_param('notes'));

        $celebrity_id = null;
        if ($celebrity_id_raw !== null && $celebrity_id_raw !== '') {
            $celebrity_id = (int) $celebrity_id_raw;
            if ($celebrity_id <= 0 || !$this->repository->celebrity_exists($celebrity_id)) {
                return $this->error('celebrity_id not found.', 404);
            }
        }

        $celebrity_name = null;
        if ($celebrity_name_raw !== null) {
            $celebrity_name = sanitize_text_field((string) $celebrity_name_raw);
        }

        $result = $this->repository->attach_celebrity_to_stylist($stylist_id, $celebrity_id, $celebrity_name, $notes);
        if (!$result['ok']) {
            return $this->error($result['error'], 400);
        }

        return new WP_REST_Response(
            array(
                'ok' => true,
                'stylist_id' => $stylist_id,
                'celebrity_id' => (int) $result['celebrity_id'],
            ),
            201
        );
    }

    public function detach_celebrity(WP_REST_Request $request) {
        $stylist_id = (int) $request['id'];
        $celebrity_id = (int) $request['celebrity_id'];

        if (!$this->repository->stylist_exists($stylist_id)) {
            return $this->error('Stylist not found.', 404);
        }
        if (!$this->repository->celebrity_exists($celebrity_id)) {
            return $this->error('Celebrity not found.', 404);
        }

        $ok = $this->repository->detach_celebrity_from_stylist($stylist_id, $celebrity_id);
        if (!$ok) {
            return $this->error('Stylist-celebrity link not found.', 404);
        }

        return rest_ensure_response(array('ok' => true));
    }

    public function add_rep(WP_REST_Request $request) {
        $stylist_id = (int) $request['id'];
        if (!$this->repository->stylist_exists($stylist_id)) {
            return $this->error('Stylist not found.', 404);
        }

        $rep_name = sanitize_text_field((string) $request->get_param('rep_name'));
        if ($rep_name === '') {
            return $this->error('rep_name is required.', 400);
        }

        $rep_id = $this->repository->add_rep($stylist_id, array(
            'rep_name' => $rep_name,
            'company' => sanitize_text_field((string) $request->get_param('company')),
            'rep_email' => sanitize_email((string) $request->get_param('rep_email')),
            'rep_phone' => sanitize_text_field((string) $request->get_param('rep_phone')),
            'territory' => sanitize_text_field((string) $request->get_param('territory')),
        ));

        if ($rep_id <= 0) {
            return $this->error('Failed to add rep.', 400);
        }

        return new WP_REST_Response(array('ok' => true, 'id' => $rep_id), 201);
    }

    public function delete_rep(WP_REST_Request $request) {
        $rep_id = (int) $request['id'];
        if ($rep_id <= 0) {
            return $this->error('Invalid rep id.', 400);
        }

        $ok = $this->repository->delete_rep($rep_id);
        if (!$ok) {
            return $this->error('Rep not found.', 404);
        }

        return rest_ensure_response(array('ok' => true));
    }

    private function build_create_stylist_payload(WP_REST_Request $request): array {
        return array(
            'full_name' => sanitize_text_field((string) $request->get_param('full_name')),
            'email' => sanitize_email((string) $request->get_param('email')),
            'phone' => sanitize_text_field((string) $request->get_param('phone')),
            'instagram' => sanitize_text_field((string) $request->get_param('instagram')),
            'website' => esc_url_raw((string) $request->get_param('website')),
        );
    }

    private function build_update_stylist_payload(WP_REST_Request $request): array {
        $allowed_fields = array('full_name', 'email', 'phone', 'instagram', 'website');
        $payload = array();

        foreach ($allowed_fields as $field) {
            if ($request->get_param($field) === null) {
                continue;
            }

            $raw = (string) $request->get_param($field);
            if ($field === 'full_name') {
                $value = sanitize_text_field($raw);
                if ($value === '') {
                    continue;
                }
                $payload[$field] = $value;
                continue;
            }

            if ($field === 'email') {
                $payload[$field] = sanitize_email($raw);
                continue;
            }

            if ($field === 'website') {
                $payload[$field] = esc_url_raw($raw);
                continue;
            }

            $payload[$field] = sanitize_text_field($raw);
        }

        return $payload;
    }

    private function error(string $message, int $status): WP_Error {
        return new WP_Error('moda_error', $message, array('status' => $status));
    }
}
