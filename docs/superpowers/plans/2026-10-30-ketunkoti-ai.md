# ketunkoti-ai Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A WordPress plugin `ketunkoti-ai` that gives Claude (over MCP, through `meom-ai-assistant`) abilities to read, copy and fill in `home`/`rental` listings, with server-enforced approval for meta changes on homes Claude did not create.

**Architecture:** Small OOP plugin in namespace `Ketunkoti\AI`. One class owns the `home_*` field rules (discovery from registered meta, validation, writing); reader, copier and proposal classes use it; one abilities class registers seven abilities through a registrar that mirrors `MEOM\AI_Assistant\Abilities\Registrar::register()` but uses its own category. Ownership and the apply switch are reused from `meom-ai-assistant`.

**Tech Stack:** PHP 8.2, WordPress 7.1 Abilities API, `meom-ai-assistant` (`Ownership`, `Settings`), `ketunkoti-features` (registered meta, taxonomies, `Ketunkoti\get_*` figure functions), wp-env.

**Spec:** `docs/superpowers/specs/2026-10-30-ketunkoti-ai-design.md`

## Global Constraints

- Plugin header: `Requires at least: 7.1`, `Requires PHP: 8.2`, `Requires Plugins: meom-ai-assistant, ketunkoti-features`, `Text Domain: ketunkoti-ai`.
- Ability category `ketunkoti`; ability names exactly: `ketunkoti/get-home`, `ketunkoti/list-homes`, `ketunkoti/copy-home`, `ketunkoti/update-home-meta`, `ketunkoti/propose-home-meta`, `ketunkoti/apply-home-meta`, `ketunkoti/discard-home-meta`.
- Every ability: `meta.show_in_rest = true`, `meta.mcp.public = true`, name added to filter `meom_ai_mcp_abilities`, permission requires `\MEOM\AI_Assistant\Settings::user_can_chat()` plus `edit_post` on the target post.
- Post types: `home` and `rental` only. Editable keys: registered meta for that type whose key starts with `home_`. Calculated figures (`home_rental_yield`, `home_cash_flow`, `home_equity_return`) are never written.
- Error codes prefixed `ketunkoti_ai_`. User-facing strings translatable with domain `ketunkoti-ai`.
- Style: MEOM PHPCS, 4-space indent, short arrays, PHPDoc on every function and class (match `plugins/ketunkoti-features`).
- No commit steps: the user commits at the end themselves.
- Tests are `wp eval-file` scripts printing `PASS`/`FAIL` lines (same style as `plugins/ketunkoti-features/tests/php/test-investment.php`), run in the **tests** environment: `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/<file>.php`. Expected pass output: no `FAIL` lines and the final line `ALL PASSED`.

## Review Focus

1. A legitimate zero (`0`, `"0"`, `"0,00"` for `home_capital_charge`) must save as 0, not be refused by the "non-empty became empty" rule. → Task 1 test `zero is allowed`.
2. Copying a `rental` must not write `home`-only meta or taxonomies (`home_debt_free_price`, `home-plot`, `home-purpose`). → Task 3 test `rental copy skips home-only`.
3. After the editor saves Claude's draft by hand, `update-home-meta` must refuse and point to `propose-home-meta`. → Task 3 test `hand-saved draft refused`.
4. A proposal whose every value equals the current one must be refused (`ketunkoti_ai_no_changes`), not stored as an empty card. → Task 4 test `no changes refused`.
5. Applying the same proposal twice, or after discard, must be refused (`ketunkoti_ai_proposal_not_found`). → Task 4 tests `apply twice refused`, `apply after discard refused`.

---

### Task 1: Plugin scaffold and field rules

