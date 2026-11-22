# Salkku snapshots: monthly history of the portfolio

Date: 2026-11-22
Status: Design agreed in conversation, builder check done. Awaiting approval.
Builds on: `docs/superpowers/specs/2026-11-22-salkku-design.md` (the Salkku
post type, calculator and overview page).

## Why

Salkku shows the portfolio as it is today. Sami wants to see how it develops:
value, debts, net worth, free collateral and leverage month by month. History
can't be recreated afterwards, so it has to start being recorded as early as
possible, on production, where the real data will live.

## Goals

- One snapshot per month, saved automatically on the 1st, plus a button to save
  one now.
- A snapshot freezes what Yhteenveto shows: per apartment, per owner and
  combined, calculated figures included.
- A "Kehitys" table on the Yhteenveto page showing the history.
- A read-only MCP ability that returns the history.

## Non-goals

- Charts. The data supports them later (or in the planned front-end block).
- Backfilling past months.
- Editing snapshots. A wrong snapshot is trashed in wp-admin and the month is
  saved again with the button.
- Production rollout. Sami copies the code and enters the data on production
  himself; history starts there.
- Cash flow and borrowing room (ostovara). Cash flow is the next design;
  borrowing room waits for Sami's calculation rule.

## Anti-goals

- **Leaking history.** Snapshots hold the same financial data as Salkku and
  get the same closed registration: no public page, REST route, search, feed,
  sitemap or Seravo chat. A design that works but opens any such path fails.
- **Real data in git.** Tests use invented numbers only.
- **Silently changing the past.** A stored month must show the figures as they
  were then, even after formulas or apartments change.

## Approaches

**A. Private post type, one post per month (picked by Sami).** Same closed
registration as `portfolio`; the figures as one structured meta value. Uses
WP_Query and native APIs, goes along with backups and exports, can be trashed
in wp-admin. 12 posts a year, so reading history stays cheap.

**B. Custom database table.** Rejected: built for volumes this data will never
reach; needs schema migrations and bypasses WordPress tooling.

**C. One option holding all months.** Rejected: invisible in wp-admin, hard to
correct, one ever-growing value.

## Design

### Where the code goes

- `plugins/ketunkoti-features/includes/portfolio-snapshots.php`: post type,
  building and saving a snapshot, cron schedule, the "save now" action.
- `plugins/ketunkoti-features/includes/portfolio-admin.php`: the Kehitys table
  and the button on the existing Yhteenveto page.
- `plugins/ketunkoti-ai/includes/class-portfolio-abilities.php`: the history
  ability, registered as `mcp_tool` and listed in `Registrar::NAMES`.

### Post type `portfolio_snapshot`

Registered exactly like `portfolio` in privacy terms: `public`,
`publicly_queryable`, `query_var`, `rewrite`, `has_archive`, `show_in_rest`,
`show_in_nav_menus`, `show_in_admin_bar` all false; `exclude_from_search`
true; every capability mapped to `manage_options` with `map_meta_cap` false;
`can_export` true.

- `show_ui => true`, `show_in_menu => 'edit.php?post_type=portfolio'` (listed
  under the Salkku menu as "Tilannekuvat"), `supports => [ 'title' ]`.
  Creating posts by hand is disabled (`create_posts` mapped to `do_not_allow`)
  so snapshots only come from the code.
- Title: the month, `YYYY-MM`. `post_date`: the moment it was taken (site
  timezone). `post_status`: `publish`.

### What one snapshot holds

Meta `portfolio_snapshot_month` (string `YYYY-MM`, used to find a month's
snapshot) and meta `portfolio_snapshot_data` (array, stored as PHP-serialised
post meta, `show_in_rest` false):

