# QA Runbook

Use this runbook to validate all acceptance criteria quickly.

## Prerequisites

1. WordPress installed and running.
2. Plugin activated.
3. WP-CLI available in the WP root.

## 1. Schema Validation

Run:

```bash
wp moda verify
```

Expected:

1. `Verification passed.`
2. Row counts are printed.

## 2. Seed Scale Data

Run:

```bash
wp moda seed --stylists=2000 --celebs=5000 --links=30000
wp moda verify --min-stylists=2000 --min-celebs=5000 --min-links=30000
```

Expected:

1. Seed command completes successfully.
2. Verify command passes minimum counts.

## 3. REST Read Endpoints

Run in browser or curl:

```text
/wp-json/moda/v1/stylists?per_page=10&page=1
/wp-json/moda/v1/stylists?q=stylist
/wp-json/moda/v1/stylists?sort=name
/wp-json/moda/v1/stylists?celebrity=1
/wp-json/moda/v1/stylists/1
```

Expected:

1. `/stylists` returns `items` and `meta`.
2. Each stylist row includes `celebrity_count`.
3. `/stylists/{id}` includes `reps` and `celebrities`.

## 4. REST Write Endpoints (Admin)

Use admin-authenticated request (cookie + nonce or application password/basic setup).

1. Create stylist:

```bash
curl -X POST "https://your-site.test/wp-json/moda/v1/stylists" \
  -H "Content-Type: application/json" \
  -d '{"full_name":"Alex Prime","email":"alex@example.com"}'
```

2. Update stylist:

```bash
curl -X PATCH "https://your-site.test/wp-json/moda/v1/stylists/1" \
  -H "Content-Type: application/json" \
  -d '{"phone":"+1-222-333-4444"}'
```

3. Attach celebrity:

```bash
curl -X POST "https://your-site.test/wp-json/moda/v1/stylists/1/celebrities" \
  -H "Content-Type: application/json" \
  -d '{"celebrity_name":"New Celebrity","notes":"Campaign"}'
```

4. Detach celebrity:

```bash
curl -X DELETE "https://your-site.test/wp-json/moda/v1/stylists/1/celebrities/1"
```

5. Add rep:

```bash
curl -X POST "https://your-site.test/wp-json/moda/v1/stylists/1/reps" \
  -H "Content-Type: application/json" \
  -d '{"rep_name":"Jordan Agent","rep_email":"jordan@example.com"}'
```

6. Delete rep:

```bash
curl -X DELETE "https://your-site.test/wp-json/moda/v1/reps/1"
```

Expected:

1. Success responses return JSON and proper status codes.
2. Invalid payloads return `400`.
3. Missing resources return `404`.

## 5. Permissions Demo

1. Repeat write calls as non-admin.
2. Expect forbidden response.
3. Confirm reads still work publicly.

## 6. Admin UI Validation

1. Open `WP Admin -> Moda Database`.
2. Search by stylist name.
3. Filter by celebrity.
4. Navigate pagination.
5. Open stylist detail.
6. Add/delete rep.
7. Attach/detach celebrity.

Expected:

1. All operations persist correctly in page reload.
2. Error/success notices appear as expected.