**Files:**
- Create: `plugins/ketunkoti-ai/ketunkoti-ai.php` (header, constants `KETUNKOTI_AI_DIR`, requires, `Plugin::init()` on `plugins_loaded`)
- Create: `plugins/ketunkoti-ai/includes/class-plugin.php`
- Create: `plugins/ketunkoti-ai/includes/class-home-fields.php`
- Create: `plugins/ketunkoti-ai/tests/php/bootstrap.php` (shared `check()`, `make_user()`, `make_home()`, `finish()`)
- Create: `plugins/ketunkoti-ai/tests/php/test-fields.php`
- Modify: `.wp-env.json` (add `"./plugins/ketunkoti-ai"` to `plugins`)

**Interfaces:**
- Produces `final class Home_Fields` (static):
  - `const POST_TYPES = [ 'home', 'rental' ];`
  - `is_home( int $post_id ): bool` — post exists and type in `POST_TYPES`.
  - `keys( string $post_type ): array` — `key => [ 'type' => string, 'label' => string, 'description' => string ]` from `get_registered_meta_keys( 'post', $post_type )`, keys starting `home_`, sorted by registration order.
  - `values( int $post_id, ?array $keys = null ): array` — `key => mixed|null` (stored single value, `null` when not set) for given keys or all keys of the type.
  - `prepare( int $post_id, array $meta ): array|\WP_Error` — `key => sanitized value|null` (`null` = delete). Uses `sanitize_meta( $key, $value, 'post', $post_type )`. Errors: `ketunkoti_ai_not_a_home`, `ketunkoti_ai_unknown_field` (message names the key and lists allowed keys), `ketunkoti_ai_invalid_value` (names field label and input). Whole call fails on the first bad key; nothing written.
  - `write( int $post_id, array $prepared ): void` — `update_post_meta` (value `wp_slash`ed) or `delete_post_meta` for `null`.
- Produces test helpers in `bootstrap.php` (namespace `Ketunkoti\AI\Tests`): `check( string $name, $expected, $actual ): void`, `make_user( string $role = 'editor' ): int` (creates and sets current user), `make_home( string $type = 'home', array $meta = [], array $terms = [] ): int` (published post), `finish(): void` (prints `ALL PASSED` or exits 1).
- `Plugin::init(): void` hooks are added in Task 2; in this task it only exists.

- [ ] **Step 1: Write failing tests in `test-fields.php`**

```php
make_user( 'editor' );
$home   = make_home( 'home', [ 'home_area' => 54.5 ] );
$rental = make_home( 'rental' );
check( 'home keys include price', true, isset( Home_Fields::keys( 'home' )['home_debt_free_price'] ) );
check( 'rental keys exclude price', false, isset( Home_Fields::keys( 'rental' )['home_debt_free_price'] ) );
check( 'rental keys include pets', true, isset( Home_Fields::keys( 'rental' )['home_pets'] ) );
check( 'computed not a key', false, isset( Home_Fields::keys( 'home' )['home_rental_yield'] ) );
check( 'not a home', 'ketunkoti_ai_not_a_home', Home_Fields::prepare( self_page(), [ 'home_area' => 1 ] )->get_error_code() );
check( 'unknown field', 'ketunkoti_ai_unknown_field', Home_Fields::prepare( $home, [ 'home_rental_yield' => 5 ] )->get_error_code() );
check( 'home-only key on rental', 'ketunkoti_ai_unknown_field', Home_Fields::prepare( $rental, [ 'home_debt_free_price' => 1 ] )->get_error_code() );
check( 'bad date refused', 'ketunkoti_ai_invalid_value', Home_Fields::prepare( $home, [ 'home_available_from' => '2026-02-31' ] )->get_error_code() );
check( 'bad year refused', 'ketunkoti_ai_invalid_value', Home_Fields::prepare( $home, [ 'home_year_built' => 'vanha' ] )->get_error_code() );
check( 'finnish number', 1285.5, Home_Fields::prepare( $home, [ 'home_maintenance_charge' => '1 285,50' ] )['home_maintenance_charge'] );
check( 'zero is allowed', 0.0, (float) Home_Fields::prepare( $home, [ 'home_capital_charge' => '0,00' ] )['home_capital_charge'] );
check( 'null deletes', [ 'home_area' => null ], Home_Fields::prepare( $home, [ 'home_area' => null ] ) );
check( 'one bad key fails all', true, is_wp_error( Home_Fields::prepare( $home, [ 'home_area' => 60, 'nope' => 1 ] ) ) );
Home_Fields::write( $home, Home_Fields::prepare( $home, [ 'home_area' => 61, 'home_floor' => '2/4' ] ) );
check( 'write then values', [ 'home_area' => '61', 'home_floor' => '2/4' ], Home_Fields::values( $home, [ 'home_area', 'home_floor' ] ) );
finish();
```