```
[
  'version'    => 1,                       // data shape version
  'month'      => 'YYYY-MM',
  'taken_at'   => ISO 8601 UTC,
  'trigger'    => 'cron' | 'manual',
  'apartments' => [                        // every counted and non-counted apartment
    [
      'id', 'title', 'owner', 'status',    // owner name, or "Ei omistajaa"
      'estimated_value', 'company_loan', 'bank_loan', 'used_collateral',
      'collateral_rate', 'rent', 'charges', 'debt_free_price',
      'company_loan_checked',
      'debts', 'collateral_value', 'free_collateral', 'net_value',  // calculated
      'counted' => bool,                   // false for draft/pending/future
    ],
  ],
  'groups'     => [ [ 'name', 'totals' => [ count, value, debts, net_worth,
                      free_collateral, leverage, rent, charges, debt_free_price ] ] ],
  'combined'   => totals,
]
```

- The address, area, year built and floor are **not** stored: history needs
  money figures, and less personal data in history is safer.
- Totals (`groups`, `combined`) come from `get_portfolio_overview()`, the same
  source as Yhteenveto, so a snapshot equals what the page showed at that
  moment. `get_portfolio_overview()` returns non-counted apartments (draft,
  pending, future) only as id, title and status, so the snapshot builder reads
  their fields with `get_portfolio_field_values()` and calculates them with
  `calculate_portfolio_apartment()`, the same way `get-portfolio` does. They are
  stored with `counted => false` and never feed the stored totals.
- Everything is stored, never recalculated on read.
- `post_author` is 0 for cron-made snapshots and the current user for the
  button. No revisions: replacing a month's snapshot overwrites it in place
  by design.

### Saving

- `save_portfolio_snapshot( string $trigger ): int|\WP_Error` builds the data
  and saves it for the current month (site timezone). If that month already
  has a **published** snapshot, it is **replaced** (same post updated: new
  data, new `post_date`, trigger recorded). Trashed snapshots are ignored
  everywhere (lookup, "has none" check, Kehitys, ability), so saving a month
  whose snapshot was trashed creates a new post. One snapshot per month: the
  latest save of that month. A button press on the 15th is therefore that
  month's record unless pressed again; the next month starts with the cron on
  the 1st.
- The button saves even with zero apartments (an explicit choice). The cron
  **skips** while the portfolio has no apartments at all, so deploying the
  code to production before the data is entered doesn't record an empty
  month; it simply tries again at the next run.

### Schedule

- A WP-Cron event `ketunkoti_portfolio_monthly_snapshot`. On each run it saves
  a snapshot for the current month **only if that month has none yet**, then
  schedules itself for 00:30 site time on the 1st of next month
  (`wp_schedule_single_event`). Running once a month by "first run in a new
  month" means a missed or late cron (site not visited at 00:30) still records
  the month at the next run.
- The event is scheduled on plugin activation and, if missing, on `init`
  (cheap `wp_next_scheduled` check), and cleared on deactivation.
- The first snapshot is taken the first time the event runs after the code is
  live, which is immediately when no event is scheduled yet (schedule for now),
  labelled as the current month with `trigger => 'cron'`, unless the portfolio
  is still empty (see Saving).
- Failures: if saving returns a `WP_Error` (or throws), the callback logs it
  with `error_log` (WP_DEBUG_LOG) and reschedules for one hour later instead
  of next month, so a failed month is retried, not skipped. On success, or
  when the month already has a snapshot, or when the portfolio is empty, it
  reschedules for 00:30 on the 1st of next month (empty portfolio: one day
  later, so history starts soon after the data is entered).
- The `init` fallback only checks `wp_next_scheduled()`; it doesn't try to
  repair a stale or timezone-shifted event, because the "first run in a new
  month" rule makes the exact run time uncritical.

### The "save now" button

On Yhteenveto, above the Kehitys table: "Tallenna tilannekuva nyt", a POST
form to `admin-post.php` with a nonce and a `manage_options` check, which calls
`save_portfolio_snapshot( 'manual' )` and redirects back with a success or
error notice. Under the button a line: "Kuukauden tilannekuva tallentuu
automaattisesti kuun 1. päivänä. Painike korvaa kuluvan kuukauden
tilannekuvan."

