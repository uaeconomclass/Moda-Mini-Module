# Requirements Checklist

This file maps each task requirement to implementation and verification points.

## 1) Database Tables (Custom Tables)

Status: Implemented

Code:

1. `includes/class-moda-database.php`

Checks:

1. Activation hook creates:
   1. `{$prefix}moda_stylists`
   2. `{$prefix}moda_celebrities`
   3. `{$prefix}moda_stylist_celebrity`
   4. `{$prefix}moda_stylist_reps`
2. Required columns included.
3. Required unique constraint on `(stylist_id, celebrity_id)` included.
4. Indexes included for search and join/filter paths.

## 2) REST API

Status: Implemented

Code:

1. `includes/class-moda-rest-controller.php`
2. `includes/class-moda-stylist-repository.php`

Routes:

1. `GET /wp-json/moda/v1/stylists`
2. `GET /wp-json/moda/v1/stylists/{id}`
3. `POST /wp-json/moda/v1/stylists`
4. `PATCH /wp-json/moda/v1/stylists/{id}`
5. `POST /wp-json/moda/v1/stylists/{id}/celebrities`
6. `DELETE /wp-json/moda/v1/stylists/{id}/celebrities/{celebrity_id}`
7. `POST /wp-json/moda/v1/stylists/{id}/reps`
8. `DELETE /wp-json/moda/v1/reps/{id}`

Behavior checks:

1. `/stylists` supports `page`, `per_page`, `q`, `sort`, `celebrity`.
2. `/stylists` includes `celebrity_count`.
3. `/stylists/{id}` includes reps and celebrities.
4. `POST /stylists/{id}/celebrities` accepts `celebrity_id` or `celebrity_name` (create-if-missing strategy).
5. `per_page` is capped at 100.

Security checks:

1. Write endpoints require `manage_options`.
2. Inputs sanitized/validated.
3. DB writes use WPDB prepared helpers or prepared queries.
4. Error responses use explicit HTTP statuses (400/404/etc).

## 3) WordPress Admin UI

Status: Implemented

Code:

1. `includes/class-moda-admin.php`

Screens:

1. `Moda Database` stylists list:
   1. Create new stylist (collapsible form).
   2. Search by stylist name.
   3. Filter by celebrity (ID or name).
   4. Sort by column headers.
   5. Pagination with total item count.
   6. Link to detail page.
2. Stylist detail screen:
   1. Edit stylist fields (name, email, phone, instagram, website).
   2. Reps list with count in header.
   3. Celebrities list with count in header.
   4. Add/delete reps (with confirmation dialog).
   5. Attach/detach celebrities (with confirmation dialog).
3. Celebrities list:
   1. Search and filter by ID.
   2. Sort by column headers.
   3. Pagination with total item count.
   4. Link to celebrity detail and related stylists.
4. Celebrity detail:
   1. Edit celebrity fields (name, industry).
   2. Linked stylists list with count in header.

## 4) Seed Data + Performance

Status: Implemented

Code:

1. `includes/class-moda-seeder.php`
2. `includes/class-moda-stylist-repository.php`

Seeding:

1. Command: `wp moda seed --stylists=2000 --celebs=5000 --links=30000`
2. `--truncate` flag to clear existing data before seeding.
3. Uses batch inserts (chunked) for scale.
4. Link seeding uses `INSERT IGNORE` to respect unique constraint.
5. Generates 1-3 reps per stylist automatically.

Performance:

1. List endpoint is paginated with `LIMIT/OFFSET`.
2. `per_page` capped at 100 in REST controller and repository.
3. Celebrity filtering uses SQL joins, no per-row N+1 loading.
4. `celebrity_count` computed via correlated subquery (no cross-join).
5. Indexes documented in `README.md`.

## 5) Deliverables

Status: Implemented in repository

Included:

1. Plugin code (all required parts).
2. `README.md` with install/seed/API/security/performance/index rationale.
3. Verification docs:
   1. `docs/REQUIREMENTS_CHECKLIST.md`
   2. `docs/QA_RUNBOOK.md`

Not included:

1. Screen recording (must be created in runtime environment).