(`self_page()` = a `page` post created in the test.) Cleanup at the end: `wp_delete_post( $id, true )` for created posts and `wp_delete_user()` for the user.

- [ ] **Step 2: Map the plugin and run tests to verify they fail**

Add the plugin to `.wp-env.json`, then `npx wp-env start` and `npx wp-env run tests-cli wp plugin activate ketunkoti-ai`.
Run: `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-fields.php`
Expected: fatal error, class `Ketunkoti\AI\Home_Fields` not found.

- [ ] **Step 3: Implement the plugin file, `Plugin` and `Home_Fields`**

The refusal rule in `prepare()`: refuse when the input is not blank (`null` or trimmed `''`), is not zero-like (numeric `0` after removing spaces and turning `,` into `.`), and the sanitized value is empty (`''`, `0`, `0.0`). Blank input → `null` (delete).

- [ ] **Step 4: Run tests to verify they pass**

Run: same command as Step 2. Expected: no `FAIL`, last line `ALL PASSED`.

---

### Task 2: Registrar and read abilities

**Files:**
- Create: `plugins/ketunkoti-ai/includes/class-registrar.php`
- Create: `plugins/ketunkoti-ai/includes/class-home-reader.php`
- Create: `plugins/ketunkoti-ai/includes/class-home-abilities.php`
- Modify: `plugins/ketunkoti-ai/includes/class-plugin.php` (hooks)
- Test: `plugins/ketunkoti-ai/tests/php/test-read.php`

**Interfaces:**
- Consumes: `Home_Fields::keys()`, `Home_Fields::values()`, `Home_Fields::is_home()`.
- Produces `final class Registrar`:
  - `const CATEGORY = 'ketunkoti';`
  - `const NAMES = [ /* the seven names from Global Constraints */ ];`
  - `register_category(): void` (hook `wp_abilities_api_categories_init`).
  - `register( string $name, array $args ): void` — same behaviour as `MEOM\AI_Assistant\Abilities\Registrar::register()` (read it: default empty object schema, `default => []` on object schemas, wraps `permission_callback` with `Settings::user_can_chat()`, `annotations` default `[ 'readonly' => true ]`, sets `meta.show_in_rest` and `meta.mcp.public`) but with `category = self::CATEGORY`.
  - `mcp_names( array $names ): array` — appends `NAMES` (filter `meom_ai_mcp_abilities`).
- Produces `final class Home_Reader`:
  - `get( int $post_id ): array|\WP_Error` — keys: `id`, `post_type`, `status`, `title`, `excerpt`, `edit_link`, `featured_media` (`null` or `[ 'id', 'url', 'alt' ]`), `fields` (`key => [ 'label', 'type', 'description', 'value' ]`), `terms` (`taxonomy => [ [ 'id', 'name' ], … ]` for every taxonomy of the type), `calculated` (`[ 'home_rental_yield' => ?string, 'home_cash_flow' => ?string, 'home_equity_return' => ?string ]` from `\Ketunkoti\get_rental_yield()` etc., `home` only, `null` for `rental`), `ai_owned` (`Ownership::is_ai_owned( $id, get_current_user_id() )`).
  - `list( array $args ): array` — args `post_type` (`home`|`rental`|omitted = both), `status` (default `any` except trash), `search`, `limit` (default 20, max 100), `offset`; returns `[ 'total' => int, 'items' => [ [ 'id', 'post_type', 'title', 'status', 'city', 'rooms' ] ] ]` (city/rooms = first term name or `null`).
