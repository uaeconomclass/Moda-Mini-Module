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

## DB / Index Decisions and Rationale

### Indexes added

| Table | Index | Why |
|---|---|---|
| `moda_stylists` | `idx_full_name(full_name)` | The `q=` search parameter runs `WHERE full_name LIKE '%...%'`. While a B-tree index cannot accelerate leading-wildcard LIKE, it still helps the optimizer with short prefixes and with `ORDER BY full_name`. For true full-text at larger scale a FULLTEXT index would replace this. |
| `moda_stylists` | `idx_updated_at(updated_at)` | Default sort order for the list endpoint is `ORDER BY updated_at DESC`. This index makes the sort a simple index scan instead of a filesort. |
| `moda_celebrities` | `idx_full_name(full_name)` | Same rationale as stylists — supports name search and `ORDER BY full_name`. |
| `moda_celebrities` | `idx_industry(industry)` | Allows fast filtering if an industry filter is added later; also benefits `GROUP BY` operations on industry. |
| `moda_stylist_celebrity` | `uniq_stylist_celebrity(stylist_id, celebrity_id)` | Enforces the business rule that a stylist-celebrity pair can only exist once. Also serves as a covering index for lookups by `(stylist_id, celebrity_id)`. |
| `moda_stylist_celebrity` | `idx_celebrity_stylist(celebrity_id, stylist_id)` | The celebrity filter (`?celebrity=ID`) joins this table on `celebrity_id`. Without this index that join is a full table scan on 30 000+ rows. The composite `(celebrity_id, stylist_id)` also covers the reverse lookup direction. |
| `moda_stylist_celebrity` | `idx_stylist(stylist_id)` | Used by the `LEFT JOIN` / correlated subquery that counts celebrities per stylist. Redundant with the unique key's first column but kept explicitly because `dbDelta` does not always guarantee prefix-index usage. |
| `moda_stylist_reps` | `idx_stylist_id(stylist_id)` | Detail endpoint queries all reps for a single stylist — this index makes that a simple range scan. |
| `moda_stylist_reps` | `idx_rep_name(rep_name)` | Supports potential rep search features and `ORDER BY rep_name`. |

### Query strategy for celebrity filtering

The `GET /stylists?celebrity=` parameter accepts either a numeric celebrity ID or a celebrity name string.

- **By ID**: `INNER JOIN moda_stylist_celebrity l_filter ON l_filter.stylist_id = s.id WHERE l_filter.celebrity_id = %d`. This is a single indexed join — `idx_celebrity_stylist` makes the lookup O(log n).
- **By name**: Adds a second join to `moda_celebrities` and filters with `LIKE '%name%'`. The join chain is: `stylists → links (on stylist_id) → celebrities (on celebrity_id)`, all hitting indexes.

In both cases the join is on the filter path only. The `celebrity_count` column is computed separately via a correlated subquery `(SELECT COUNT(*) FROM links WHERE stylist_id = s.id)` rather than a LEFT JOIN, which avoids a cartesian product between the filter joins and the count join when multiple celebrities match the filter.

### N+1 and full-scan avoidance

- **List endpoint**: One query counts total rows (`COUNT(DISTINCT s.id)`), one query fetches the page with `celebrity_count` as a correlated subquery. Two SQL queries total regardless of page size.
- **Detail endpoint**: Three queries — one for the stylist row, one for all reps (`WHERE stylist_id = %d`), one for all celebrities via JOIN through the link table. Three SQL queries total, not N+1.
- **No full-table loads**: All list queries use `LIMIT %d OFFSET %d`. The `per_page` parameter is capped at 100 in both the REST controller and repository.

## Performance Approach

1. `GET /stylists` is paginated with SQL `LIMIT/OFFSET`; no full-table in-memory loads.
2. `per_page` is capped at 100 in both the REST controller and repository layer.
3. Celebrity filtering uses indexed `INNER JOIN`s (see above).
4. `celebrity_count` uses a correlated subquery instead of LEFT JOIN to prevent cross-product with filter joins.
5. Seeder uses chunked batch `INSERT` (500 rows/chunk) with `INSERT IGNORE` for link uniqueness.
6. All indexes are verified automatically via `wp moda verify`.

## Scalability and Evolution Notes

### Search Strategy at 100k+ Stylists

1. Replace broad `LIKE '%term%'` as the primary strategy with `FULLTEXT` (or prefix search where acceptable).
2. Cache frequent search queries with short TTLs to reduce repeated DB load.
3. Move deep pagination to cursor/keyset pagination to avoid large-offset degradation.
4. Roll out incrementally and validate with query plans and endpoint latency metrics.

### `celebrity_count` Strategy at Larger Scale

1. For current page sizes, the correlated subquery is acceptable and keeps query logic simple.
2. At larger scale, replace with:
   1. Aggregate join/subquery (`GROUP BY stylist_id`) for real-time counts, or
   2. A denormalized counter column maintained on attach/detach writes.
3. Choose based on profiling: start with aggregate query, denormalize only if needed.

### Capability Model (`manage_options` vs custom capability)

1. `manage_options` was chosen for MVP speed and strict admin-only write access.
2. Production recommendation: create `manage_moda` capability and assign it to a dedicated role (least privilege).
3. This decouples plugin permissions from full site-admin rights and improves operational safety.

### Schema Migrations and Versioning

1. Store a schema version in `wp_options` (e.g. `moda_schema_version`).
2. On plugin upgrade, run sequential idempotent migrations until target version is reached.
3. For larger changes, use expand/contract:
   1. Add new columns/tables first,
   2. Backfill in batches,
   3. Switch reads/writes,
   4. Remove old structures in a later release.
4. Keep migrations auditable and safe to re-run.

## What I Would Improve With More Time

1. Add automated tests (PHPUnit) for repository queries and REST route behavior.
2. Replace `LIMIT/OFFSET` with cursor-based pagination for large offsets (offset pagination degrades as page number grows).
3. Use `WP_List_Table` for admin screens — gives sorting, bulk actions, and screen options for free.
4. Add a custom capability (e.g. `manage_moda`) with a dedicated role instead of relying on `manage_options`.
5. Add FULLTEXT indexes on `full_name` columns for better partial-match search at scale.
6. Wrap seed inserts in a transaction for atomicity and slightly faster writes.
