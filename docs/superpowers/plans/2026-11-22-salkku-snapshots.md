# Salkku Snapshots Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Save a frozen monthly snapshot of the Salkku portfolio (cron on the 1st plus a "save now" button), show it as a "Kehitys" table on Yhteenveto, and expose it through a read-only MCP ability.

**Architecture:** A closed post type `portfolio_snapshot` in `ketunkoti-features`, one post per month with the figures in one serialised meta value, built from `get_portfolio_overview()` and the calculator. A single-event WP-Cron that reschedules itself. `ketunkoti-ai` reads the posts for `ketunkoti/get-portfolio-history`.

**Tech Stack:** WordPress 7.1 (wp-env), PHP 8.2, WP-Cron, Abilities API, MCP Adapter 0.7.2, WP-CLI `eval-file` test harnesses.

**Spec:** `docs/superpowers/specs/2026-11-22-salkku-snapshots-design.md` (builds on `docs/superpowers/specs/2026-11-22-salkku-design.md`).

## Global Constraints

- Branch `feature/salkku` (Sami's choice). Local commits allowed, never push. Commit trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- No real portfolio figures, addresses or apartment names in code, tests, docs or commit messages. Tests use invented numbers.
- Snapshots get exactly the closed registration of `portfolio`; everything gated by `manage_options`; the ability is `mcp_tool` (never `meta.public`).
- Stored figures are never recalculated on read. Trashed snapshots are ignored everywhere.
- Finnish UI strings (`ketunkoti-features`), English ability strings (`ketunkoti-ai`), 4-space indentation, PHPDoc on every function, escape all output, WCAG 2.2 AA.
- Never query or change the dev site's data (`wp-env run cli`, localhost:8888): it holds Sami's real portfolio. Tests run on `tests-cli` only.
- Test commands from the repo root: `source ~/.nvm/nvm.sh; npx wp-env run tests-cli wp eval-file wp-content/plugins/<plugin>/tests/php/<file>.php`. Features harness prints "All checks passed."; ketunkoti-ai prints "ALL PASSED".
- Plugin versions stay 1.1.0 (features) / 1.3.0 (ai): this branch is unreleased.

## Review Focus

1. Month boundary in the site timezone (Europe/Helsinki): a save at 23:59 local on the last day belongs to that month, at 00:01 to the next, regardless of UTC. → Task 1 test with fixed timestamps.
2. Editing an apartment after a snapshot leaves the stored month unchanged. → Task 1 test.
3. The cron running repeatedly in one month saves once; a trashed snapshot lets the cron save the month again. → Task 2 test.
4. A snapshot whose stored data is missing or malformed (e.g. hand-edited meta) doesn't break Yhteenveto or the ability: the month is skipped. → Task 3 and Task 4 tests.
5. Two months with null leverage (zero value) render "–" without a change and without PHP warnings. → Task 3 test.

---

### Task 1: Snapshot post type, builder and save

**Files:**
- Create: `plugins/ketunkoti-features/includes/portfolio-snapshots.php` (require in `ketunkoti-features.php` after `portfolio-admin.php`)
- Test: `plugins/ketunkoti-features/tests/php/test-portfolio-snapshots.php` (copy the harness and teardown pattern of `test-portfolio.php`; delete its users, portfolio posts, snapshot posts and terms at the end)

**Interfaces:**
- Consumes: `get_portfolio_overview()`, `get_portfolio_counted_statuses()`, `get_portfolio_field_values()`, `calculate_portfolio_apartment()`, `PORTFOLIO_POST_TYPE`, `PORTFOLIO_OWNER_TAXONOMY`, `PORTFOLIO_NO_OWNER`.
- Produces (namespace `Ketunkoti`):
  - `const PORTFOLIO_SNAPSHOT_POST_TYPE = 'portfolio_snapshot';`, `const PORTFOLIO_SNAPSHOT_MONTH_META = 'portfolio_snapshot_month';`, `const PORTFOLIO_SNAPSHOT_DATA_META = 'portfolio_snapshot_data';`
  - `register_portfolio_snapshots(): void` on `init` (priority after `register_portfolio`): post type per spec "Post type `portfolio_snapshot`" (labels Tilannekuvat / Tilannekuva, `show_in_menu => 'edit.php?post_type=portfolio'`, `supports => [ 'title' ]`, caps → `manage_options` except `create_posts` → `do_not_allow`); both metas registered single, `show_in_rest` false, auth `manage_options`.
  - `get_portfolio_snapshot_month( int $timestamp ): string` → `wp_date( 'Y-m', $timestamp )`.
  - `build_portfolio_snapshot_data( string $trigger, int $timestamp ): array` → the spec's data shape with `version => 1`; apartments include non-counted ones with figures and `counted => false`; no address/area/year/floor keys.
  - `get_portfolio_snapshot_id( string $month ): int` → published snapshot ID for the month or 0.
  - `save_portfolio_snapshot( string $trigger, ?int $timestamp = null ): int|\WP_Error` → insert or replace (update data, `post_date` and title) the month's published snapshot via `wp_insert_post( ..., true )` / `wp_update_post( ..., true )` so failures come back as `WP_Error` (Task 2 relies on it); `post_author` = current user (0 under cron); `$trigger` must be `cron` or `manual` (else `WP_Error( 'ketunkoti_portfolio_invalid_trigger' )`).
  - `get_portfolio_snapshots( ?string $from = null, ?string $to = null ): array` → published snapshots' data arrays, oldest month first, filtered by month inclusive; entries whose data isn't an array with `month` and `combined` are skipped.

- [ ] **Step 1: Write the failing tests** (invented apartments; `$ts_nov = strtotime( '2026-11-30 21:59:00 UTC' )` is 23:59 Helsinki, `$ts_dec = strtotime( '2026-11-30 22:01:00 UTC' )` is 00:01 Helsinki on 1 Dec):

```php
check( 'post type registered', true, post_type_exists( 'portfolio_snapshot' ) );
check( 'not viewable', false, is_post_type_viewable( 'portfolio_snapshot' ) );
check( 'not in REST types', false, array_key_exists( 'portfolio_snapshot', rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/types' ) )->get_data() ) );
check( 'admin cannot create by hand', false, user_can( $admin, get_post_type_object( 'portfolio_snapshot' )->cap->create_posts ) );
check( 'editor cannot read', false, user_can( $editor, 'read_post', $any_snapshot_id ) );
check( 'month helsinki late', '2026-11', get_portfolio_snapshot_month( $ts_nov ) );
check( 'month helsinki next', '2026-12', get_portfolio_snapshot_month( $ts_dec ) );
$data = build_portfolio_snapshot_data( 'manual', $ts_nov );
check( 'combined equals overview', get_portfolio_overview()['combined'], $data['combined'] );
check( 'no address stored', false, array_key_exists( 'portfolio_address', $data['apartments'][0] ) || array_key_exists( 'address', $data['apartments'][0] ) );
check( 'draft stored not counted', false, $draft_row['counted'] );
check( 'draft has figures', true, isset( $draft_row['free_collateral'] ) );
$id1 = save_portfolio_snapshot( 'manual', $ts_nov );
$id2 = save_portfolio_snapshot( 'cron', $ts_nov );
check( 'same month replaces', $id1, $id2 );
check( 'trigger updated', 'cron', get_post_meta( $id1, 'portfolio_snapshot_data', true )['trigger'] );
$id3 = save_portfolio_snapshot( 'manual', $ts_dec );
check( 'new month new post', true, $id3 !== $id1 );
update_post_meta( $apartment_id, 'portfolio_estimated_value', 999999 );
check( 'stored month frozen', $value_before, get_portfolio_snapshots( '2026-11', '2026-11' )[0]['combined']['value'] );
wp_trash_post( $id3 );
check( 'trashed ignored by lookup', 0, get_portfolio_snapshot_id( '2026-12' ) );
check( 'trashed ignored by list', 1, count( get_portfolio_snapshots() ) );
update_post_meta( $id1, 'portfolio_snapshot_data', 'broken' );
check( 'malformed skipped', 0, count( get_portfolio_snapshots() ) );
check( 'bad trigger', 'ketunkoti_portfolio_invalid_trigger', save_portfolio_snapshot( 'nope', $ts_nov )->get_error_code() );
```

- [ ] **Step 2: Run, expect FAIL.** `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-portfolio-snapshots.php`
- [ ] **Step 3: Implement** the interfaces. The tests site timezone may not be Helsinki: set `update_option( 'timezone_string', 'Europe/Helsinki' )` in the test and restore the old value in teardown.
- [ ] **Step 4: Run, expect "All checks passed."**, then re-run `test-portfolio.php` and `test-portfolio-calculator.php`. Commit.

---

### Task 2: Monthly schedule

**Files:**
- Modify: `plugins/ketunkoti-features/includes/portfolio-snapshots.php`, `plugins/ketunkoti-features/ketunkoti-features.php` (`activate()` calls `schedule_portfolio_snapshot_event()`; `deactivate()` calls `wp_clear_scheduled_hook( PORTFOLIO_SNAPSHOT_EVENT )`)
- Test: `plugins/ketunkoti-features/tests/php/test-portfolio-snapshots.php`

**Interfaces:**
- Produces:
  - `const PORTFOLIO_SNAPSHOT_EVENT = 'ketunkoti_portfolio_monthly_snapshot';`
  - `get_next_portfolio_snapshot_time( int $now ): int` → UTC timestamp of 00:30 site time on the 1st of the month after `$now`'s site-time month.
  - `schedule_portfolio_snapshot_event(): void` → if `wp_next_scheduled()` is false, `wp_schedule_single_event( time(), PORTFOLIO_SNAPSHOT_EVENT )`; hooked on `init`.
  - `run_portfolio_snapshot_cron( ?int $now = null ): string` (returns the outcome for tests: `saved`, `exists`, `empty`, `failed`) on the event hook. Rules from the spec "Schedule": empty portfolio (no `portfolio` posts in any non-trash status) → `empty`, reschedule `$now + DAY_IN_SECONDS`; month already has a snapshot → `exists`, reschedule next 1st; save OK → `saved`, reschedule next 1st; `WP_Error` or exception → `error_log()` the message, `failed`, reschedule `$now + HOUR_IN_SECONDS`. Before scheduling, clear any other pending instance of the event so only one exists.

- [ ] **Step 1: Add failing tests**

```php
$t = get_next_portfolio_snapshot_time( strtotime( '2026-11-15 10:00:00 UTC' ) );
check( 'next is 1 dec 00:30 helsinki', '2026-12-01 00:30', wp_date( 'Y-m-d H:i', $t ) );
check( 'next from late 30 nov local', '2027-01-01 00:30', wp_date( 'Y-m-d H:i', get_next_portfolio_snapshot_time( strtotime( '2026-11-30 22:01:00 UTC' ) ) ) );
// With no portfolio posts at all:
check( 'empty skips', 'empty', run_portfolio_snapshot_cron( $now ) );
check( 'empty retries tomorrow', $now + DAY_IN_SECONDS, wp_next_scheduled( 'ketunkoti_portfolio_monthly_snapshot' ) );
// With one apartment:
check( 'first run saves', 'saved', run_portfolio_snapshot_cron( $now ) );
check( 'second run exists', 'exists', run_portfolio_snapshot_cron( $now ) );
check( 'one event only', 1, count( array_filter( _get_cron_array(), fn( $e ) => isset( $e['ketunkoti_portfolio_monthly_snapshot'] ) ) ) );
wp_trash_post( get_portfolio_snapshot_id( get_portfolio_snapshot_month( $now ) ) );
check( 'after trash saves again', 'saved', run_portfolio_snapshot_cron( $now ) );
add_filter( 'wp_insert_post_empty_content', '__return_true' ); // forces insert failure on the next month
check( 'failure retries in an hour', 'failed', run_portfolio_snapshot_cron( $next_month_ts ) );
check( 'retry scheduled', $next_month_ts + HOUR_IN_SECONDS, wp_next_scheduled( 'ketunkoti_portfolio_monthly_snapshot' ) );
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
```

- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.** Clear the event in teardown so the tests site has no leftover schedule.
- [ ] **Step 4: Run, expect "All checks passed."** Commit.

---

### Task 3: Save-now button and Kehitys table

**Files:**
- Modify: `plugins/ketunkoti-features/includes/portfolio-snapshots.php` (handler), `plugins/ketunkoti-features/includes/portfolio-admin.php` (render), `plugins/ketunkoti-features/assets/portfolio-admin.css` (change text style)
- Test: `plugins/ketunkoti-features/tests/php/test-portfolio-snapshots.php`

**Interfaces:**
- Consumes: Task 1 `save_portfolio_snapshot()`, `get_portfolio_snapshots()`; `format_portfolio_euros()`, `format_portfolio_percent()`, existing table/region markup helpers in `portfolio-admin.php`.
- Produces:
  - `handle_portfolio_snapshot_request(): void` on `admin_post_ketunkoti_portfolio_snapshot`: `check_admin_referer( 'ketunkoti_portfolio_snapshot' )`, `manage_options` check (`wp_die` otherwise), `save_portfolio_snapshot( 'manual' )`, store a per-user notice transient `ketunkoti_portfolio_snapshot_notice_{user_id}` (success "Tilannekuva tallennettu kuukaudelle YYYY-MM." or the error), `wp_safe_redirect()` to the Yhteenveto page, `exit`.
  - `get_portfolio_snapshot_form_html(): string` → the POST form (nonce, `action` field, button "Tallenna tilannekuva nyt") and the explanation line from the spec.
  - `get_portfolio_history_html( array $snapshots ): string` → heading "Kehitys", the empty message, or a table per spec "Kehitys table": newest first, columns Kuukausi, Asuntoja, Asuntojen arvo, Velat, Nettovarallisuus, Vapaata vakuutta, Velkavipu; change vs the previous snapshot in the list as `<span class="ketunkoti-portfolio-change">` with sign (`+`, U+2212, or "±0 €"), leverage change in percentage points ("+1,2 %-yks."); "–" and no change when either leverage is null; caption and keyboard-scrollable region like the other tables.
  - `get_portfolio_overview_html()` appends the notice (once, then deleted), the form and the history after the existing tables.

- [ ] **Step 1: Add failing tests**

```php
$html = get_portfolio_history_html( [] );
check( 'empty message', true, str_contains( $html, 'Ei vielä tilannekuvia.' ) );
$html = get_portfolio_history_html( [ $snap_oct, $snap_nov ] ); // invented, nov value +10000 vs oct
check( 'newest first', true, strpos( $html, '2026-11' ) < strpos( $html, '2026-10' ) );
check( 'positive change', true, str_contains( $html, "+10\u{00A0}000\u{00A0}€" ) );
check( 'unchanged shows zero', true, str_contains( $html, "±0\u{00A0}€" ) );
check( 'null leverage dash', true, str_contains( get_portfolio_history_html( [ $zero_a, $zero_b ] ), '–' ) );
check( 'caption', true, str_contains( $html, '<caption' ) );
check( 'malformed month skipped upstream', 1, count( get_portfolio_snapshots() ) ); // after corrupting one of two
$_REQUEST['_wpnonce'] = 'bad';
check( 'bad nonce refused', true, portfolio_test_dies( fn() => handle_portfolio_snapshot_request() ) );
```

(`portfolio_test_dies()`: a test helper that runs the callable with `wp_die_handler` filtered to throw, returns true when it threw.)

- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, expect "All checks passed."** and re-run `test-portfolio.php`. Commit.

---

### Task 4: History ability

**Files:**
- Modify: `plugins/ketunkoti-ai/includes/class-portfolio-abilities.php`, `plugins/ketunkoti-ai/includes/class-registrar.php` (`NAMES`), `plugins/ketunkoti-ai/README.md`
- Test: `plugins/ketunkoti-ai/tests/php/test-portfolio-history.php` (uses `bootstrap.php`; deletes created snapshot and portfolio posts)

**Interfaces:**
- Consumes: Task 1 `get_portfolio_snapshots()`, `save_portfolio_snapshot()`; `Registrar::register( ..., 'mcp_tool' )`.
- Produces: `ketunkoti/get-portfolio-history` per spec "Ability": readonly/idempotent/non-destructive, permission `manage_options`, input `{ from?: string, to?: string, owner?: string, include_apartments?: boolean }` with `pattern` `^\d{4}-(0[1-9]|1[0-2])$` on from/to, output months oldest first with `month`, `taken_at`, `trigger`, `combined`, `groups`, and `apartments` only when `include_apartments`. Errors `ketunkoti_ai_invalid_range` (from after to), `ketunkoti_ai_unknown_owner` (name in no snapshot and no current term).

- [ ] **Step 1: Write the failing test**

```php
make_user( 'editor' );
check( 'editor refused', false, wp_get_ability( 'ketunkoti/get-portfolio-history' )->check_permissions( [] ) );
make_user( 'administrator' );
// two saved months with invented data, owner "Vanha omistaja" renamed after the first
$r = wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [] );
check( 'oldest first', '2026-10', $r[0]['month'] );
check( 'no apartments by default', false, array_key_exists( 'apartments', $r[0] ) );
check( 'apartments on request', true, array_key_exists( 'apartments', wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [ 'include_apartments' => true ] )[0] ) );
check( 'range filter', 1, count( wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [ 'from' => '2026-11', 'to' => '2026-11' ] ) ) );
check( 'invalid range', 'ketunkoti_ai_invalid_range', wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [ 'from' => '2026-12', 'to' => '2026-10' ] )->get_error_code() );
check( 'renamed owner reachable', true, ! is_wp_error( wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [ 'owner' => 'Vanha omistaja' ] ) ) );
check( 'unknown owner', 'ketunkoti_ai_unknown_owner', wp_get_ability( 'ketunkoti/get-portfolio-history' )->execute( [ 'owner' => 'Ei ole' ] )->get_error_code() );
check( 'not in chat list', false, array_key_exists( 'ketunkoti/get-portfolio-history', wp_get_abilities( [ 'meta' => [ 'public' => true ] ] ) ) );
check( 'in MCP tools', true, in_array( 'ketunkoti/get-portfolio-history', Registrar::add_tools( [] )['tools'], true ) );
```

- [ ] **Step 2: Run, expect FAIL.** `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-portfolio-history.php`
- [ ] **Step 3: Implement**, README section for the ability (MCP-only).
- [ ] **Step 4: Run, expect "ALL PASSED"**, then all ketunkoti-ai suites and the three ketunkoti-features portfolio suites. Commit.

---

### Task 5: Local check (controller, no code)

- [ ] **Step 1:** `npx wp-env run cli wp cron event list --fields=hook,next_run_relative | grep ketunkoti_portfolio` → the event is scheduled on the dev site.
- [ ] **Step 2:** Ask Sami before creating a snapshot from his local data. If he agrees, he presses "Tallenna tilannekuva nyt" on Yhteenveto (or the controller runs the cron once via `wp cron event run ketunkoti_portfolio_monthly_snapshot`), then the Kehitys table shows one month whose totals equal the summary table. Visual check at 1440 and 375 px.
- [ ] **Step 3:** MCP `tools/list` via a temporary application password lists `ketunkoti-get-portfolio-history`; delete the password.

## Finalize

- Ask Sami whether to run linting (no PHPCS installed in the repo; ask whether to add `meom/phpcs-composer` or use his global setup with explicit paths `plugins/ketunkoti-features plugins/ketunkoti-ai`).
- Ask Sami whether to create a PR (GitHub, `samikeijonen/ketunkoti`). Sami commits and pushes.