- Produces `final class Home_Abilities::register(): void` (hook `wp_abilities_api_init`) registering `get-home` (input `id` integer required; permission `edit_post`) and `list-homes` (permission `edit_posts`). Later tasks add the rest to this class.

- [ ] **Step 1: Write failing tests in `test-read.php`**

```php
$user = make_user( 'editor' );
$home = make_home( 'home', [ 'home_area' => 54.5, 'home_rent' => 780 ], [ 'home-city' => [ 'Lahti' ] ] );
$res  = wp_get_ability( 'ketunkoti/get-home' )->execute( [ 'id' => $home ] );
check( 'get-home area', '54.5', $res['fields']['home_area']['value'] );
check( 'get-home label', 'Asuinpinta-ala', $res['fields']['home_area']['label'] );
check( 'get-home city', 'Lahti', $res['terms']['home-city'][0]['name'] );
check( 'get-home calculated keys', [ 'home_rental_yield', 'home_cash_flow', 'home_equity_return' ], array_keys( $res['calculated'] ) );
check( 'get-home not owned', false, $res['ai_owned'] );
check( 'get-home page refused', true, is_wp_error( wp_get_ability( 'ketunkoti/get-home' )->execute( [ 'id' => self_page() ] ) ) );
$list = wp_get_ability( 'ketunkoti/list-homes' )->execute( [ 'search' => get_the_title( $home ) ] );
check( 'list finds home', $home, $list['items'][0]['id'] );
check( 'list city', 'Lahti', $list['items'][0]['city'] );
check( 'mcp filter has names', true, in_array( 'ketunkoti/apply-home-meta', apply_filters( 'meom_ai_mcp_abilities', [] ), true ) );
check( 'mcp public', true, wp_get_ability( 'ketunkoti/get-home' )->get_meta()['mcp']['public'] );
make_user( 'subscriber' );
check( 'subscriber refused', true, is_wp_error( wp_get_ability( 'ketunkoti/get-home' )->execute( [ 'id' => $home ] ) ) );
finish();
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-read.php`
Expected: fatal error on `->execute()` of null (ability not registered).

- [ ] **Step 3: Implement `Registrar`, `Home_Reader`, `Home_Abilities` (read part) and the hooks in `Plugin::init()`**

- [ ] **Step 4: Run tests to verify they pass** (same command; `ALL PASSED`). Re-run `test-fields.php` too.

---

### Task 3: Copy a home and write meta on Claude's drafts

**Files:**
- Create: `plugins/ketunkoti-ai/includes/class-home-copier.php`
- Modify: `plugins/ketunkoti-ai/includes/class-home-abilities.php` (add `copy-home`, `update-home-meta`)
- Test: `plugins/ketunkoti-ai/tests/php/test-copy.php`

**Interfaces:**
- Consumes: `Home_Fields::*`, `Registrar::register()`, `\MEOM\AI_Assistant\Ownership::mark()/record_write()/is_ai_owned()`.
- Produces `final class Home_Copier::copy( int $source_id, int $user_id, ?string $title = null ): int|\WP_Error` — new draft, same type, author `$user_id`; copies content, excerpt, menu order, `_wp_page_template`, featured image, every `Home_Fields::keys()` value, and terms of every taxonomy of the type (by term ID). Default title: source title + `' (kopio)'`. Does not copy any other meta. Calls `Ownership::mark( $new_id, $user_id )` **last**, after all writes. Errors: `ketunkoti_ai_not_a_home`, insert failure passed through.
- `copy-home` ability: input `id` (required), `title` (optional string); permission `edit_post` on source and `edit_posts`; annotations `[ 'readonly' => false, 'destructive' => false ]`; returns `[ 'id', 'edit_link', 'home' => Home_Reader::get( $new_id ) ]`.
- `update-home-meta` ability: input `id`, `meta` (object, values string|number|integer|null); permission `edit_post`; refuses with `ketunkoti_ai_not_ai_owned` (message: use `ketunkoti/propose-home-meta`) unless `Ownership::is_ai_owned( $id, get_current_user_id() )`; then `Home_Fields::prepare()` → `write()` → `Ownership::record_write( $id )`; returns `[ 'id', 'fields' => Home_Fields::values( $id, array_keys( $meta ) ) ]`.