### Kehitys table

Under the summary and apartment tables on Yhteenveto, heading "Kehitys":

```
Kuukausi | Asuntoja | Asuntojen arvo | Velat | Nettovarallisuus | Vapaata vakuutta | Velkavipu
2026-12  |    n     |  x xxx xxx € (+x xxx €) | ... (change from previous month in each money column)
2026-11  | ...
```

- Combined totals only, newest month first, all months (no paging until it
  is needed).
- Each money column shows the value and, in smaller text, the change from the
  **previous snapshot in the list** (which may be more than a month earlier if
  a month is missing) with a sign; "±0 €" when unchanged; no change for the
  oldest row. Leverage change in percentage points; when either month's
  leverage is null, the value shows "–" and no change.
- With no snapshots yet: "Ei vielä tilannekuvia. Ensimmäinen tallentuu kuun
  1. päivänä, tai tallenna se nyt painikkeesta."
- Same table markup, formatting helpers and accessibility as the existing
  tables (caption, `th scope`, keyboard-scrollable region).

### Ability `ketunkoti/get-portfolio-history`

Read-only, idempotent, `mcp_tool`, permission `manage_options`. Input:
optional `from` and `to` (`YYYY-MM`), optional `owner` (name or slug; then
each month's `combined` is that owner's totals and `apartments` are filtered),
optional `include_apartments` (bool, default false, to keep responses small).
Returns months oldest first: `month`, `taken_at`, `trigger`, `combined`,
`groups`, and `apartments` when asked. No cap on the number of months.

- `owner` is matched against the owner names **stored in the snapshots** (and
  current term slugs), not only live terms, so history of a renamed or
  deleted owner stays reachable. A rename splits that owner's history across
  two names; accepted. A name found in no snapshot and no current term →
  `ketunkoti_ai_unknown_owner`. With `owner`, each month's `combined` is that
  owner's stored totals (zero totals if the owner has no group that month) and
  `groups` holds only that group.
- `from` / `to` must match `YYYY-MM`; an invalid format or `from` after `to`
  returns `ketunkoti_ai_invalid_range`.

### Tests (invented data)

- Building a snapshot equals `get_portfolio_overview()` totals; the address is
  not in the stored data; non-counted apartments are stored with
  `counted => false`.
- Saving twice in a month replaces, not duplicates; a new month creates a new
  post.
- Stored figures don't change after an apartment is edited.
- The cron callback saves only when the month has none, and reschedules for
  00:30 on the 1st of next month.
- Privacy: post type not viewable, not in `/wp/v2/types`, no REST route, not in
  the sitemap, editor can't read, `create_posts` refused for admins too.
- The save-now handler refuses a missing nonce and a non-admin.
- Kehitys HTML: empty message; changes with signs; newest first.
- Ability: permission, `from`/`to`, owner filter, `include_apartments`, MCP
  only (absent from `meta.public`).

## Decided by Sami

- Snapshots before cash flow; borrowing room later.
- Approach A: private post type, one post per month.
- Production is where history will live; the code and data go there later,
  by Sami.

## Calls made in this design (Claude)

- Cron at 00:30 site time on the 1st, "first run in a new month" logic.
- One snapshot per month; the button replaces the current month's.
- Address, area, year and floor not stored in snapshots.
- Kehitys table shows combined totals only, with month-to-month changes.
- Read-only history ability, no write ability.
- Snapshots listed under the Salkku menu, hand creation disabled.
- Non-counted apartments stored with their figures and `counted => false`.
- Cron skips while the portfolio is empty; the button doesn't.
- A failed cron save retries in an hour; trashed snapshots are ignored.
- Kehitys compares to the previous snapshot in the list; "±0 €" when equal.
- Ability owner filter uses stored owner names; invalid range is an error.

## Left to the builder

- Exact markup and CSS of the Kehitys table and the button within the sketch.
- Test file layout.
