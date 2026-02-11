# Moda Mini Module

Standalone WordPress plugin that provides custom database tables, admin screens, REST endpoints, and WP-CLI data seeding for stylist/celebrity data.

## Installation

1. Copy this plugin folder into `wp-content/plugins/moda-mini-module`.
2. Activate **Moda Mini Module** in WordPress Admin -> Plugins.
3. On activation, the plugin creates these tables (with your WP prefix):
   1. `{$prefix}moda_stylists`
   2. `{$prefix}moda_celebrities`
   3. `{$prefix}moda_stylist_celebrity`
   4. `{$prefix}moda_stylist_reps`

## Seed Data (WP-CLI)

Preferred command:

```bash
wp moda seed --stylists=2000 --celebs=5000 --links=30000
```

Defaults are `2000 / 5000 / 30000` if you omit flags.

To clear existing data before seeding:

```bash
wp moda seed --truncate
```

The seeder generates:
- Stylists with email, phone, instagram, website
- Celebrities with random industry (Music, Film/TV, Sports, Fashion)
- Stylist-celebrity links with sample notes
- 1-3 representative (rep) records per stylist with company, territory, contact info

## Verify Requirements (WP-CLI)

Quick schema/index/data verification:

```bash
wp moda verify
```

Verify with scale minimums:

```bash
wp moda verify --min-stylists=2000 --min-celebs=5000 --min-links=30000
```

Detailed verification steps:

1. `docs/REQUIREMENTS_CHECKLIST.md`
2. `docs/QA_RUNBOOK.md`

## Admin UI

Menu: `Moda Database`

1. **Stylists List**
   1. Create new stylist (collapsible form)
   2. Search by stylist name
   3. Filter by stylist ID
   4. Filter by celebrity (ID or name)
   5. Sort by table headers (ID, Name, Email, Phone, Updated, Celebrities)
   6. Pagination with total item count
   7. Click row name to open detail page
2. **Stylist Detail**
   1. Edit all stylist fields (name, email, phone, instagram, website)
   2. Lists reps with count in header
   3. Lists celebrities with count in header
   4. Add/delete rep (with delete confirmation)
   5. Attach/detach celebrity (with detach confirmation)
3. **Celebrities List**
   1. Filter by celebrity ID
   2. Search by celebrity name
   3. Sort by table headers (ID, Name, Industry, Stylist Count, Updated)
   4. Pagination with total item count
   5. Open related stylists by celebrity
4. **Celebrity Detail**
   1. Edit celebrity fields (name, industry)
   2. View linked stylists with count in header

## REST API

Namespace: `/wp-json/moda/v1/`

Read:

1. `GET /stylists?per_page=10&page=1`
2. `GET /stylists?q=alex`
3. `GET /stylists?sort=name`
4. `GET /stylists?celebrity=12` (ID filter)
5. `GET /stylists?celebrity=Taylor` (name filter)
6. `GET /stylists/1`

Write (admin only, `manage_options` capability):

1. `POST /stylists`
2. `PATCH /stylists/{id}`
3. `POST /stylists/{id}/celebrities`
4. `DELETE /stylists/{id}/celebrities/{celebrity_id}`
5. `POST /stylists/{id}/reps`
6. `DELETE /reps/{id}`

### Attach Celebrity Strategy

For `POST /stylists/{id}/celebrities`:

1. If `celebrity_id` is provided, it links to existing celebrity.
2. If `celebrity_name` is provided and no exact match exists, plugin creates a new celebrity and attaches it.

## Security Approach

1. Write endpoints use permission callback (`current_user_can('manage_options')`).
2. Input validation/sanitization:
   1. `sanitize_text_field` for names/text
   2. `sanitize_email` for emails
   3. `esc_url_raw` for website URL
3. SQL access uses `$wpdb->prepare`, `$wpdb->insert`, `$wpdb->update`, `$wpdb->delete`.
4. API errors return `WP_Error` with explicit status codes (`400`, `404`, etc.).
5. Admin forms use nonce checks (`check_admin_referer`) and capability checks.
6. Destructive admin actions (delete rep, detach celebrity) require JavaScript confirmation.

## Performance Approach

1. `GET /stylists` is paginated with SQL `LIMIT/OFFSET`; no full-table in-memory loads.
2. `per_page` is capped at 100 in both the REST controller and repository layer.
3. Celebrity filtering uses indexed `JOIN`s:
   1. By celebrity ID: join to link table and filter `celebrity_id`.
   2. By celebrity name: join link + celebrity table and filter celebrity name.
4. Avoiding N+1:
   1. List endpoint computes `celebrity_count` via a correlated subquery (no cross-join with filter joins).
   2. Detail endpoint uses one query for stylist, one for reps, one for celebrities.
5. Indexes added:
   1. `moda_stylists`: `idx_full_name`, `idx_updated_at`
   2. `moda_celebrities`: `idx_full_name`, `idx_industry`
   3. `moda_stylist_celebrity`: `uniq_stylist_celebrity(stylist_id,celebrity_id)`, `idx_celebrity_stylist(celebrity_id,stylist_id)`, `idx_stylist(stylist_id)`
   4. `moda_stylist_reps`: `idx_stylist_id`, `idx_rep_name`

## What I Would Improve With More Time

1. Add automated tests for repository + REST routes.
2. Add cursor-based pagination option for very large datasets.
3. Add proper admin list table classes (`WP_List_Table`) and bulk operations.
4. Add custom capability + roles instead of `manage_options` only.