- [ ] **Step 1: Write failing tests in `test-copy.php`**

```php
$user   = make_user( 'editor' );
$source = make_home( 'home', [ 'home_area' => 54.5, 'home_debt_free_price' => 138725, 'home_floor' => '3/5' ],
    [ 'home-city' => [ 'Lahti' ], 'home-rooms' => [ '2h' ], 'home-plot' => [ 'Oma tontti' ] ] );
update_post_meta( $source, '_genesis_title', 'SEO title' ); // TSF meta must not be copied.
$copy = wp_get_ability( 'ketunkoti/copy-home' )->execute( [ 'id' => $source ] );
$id   = $copy['id'];
check( 'copy is draft', 'draft', get_post_status( $id ) );
check( 'copy title', get_the_title( $source ) . ' (kopio)', get_the_title( $id ) );
check( 'copy content', get_post( $source )->post_content, get_post( $id )->post_content );
check( 'copy every field', Home_Fields::values( $source ), Home_Fields::values( $id ) );
foreach ( get_object_taxonomies( 'home' ) as $tax ) {
    check( "copy terms $tax", wp_get_object_terms( $source, $tax, [ 'fields' => 'ids' ] ), wp_get_object_terms( $id, $tax, [ 'fields' => 'ids' ] ) );
}
check( 'seo meta not copied', '', get_post_meta( $id, '_genesis_title', true ) );
check( 'copy is ai owned', true, Ownership::is_ai_owned( $id, $user ) );
$rental      = make_home( 'rental', [ 'home_rent' => 700, 'home_pets' => 'Sovittavissa' ], [ 'home-city' => [ 'Espoo' ] ] );
$rental_copy = wp_get_ability( 'ketunkoti/copy-home' )->execute( [ 'id' => $rental, 'title' => 'Uusi vuokrakoti' ] )['id'];
check( 'rental copy title', 'Uusi vuokrakoti', get_the_title( $rental_copy ) );
check( 'rental copy skips home-only', '', get_post_meta( $rental_copy, 'home_debt_free_price', true ) );
$res = wp_get_ability( 'ketunkoti/update-home-meta' )->execute( [ 'id' => $id, 'meta' => [ 'home_floor' => '1/3', 'home_area' => null ] ] );
check( 'update writes', '1/3', get_post_meta( $id, 'home_floor', true ) );
check( 'update deletes', null, $res['fields']['home_area'] );
check( 'still ai owned', true, Ownership::is_ai_owned( $id, $user ) );
check( 'published refused', 'ketunkoti_ai_not_ai_owned', wp_get_ability( 'ketunkoti/update-home-meta' )->execute( [ 'id' => $source, 'meta' => [ 'home_floor' => '1/3' ] ] )->get_error_code() );
wp_update_post( [ 'ID' => $id, 'post_content' => 'Käsin muokattu' ] ); // Editor saves by hand.
check( 'hand-saved draft refused', 'ketunkoti_ai_not_ai_owned', wp_get_ability( 'ketunkoti/update-home-meta' )->execute( [ 'id' => $id, 'meta' => [ 'home_floor' => '2/3' ] ] )->get_error_code() );
finish();
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-copy.php`
Expected: fatal error, ability `ketunkoti/copy-home` not registered.

- [ ] **Step 3: Implement `Home_Copier` and the two abilities**

- [ ] **Step 4: Run tests to verify they pass** (`ALL PASSED`); re-run Tasks 1–2 tests.

---

### Task 4: Proposals for homes Claude did not create, and README

**Files:**
- Create: `plugins/ketunkoti-ai/includes/class-meta-proposals.php`
- Modify: `plugins/ketunkoti-ai/includes/class-home-abilities.php` (add `propose-home-meta`, `apply-home-meta`, `discard-home-meta`)
- Create: `plugins/ketunkoti-ai/README.md`
- Test: `plugins/ketunkoti-ai/tests/php/test-proposals.php`

**Interfaces:**
- Consumes: `Home_Fields::*`, `Registrar::register()`, `\MEOM\AI_Assistant\Settings::mcp_apply_allowed( int $user_id ): bool`.
- Produces `final class Meta_Proposals` (no MCP-specific code, so it can move into `meom-ai-assistant` later):
  - `const TTL = 30 * MINUTE_IN_SECONDS;` `const LOG_META = '_ketunkoti_ai_meta_log';`
  - Storage: pending proposal in transient `ketunkoti_ai_proposal_{id}` (id = `wp_generate_uuid4()`), value `[ 'id', 'post_id', 'user_id', 'created' => time(), 'before' => key=>value|null, 'after' => key=>value|null ]`, expiry `TTL`. Applied: append `[ 'proposal_id', 'user_id', 'applied' => time(), 'before', 'after' ]` to post meta `LOG_META` (non-single, one row per apply) and delete the transient. Discard: delete the transient.
  - `propose( int $post_id, int $user_id, array $meta ): array|\WP_Error` — refuses `ketunkoti_ai_apply_disabled` when `! Settings::mcp_apply_allowed( $user_id )` (message tells the user to turn on **Allow applying changes from Claude on my computer** in their profile); `Home_Fields::prepare()`; drops keys whose prepared value equals the current value (compare as strings, `null` = not set); none left → `ketunkoti_ai_no_changes`. Returns `[ 'proposal_id', 'post_id', 'title', 'expires' => ISO 8601 UTC, 'changes' => [ [ 'key', 'label', 'before', 'after' ] ] ]`.
  - `apply( string $proposal_id, int $user_id ): array|\WP_Error` — refusals in this order: missing/expired transient or `created` older than `TTL` → `ketunkoti_ai_proposal_not_found`; other user → `ketunkoti_ai_not_your_proposal`; switch off → `ketunkoti_ai_apply_disabled`; `! user_can( $user_id, 'edit_post', $post_id )` → `ketunkoti_ai_forbidden`; any key's current value differs from `before` → `ketunkoti_ai_stale` (names the fields). Then `Home_Fields::write()`. Returns `[ 'post_id', 'applied' => key=>value ]`.
  - `discard( string $proposal_id, int $user_id ): array|\WP_Error` — same not-found / other-user refusals; returns `[ 'discarded' => $proposal_id ]`.
- Abilities: `propose-home-meta` (input `id`, `meta`; permission `edit_post`; annotations `readonly => false, destructive => false`), `apply-home-meta` and `discard-home-meta` (input `proposal_id` string; permission = `Settings::user_can_chat()` only, the class checks the rest; annotations `readonly => false`, apply `destructive => true`). Each ability's `description` tells Claude: show the `changes` list to the user and call apply only after the user says yes.

- [ ] **Step 1: Write failing tests in `test-proposals.php`**

```php
$user = make_user( 'editor' );
$home = make_home( 'home', [ 'home_debt_free_price' => 138725, 'home_floor' => '3/5' ] );
$propose = fn( $meta ) => wp_get_ability( 'ketunkoti/propose-home-meta' )->execute( [ 'id' => $home, 'meta' => $meta ] );
$apply   = fn( $pid ) => wp_get_ability( 'ketunkoti/apply-home-meta' )->execute( [ 'proposal_id' => $pid ] );
check( 'switch off refused', 'ketunkoti_ai_apply_disabled', $propose( [ 'home_floor' => '4/5' ] )->get_error_code() );
update_user_meta( $user, 'meom_ai_allow_mcp_apply', '1' );
$p = $propose( [ 'home_debt_free_price' => 135000, 'home_floor' => '3/5' ] );
check( 'unchanged field left out', [ 'home_debt_free_price' ], wp_list_pluck( $p['changes'], 'key' ) );
check( 'before value', '138725', $p['changes'][0]['before'] );
check( 'propose writes nothing', '138725', get_post_meta( $home, 'home_debt_free_price', true ) );
check( 'no changes refused', 'ketunkoti_ai_no_changes', $propose( [ 'home_floor' => '3/5' ] )->get_error_code() );
check( 'apply ok', '135000', (string) $apply( $p['proposal_id'] )['applied']['home_debt_free_price'] );
check( 'applied', '135000', get_post_meta( $home, 'home_debt_free_price', true ) );
check( 'log kept', '138725', get_post_meta( $home, Meta_Proposals::LOG_META )[0]['before']['home_debt_free_price'] );
check( 'apply twice refused', 'ketunkoti_ai_proposal_not_found', $apply( $p['proposal_id'] )->get_error_code() );
$stale = $propose( [ 'home_floor' => '4/5' ] );
update_post_meta( $home, 'home_floor', '5/5' ); // Someone edits in the editor meanwhile.
check( 'stale refused', 'ketunkoti_ai_stale', $apply( $stale['proposal_id'] )->get_error_code() );
$d = $propose( [ 'home_floor' => '1/5' ] );
wp_get_ability( 'ketunkoti/discard-home-meta' )->execute( [ 'proposal_id' => $d['proposal_id'] ] );
check( 'apply after discard refused', 'ketunkoti_ai_proposal_not_found', $apply( $d['proposal_id'] )->get_error_code() );
$old = $propose( [ 'home_floor' => '1/5' ] );
$row = get_transient( 'ketunkoti_ai_proposal_' . $old['proposal_id'] );
$row['created'] -= Meta_Proposals::TTL + 1;
set_transient( 'ketunkoti_ai_proposal_' . $old['proposal_id'], $row, Meta_Proposals::TTL );
check( 'expired refused', 'ketunkoti_ai_proposal_not_found', $apply( $old['proposal_id'] )->get_error_code() );
$mine = $propose( [ 'home_floor' => '2/5' ] );
$other = make_user( 'editor' );
update_user_meta( $other, 'meom_ai_allow_mcp_apply', '1' );
check( 'other user refused', 'ketunkoti_ai_not_your_proposal', $apply( $mine['proposal_id'] )->get_error_code() );
wp_set_current_user( $user );
update_user_meta( $user, 'meom_ai_allow_mcp_apply', '0' );
check( 'switch off at apply', 'ketunkoti_ai_apply_disabled', $apply( $mine['proposal_id'] )->get_error_code() );
finish();
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-proposals.php`
Expected: fatal error, ability `ketunkoti/propose-home-meta` not registered.

- [ ] **Step 3: Implement `Meta_Proposals` and the three abilities**

- [ ] **Step 4: Run tests to verify they pass** (`ALL PASSED`); re-run all four test files.

- [ ] **Step 5: Write `README.md`**

Sections: what it is (add-on for `meom-ai-assistant`), requirements, the seven abilities with inputs and one-line outputs, the typical flow (list → copy → update meta → `meom-ai/update-ai-draft` for content and `meom-ai/upload-media` for images → editor publishes), the approval flow for existing homes (propose → user says yes → apply; refusals; the profile switch), where applied changes are logged (`_ketunkoti_ai_meta_log`), how to run the tests.

- [ ] **Step 6: Smoke check on the dev site**

Run: `npx wp-env run cli wp eval 'wp_set_current_user(1); print_r( wp_get_ability( "ketunkoti/list-homes" )->execute( [] )["total"] );'`
Expected: `13` (6 homes + 7 rentals currently published), no PHP warnings.
