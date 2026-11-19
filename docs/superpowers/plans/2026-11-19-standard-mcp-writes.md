# Standard MCP Writes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Seravo AI Assistant and Ketun koti AI expose reads and writes over the default MCP endpoint and in the Seravo chat using only Abilities API annotations, core capabilities and MCP client approval; `ketunkoti-ai` no longer needs any assistant plugin.

**Architecture:** Each plugin builds ability meta in one place, where `mcp.public` follows `annotations.readonly`, and lists all its own abilities as individual tools on the MCP Adapter's default server through `mcp_adapter_default_server_config`. Writes therefore run only as their own MCP tool (client asks per tool) and never through `mcp-adapter-execute-ability`. `ketunkoti-ai` drops MEOM ownership and its propose/apply flow; its writes use core capabilities and `wp_check_post_lock()`.

**Tech Stack:** WordPress 7.1.5 (wp-env), PHP 8.2, Abilities API, MCP Adapter v0.7.1, WP-CLI `eval-file` test harnesses (one per plugin).

**Spec:** `docs/superpowers/specs/2026-11-19-standard-mcp-writes-design.md`

## Global Constraints

- Annotation rules: `readonly` true only when nothing stored on the site changes (calling an AI model or writing the object cache does not count); `destructive` true when existing data can be overwritten, changed or removed; `idempotent` true when repeating the same call has no additional effect (reads always true, updates/trash true, creates false). Every ability states all three.
- Meta rule (both plugins): `public` = true, `show_in_rest` = true, `mcp.public` = (`annotations.readonly === true`), `mcp.type` = `tool`.
- Seravo has no site-specific knowledge: no code, filter, default, test, doc or description in `seravo-ai-assistant` mentions homes, rentals, `home_*` or Ketun koti. Its tests use their own throwaway post type.
- `ketunkoti-ai` must work with no assistant plugin active. `Requires Plugins: ketunkoti-features` only.
- Seravo PHP: tabs, `declare( strict_types=1 );`, namespace `Seravo_AI_Assistant`, text domain `seravo-ai-assistant`. `ketunkoti-ai` PHP: 4 spaces, short arrays, namespace `Ketunkoti\AI`, text domain `ketunkoti-ai`.
- PHPDoc on every function and class. Code comments in English.
- Node commands: `source ~/.nvm/nvm.sh && nvm use 22` first. wp-env commands from `/Users/samikeijonen/projects/ketunkoti`.
- **No commits.** Sami commits at the end. Seravo is its own repo (`plugins/seravo-ai-assistant`, branch `main`); everything else is the ketunkoti repo (branch `feature/seravo-ai-assistant`).
- Do not change `meom-ai-assistant` or `meom-ai-translate`.

## Review Focus

1. A user who can edit only their own homes (Author role) calls `ketunkoti/update-home-meta` or `ketunkoti/trash-home` on another author's published home: must be refused, nothing written, no log row. Test in Task 6.
2. The current user has the post open in their own editor (their own `_edit_lock`): writes must proceed; only another user's lock refuses. Tests in Tasks 4 and 6.
3. A custom post type that is public but `show_in_rest` false, or not public: must not become editable/creatable through Seravo's new default. Test in Task 4.
4. An administrator calls a write through `mcp-adapter-execute-ability`: must be refused even though the admin may run the ability as its own tool. Test in Task 3 (exposure check) and Task 7 (live).
5. `ketunkoti/update-home-meta` with one invalid field among valid ones: nothing written, no log row. Test in Task 6.

---

### Task 1: Local env without MEOM, Seravo tests runnable in wp-env

**Files:**
- Modify: `.wp-env.json` (ketunkoti repo)
- Modify: `plugins/seravo-ai-assistant/tools/tests/run.sh`

**Interfaces:**
- Produces: `plugins/seravo-ai-assistant/tools/tests/run.sh [filter]` runs the Seravo suite in kala-stack (unchanged) or, when no `docker-compose.yml` is found but a `.wp-env.json` is, with `npx wp-env run tests-cli wp eval-file wp-content/plugins/$slug/tools/tests/design-abilities.php [filter]` from the directory holding `.wp-env.json`.

- [ ] **Step 1: Remove `"./plugins/meom-ai-assistant"` and `"./plugins/meom-ai-translate"` from `plugins` in `.wp-env.json`.** Keep their `.gitignore` lines.
- [ ] **Step 2: Restart and confirm.** Run `npx wp-env start` then `npx wp-env run tests-cli wp plugin list --fields=name,status`. Expected: no `meom-ai-*` rows; `seravo-ai-assistant`, `ketunkoti-ai`, `ketunkoti-features`, `mcp-adapter` active.
- [ ] **Step 3: Extend `run.sh`.** Walk up for `docker-compose.yml` as today; if none, walk up for `.wp-env.json` and run the wp-env command above from that directory (sourcing nvm, `nvm use 22`). Error out if neither is found.
- [ ] **Step 4: Baseline.** Run `plugins/seravo-ai-assistant/tools/tests/run.sh`. Expected: `310 tests, 2 failures`, the two being `agent defaults: content query and design abilities are available` and `tool declarations: top-level oneOf schemas are flattened into one object` (both need core 7.2's `core/content-query`; out of scope, leave them). Record the line for later tasks.
- [ ] **Step 5: Ketun koti AI baseline.** Run `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-fields.php`. Expected: `ALL PASSED` (this file uses no MEOM code). Do not run the other ketunkoti-ai tests yet: with MEOM gone, `Plugin::init()` registers nothing until Task 5.

### Task 2: Seravo meta rule and annotations

**Files:**
- Modify: `plugins/seravo-ai-assistant/includes/class-abilities.php` (`meta()`, lines ~55-76)
- Modify: `plugins/seravo-ai-assistant/includes/class-guidelines-abilities.php:192` (update-guidelines annotations)
- Modify: `plugins/seravo-ai-assistant/includes/class-glossary-abilities.php:120` (upsert-glossary-term annotations)
- Modify: `plugins/seravo-ai-assistant/includes/class-translation-abilities.php` (translate-text annotations, the `Abilities::meta()` call around line 141 or 194; find the one under `seravo-ai-assistant/translate-text`)
- Modify tests: `tools/tests/test-ability-exposure.php`, `test-guidelines-abilities.php:22-25`, `test-translate-text-ability.php:44-45`, `test-glossary-abilities.php:37-39`

**Interfaces:**
- Produces: `Abilities::meta( array $annotations ): array` returns `public` true, `mcp` => `public` (= `true === ( $annotations['readonly'] ?? null )`), `type` `tool`, `annotations` as given. `show_in_rest` is not set here: core derives it from `public` (the existing exposure test already passes on it), so leave that as is.
- The target annotation table lives only in the test, not in a constant.

- [ ] **Step 1: Write the failing test** in `test-ability-exposure.php`, replacing the first test:

```php
test(
	'ability exposure: annotations match the design table and MCP follows readonly',
	static function (): void {
		$expected = array(
			'get-site-overview'     => array( true, false, true ),
			'get-post'              => array( true, false, true ),
			'update-post'           => array( false, true, true ),
			'create-post'           => array( false, false, false ),
			'get-guidelines'        => array( true, false, true ),
			'update-guidelines'     => array( false, true, true ),
			'get-glossary'          => array( true, false, true ),
			'upsert-glossary-term'  => array( false, true, true ),
			'delete-glossary-term'  => array( false, true, true ),
			'translate-text'        => array( true, false, true ),
			'translate-post'        => array( false, false, false ),
			'get-translation-job'   => array( true, false, true ),
			'get-post-translations' => array( true, false, true ),
			'get-design-tokens'     => array( true, false, true ),
			'list-patterns'         => array( true, false, true ),
			'get-pattern'           => array( true, false, true ),
			'get-block-types'       => array( true, false, true ),
			'search-media'          => array( true, false, true ),
		);
		$abilities = wp_get_abilities( array( 'category' => Abilities::CATEGORY ) );
		assert_same( count( $expected ), count( $abilities ), 'Ability count:' );

		foreach ( $expected as $short => list( $readonly, $destructive, $idempotent ) ) {
			$meta = wp_get_ability( "seravo-ai-assistant/{$short}" )->get_meta();
			assert_same( $readonly, $meta['annotations']['readonly'] ?? null, "{$short} readonly:" );
			assert_same( $destructive, $meta['annotations']['destructive'] ?? null, "{$short} destructive:" );
			assert_same( $idempotent, $meta['annotations']['idempotent'] ?? null, "{$short} idempotent:" );
			assert_same( true, $meta['public'] ?? null, "{$short} public:" );
			assert_same( true, $meta['show_in_rest'] ?? null, "{$short} show_in_rest:" );
			assert_same( $readonly, $meta['mcp']['public'] ?? null, "{$short} mcp.public:" );
		}
	}
);
```

- [ ] **Step 2: Run it.** `tools/tests/run.sh "ability exposure"`. Expected: FAIL on `update-guidelines destructive`, `upsert-glossary-term destructive`, `translate-text readonly`, and `update-post mcp.public`.
- [ ] **Step 3: Implement.** `meta()` derives `mcp.public` from `readonly`; set the three annotation rows from the table (update-guidelines `false/true/true`, upsert-glossary-term `false/true/true`, translate-text `true/false/true`). Update the docblock of `meta()`: writes are kept off the generic MCP tools so they run only as their own tool (see class `MCP`, Task 3).
- [ ] **Step 4: Fix the older assertions that encode the old values:** `test-guidelines-abilities.php` (update-guidelines destructive → true, its MCP assertion → false), `test-translate-text-ability.php` (readonly → true, destructive stays false), `test-glossary-abilities.php` (add `upsert ... destructive` true), and `bootstrap.php:assert_public_readonly()` keeps asserting `mcp.public` true (reads only, still correct). `test-translate-post-ability.php:25` asserts get-translation-job MCP true (read, still correct); if it asserts translate-post MCP true anywhere, change to false.
- [ ] **Step 5: Run the suite.** `tools/tests/run.sh`. Expected: only the 2 baseline failures from Task 1.
- [ ] **Step 6: Writer separation audit (spec §3).** Read the execute callbacks of the six writes (`update_post`, `create_post`, `update_guidelines`, `upsert_term`, `delete_term`, `translate_post`). Each must take `$input` and return `array|WP_Error` with no reference to `Agent`, `REST_Controller`, `Conversation_Repository` or MCP. Run `grep -nE "Agent|REST_Controller|Conversation_Repository|mcp" includes/class-abilities.php includes/class-guidelines-abilities.php includes/class-glossary-abilities.php includes/class-translation-abilities.php` and confirm no hit is inside those methods. If one is, move that code to the caller; otherwise change nothing.

### Task 3: Seravo lists its abilities as MCP tools

**Files:**
- Create: `plugins/seravo-ai-assistant/includes/class-mcp.php`
- Modify: `plugins/seravo-ai-assistant/seravo-ai-assistant.php` (require + `( new MCP() )->register_hooks();` in `bootstrap()`, after `Abilities`)
- Create: `plugins/seravo-ai-assistant/tools/tests/test-mcp.php`
- Modify: `plugins/seravo-ai-assistant/readme.md` (add a short "MCP" section)

**Interfaces:**
- Consumes: `Abilities::CATEGORY`, the meta rule from Task 2.
- Produces: `final class MCP` with `register_hooks(): void` (adds `mcp_adapter_default_server_config` → `add_tools`) and `public static function add_tools( $config ): array`.

- [ ] **Step 1: Write the failing tests** in `test-mcp.php`:

```php
test(
	'mcp: every plugin ability is added as a tool, existing tools kept, no duplicates',
	static function (): void {
		$names  = array_keys( wp_get_abilities( array( 'category' => Abilities::CATEGORY ) ) );
		$config = MCP::add_tools( array( 'tools' => array( 'other/tool', 'seravo-ai-assistant/get-post' ) ) );

		assert_same( 'other/tool', $config['tools'][0], 'Existing tool first:' );
		foreach ( $names as $name ) {
			assert_true( in_array( $name, $config['tools'], true ), "{$name} listed." );
		}
		assert_same( count( $config['tools'] ), count( array_unique( $config['tools'] ) ), 'No duplicates:' );
	}
);

test(
	'mcp: a config without a tools array still gets the tools',
	static function (): void {
		$config = MCP::add_tools( array( 'server_name' => 'x' ) );
		assert_same( 'x', $config['server_name'], 'Other keys kept:' );
		assert_true( in_array( 'seravo-ai-assistant/update-post', $config['tools'], true ), 'Write listed.' );
	}
);

test(
	'mcp: generic MCP tools can run reads but not writes',
	static function (): void {
		if ( ! class_exists( \WP\MCP\Abilities\McpAbilityExposure::class ) ) {
			throw new Skipped( 'MCP Adapter is not active.' );
		}
		assert_same( true, \WP\MCP\Abilities\McpAbilityExposure::is_public( wp_get_ability( 'seravo-ai-assistant/get-post' ) ), 'Read:' );
		assert_same( false, \WP\MCP\Abilities\McpAbilityExposure::is_public( wp_get_ability( 'seravo-ai-assistant/update-post' ) ), 'Write:' );
	}
);
```

- [ ] **Step 2: Run.** `tools/tests/run.sh mcp`. Expected: FAIL, `Class "Seravo_AI_Assistant\MCP" not found`.
- [ ] **Step 3: Implement `class-mcp.php`.** `add_tools()` takes whatever `$config['tools']` is (non-array → `array()`), appends `array_keys( wp_get_abilities( array( 'category' => Abilities::CATEGORY ) ) )`, `array_values( array_unique( ... ) )`, returns the config. Class docblock: why every ability is its own tool and why writes are not MCP-public (client approval per write; `execute-ability` cannot reach writes). It touches no other plugin's abilities.
- [ ] **Step 4: Run.** `tools/tests/run.sh`. Expected: only the 2 baseline failures.
- [ ] **Step 5: readme.md.** Add an "MCP" section: every ability is its own tool on `/wp-json/mcp/mcp-adapter-default-server`; reads are also reachable through the adapter's generic tools; writes only as their own tool, so the MCP client asks before each write. Annotations follow the rules in Global Constraints.

### Task 4: Seravo post types by default, post lock on update-post

**Files:**
- Modify: `plugins/seravo-ai-assistant/includes/class-abilities.php` (`creatable_post_types()` ~274, `editable_post_types()` ~343, `update_post()` ~569)
- Create: `plugins/seravo-ai-assistant/tools/tests/test-post-types-and-lock.php`

**Interfaces:**
- Produces: `public static function Abilities::public_post_types(): array` (list of slugs: `get_post_types( array( 'public' => true, 'show_in_rest' => true ) )` without `attachment`, as a list). `creatable_post_types()` and `editable_post_types()` pass it to their existing filters as the default.
- Produces: `update-post` returns `WP_Error` code `seravo_ai_assistant_post_locked`, data `status` 409, when another user holds the post lock.

- [ ] **Step 1: Write the failing tests.** Helper in the file registers throwaway types inside each test and unregisters them in `defer()`: `seravo_test_public` (`public` true, `show_in_rest` true), `seravo_test_norest` (`public` true, `show_in_rest` false), `seravo_test_private` (`public` false, `show_in_rest` true). Tests:
  - `'post types: default is every public REST type except attachment'`: `public_post_types()` contains `post`, `page`, `seravo_test_public`; not `attachment`, `seravo_test_norest`, `seravo_test_private`. `editable_post_types()` and `creatable_post_types()` equal it with no filter at `PHP_INT_MAX` (`use_creatable_post_types()` from `test-creatable-post-types.php` is not used here).
  - `'post types: filter still narrows'`: with `seravo_ai_assistant_editable_post_types` returning `array( 'page' )`, `can_update_post()` on a `seravo_test_public` post returns `WP_Error` `invalid_post_type` (the code it already uses).
  - `'update-post: works on a public custom type'`: as an administrator (`create_user( 'administrator' )`), `run_ability( 'seravo-ai-assistant/update-post', array( 'post_id' => $id, 'title' => 'Uusi' ) )` on a `seravo_test_public` post is not a `WP_Error` and `get_the_title( $id )` is `'Uusi'`.
  - `'update-post: refused while another user edits the post'`: `update_post_meta( $id, '_edit_lock', time() . ':' . $other_user )`; result is `WP_Error` `seravo_ai_assistant_post_locked`; title unchanged.
  - `'update-post: own lock does not block'`: `_edit_lock` with the current user's ID; update succeeds.
- [ ] **Step 2: Run.** `tools/tests/run.sh "post types"` and `tools/tests/run.sh "update-post:"`. Expected: FAIL (`public_post_types` undefined; locked update succeeds).
- [ ] **Step 3: Implement.** Add `public_post_types()`; use it as both filters' default. In `update_post()`, before writing: `require_once ABSPATH . 'wp-admin/includes/post.php';` (REST and MCP requests do not load it), then if `wp_check_post_lock( $post_id )` is truthy return the error (message names the user: `get_userdata( $lock )->display_name`). Update both filters' docblocks (default is now every public REST post type).
- [ ] **Step 4: Run the suite.** `tools/tests/run.sh`. Expected: only the 2 baseline failures. If `test-creatable-post-types.php` assumed the old `post`/`page` default, it already overrides the filter at `PHP_INT_MAX`; confirm it still passes.
- [ ] **Step 5: Site-knowledge check.** `grep -rniE "home|rental|ketun" plugins/seravo-ai-assistant --include=*.php --include=*.md --exclude-dir=node_modules --exclude-dir=build --exclude-dir=.git`. Expected: no line added by Tasks 2-4 (compare with `git -C plugins/seravo-ai-assistant diff`).

### Task 5: Ketun koti AI boots standalone; meta rule and MCP tools; reads clean

**Files:**
- Modify: `plugins/ketunkoti-ai/ketunkoti-ai.php` (Description: drop "Add-on for MEOM AI Assistant")
- Modify: `plugins/ketunkoti-ai/includes/class-plugin.php`
- Modify: `plugins/ketunkoti-ai/includes/class-registrar.php`
- Modify: `plugins/ketunkoti-ai/includes/class-home-reader.php` (remove `Ownership` import and `ai_owned`)
- Modify: `plugins/ketunkoti-ai/includes/class-home-abilities.php` (get-home and list-homes: explicit annotations `true/false/true`; get-home description drops `ai_owned`)
- Modify: `plugins/ketunkoti-ai/tests/php/test-read.php:30,44-47`

**Interfaces:**
- Produces: `Plugin::init()` registers when `function_exists( 'wp_register_ability' )`; hooks `wp_abilities_api_categories_init` → `Registrar::register_category`, `wp_abilities_api_init` → `Home_Abilities::register`, `mcp_adapter_default_server_config` → `Registrar::add_tools`.
- Produces: `Registrar::register( string $name, array $args ): void` requires `$args['annotations']` with bool `readonly`, `destructive`, `idempotent`; otherwise `_doing_it_wrong( __METHOD__, ..., '1.1.0' )` and returns without registering. Meta per Global Constraints. Permission callback is the ability's own only (no wrapper).
- Produces: `Registrar::add_tools( $config ): array` (same contract as Seravo's `MCP::add_tools`, appending `Registrar::NAMES`). `mcp_names()` and the `meom_ai_mcp_abilities` hook are removed.
- Produces: `Registrar::NAMES` = `ketunkoti/get-home`, `ketunkoti/list-homes`, `ketunkoti/copy-home`, `ketunkoti/update-home-meta`, `ketunkoti/trash-home`.

- [ ] **Step 1: Write the failing checks** in `test-read.php`, replacing lines 30 and 44-47:

```php
check( 'get-home has no ai_owned', false, array_key_exists( 'ai_owned', is_array( $res ) ? $res : [] ) );
$tools = Registrar::add_tools( [ 'tools' => [ 'other/tool' ] ] )['tools'];
check( 'mcp tools keep existing', 'other/tool', $tools[0] );
check( 'mcp tools list every ability', Registrar::NAMES, array_values( array_diff( $tools, [ 'other/tool' ] ) ) );
check( 'no meom filter', false, has_filter( 'meom_ai_mcp_abilities' ) );
check( 'get-home public', true, $get_home->get_meta()['public'] ?? false );
check( 'get-home mcp public', true, $get_home->get_meta()['mcp']['public'] ?? false );
check( 'get-home annotations', [ true, false, true ], array_values( $get_home->get_meta()['annotations'] ?? [] ) );
check( 'show in rest', true, $get_home->get_meta()['show_in_rest'] ?? false );
check( 'category', 'ketunkoti', $get_home->get_category() );
```

Add `use Ketunkoti\AI\Registrar;` at the top.
- [ ] **Step 2: Run.** `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-read.php`. Expected: FAIL (abilities not registered: `Plugin::init()` still waits for MEOM's `Settings`).
- [ ] **Step 3: Implement** `Plugin`, `Registrar` and `Home_Reader` per Interfaces. Copy-home, update-home-meta and trash-home get their target annotations now (`false/false/false`, `false/true/true`, `false/true/true`) so they register; their behaviour changes in Task 6. Remove the propose/apply/discard registrations and delete `includes/class-meta-proposals.php` and its `require_once` in Task 6, not here.
- [ ] **Step 4: Run.** test-read.php → `ALL PASSED`; test-fields.php → `ALL PASSED`.

### Task 6: Ketun koti AI writes on any editable home

**Files:**
- Modify: `plugins/ketunkoti-ai/includes/class-home-abilities.php` (remove `Ownership`, proposal abilities, `proposal_schema()`; rewrite `update_home_meta()`, `trash_home()`; new labels and descriptions)
- Modify: `plugins/ketunkoti-ai/includes/class-home-copier.php` (remove `Ownership` import and `Ownership::mark()`)
- Modify: `plugins/ketunkoti-ai/includes/class-home-fields.php` (add `LOG_META`)
- Modify: `plugins/ketunkoti-ai/includes/class-registrar.php` (`NAMES` already final from Task 5)
- Delete: `plugins/ketunkoti-ai/includes/class-meta-proposals.php` and its `require_once` in `ketunkoti-ai.php`
- Delete: `plugins/ketunkoti-ai/tests/php/test-proposals.php`
- Modify: `plugins/ketunkoti-ai/tests/php/test-copy.php` (lines 11, 79, 112, 117, 127, 150-160)
- Create: `plugins/ketunkoti-ai/tests/php/test-write.php`
- Modify: `plugins/ketunkoti-ai/README.md`

**Interfaces:**
- Produces: `Home_Fields::LOG_META = '_ketunkoti_ai_meta_log'`. One row per successful `update-home-meta` call via `add_post_meta( $post_id, LOG_META, wp_slash( [ 'user_id' => int, 'changed' => time(), 'before' => Home_Fields::values( $post_id, keys ), 'after' => $prepared ] ) )`; `before` is read before the write.
- Produces: errors `ketunkoti_ai_post_locked` (another user's lock, via `wp_check_post_lock()` after `require_once ABSPATH . 'wp-admin/includes/post.php'`) for update-home-meta and trash-home; `ketunkoti_ai_not_a_home` kept; `ketunkoti_ai_not_ai_owned` removed.
- Permissions: update-home-meta `edit_post` on the id; trash-home `delete_post` on the id; copy-home unchanged (`can_copy`).

- [ ] **Step 1: Write the failing tests** in `test-write.php` (bootstrap helpers `make_user`, `make_home`, `check`, `code`, `finish`):
  - Editor; published home with `home_floor` `'2/5'`: `update-home-meta` `{ home_floor: '3/5' }` returns array; meta is `'3/5'`; one log row with `before.home_floor` `'2/5'`, `after.home_floor` `'3/5'`, `user_id` = editor.
  - Same home, `{ home_floor: '4/5', home_year_built: 'vanha' }`: code `ketunkoti_ai_invalid_value`; `home_floor` still `'3/5'`; still one log row.
  - Another user's `_edit_lock` (`time() . ':' . $other`): code `ketunkoti_ai_post_locked`, nothing written. Editor's own lock: write succeeds.
  - Author A and Author B; B's published home: as A, `update-home-meta` and `trash-home` are refused (`WP_Error` from the permission check; assert `is_wp_error`), meta unchanged, status `publish`.
  - Editor, published home: `trash-home` returns `status` `trash`; post still exists. Another user's lock on a second home: `ketunkoti_ai_post_locked`, status unchanged.
  - Annotations: update-home-meta `[ false, true, true ]`, trash-home `[ false, true, true ]`, copy-home `[ false, false, false ]`; `mcp.public` false for all three; `public` true.
  - `wp_get_ability( 'ketunkoti/propose-home-meta' )` is null (also apply, discard).
- [ ] **Step 2: Run.** `npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-write.php`. Expected: FAIL (fatal on `Ownership` or `ketunkoti_ai_not_ai_owned`).
- [ ] **Step 3: Implement** per Interfaces. New labels: "Update home fields", "Move a home to the trash". Descriptions: update-home-meta sets `home_*` fields on any home or rental the user may edit, published ones included, saves at once, and the MCP client asks the user first; keep the existing text about `null`/`""` clearing and `notes`. trash-home: moves any home or rental the user may delete to the trash, never deletes permanently. copy-home: drop "your own" wording.
- [ ] **Step 4: Update `test-copy.php`:** remove the `Ownership` import and the `ai owned` checks (lines 79, 112); line 117 becomes "published home updated" (returns array); line 127 becomes "hand-saved draft updated"; trash section (150-160): own draft trashed (unchanged), published not refused anymore (move to test-write, delete here), page still `ketunkoti_ai_not_a_home`.
- [ ] **Step 5: Run all ketunkoti-ai tests.** `for t in fields read copy write; do npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-$t.php; done`. Expected: four `ALL PASSED`. Then `grep -rn "MEOM\|meom\|Ownership\|Meta_Proposals\|ai_owned" plugins/ketunkoti-ai` returns nothing outside README history notes (README is rewritten next).
- [ ] **Step 6: Rewrite `README.md`:** requirements (WordPress 7.1+, Ketun koti Features, MCP Adapter for Claude; works with or without Seravo AI Assistant, whose chat also offers these abilities); abilities table (5 rows, with the access rule per ability); "A typical new home" (list → copy → update-home-meta → title and content with `seravo-ai-assistant/update-post` when Seravo is active, otherwise in wp-admin; terms and images in wp-admin for now → editor publishes); "Changing an existing home" (Claude calls update-home-meta, the MCP client or the Seravo chat asks first; another user's open editor blocks it; every change logged in `_ketunkoti_ai_meta_log`); tests loop `for t in fields read copy write`.

### Task 7: Live verification over MCP and in the chat

**Files:**
- Modify (only if a gap is found): `docs/mcp-parannusehdotukset.md`

- [ ] **Step 1: App password.** `npx wp-env run cli wp user application-password create admin mcp-check --porcelain` → `$PASS`.
- [ ] **Step 2: Session.** `POST http://localhost:8888/wp-json/mcp/mcp-adapter-default-server` with `--user "admin:$PASS"`, JSON-RPC `initialize` (`protocolVersion` `2025-11-25`); capture `Mcp-Session-Id`; send `notifications/initialized`. All later calls carry the header.
- [ ] **Step 3: `tools/list`.** Expected: the three `mcp-adapter-*` tools plus 18 `seravo-ai-assistant-*` and 5 `ketunkoti-*` tools; `annotations` on each match the tables in Task 2 and Task 6 (`readOnlyHint`, `destructiveHint`, `idempotentHint`).
- [ ] **Step 4: Writes as their own tool.** `tools/call` `seravo-ai-assistant-update-post` on a throwaway post (create it with `wp post create`), and `ketunkoti-update-home-meta` on a throwaway home: both succeed. Repeat with a Subscriber's app password: both return `isError` true.
- [ ] **Step 5: Generic path.** `tools/call` `mcp-adapter-execute-ability` with `ability_name` `seravo-ai-assistant/update-post`: refused. With `seravo-ai-assistant/get-post`: works. `mcp-adapter-discover-abilities`: lists `ketunkoti/get-home`, no write. If step 4 or 5 contradicts this, **stop and report to Sami** (the `mcp.public = false` decision depends on it).
- [ ] **Step 6: Without Seravo.** `npx wp-env run cli wp plugin deactivate seravo-ai-assistant`, repeat `tools/list`: the 5 `ketunkoti-*` tools are still there. Reactivate.
- [ ] **Step 7: Seravo chat (needs an AI connector; if none is configured, report the step as not run).** Ask the chat to change a home's floor: an approval card appears for `ketunkoti/update-home-meta`; asking for the home's details runs `ketunkoti/get-home` without a card; asking to translate a sentence runs `seravo-ai-assistant/translate-text` without a card.
- [ ] **Step 8: Clean up.** Delete throwaway posts and homes; `wp user application-password delete admin <uuid>`.
- [ ] **Step 9: Report.** List what passed, what was not run and why. Add a line to `docs/mcp-parannusehdotukset.md` for any MCP-only gap found. Ask Sami whether to run linting: no PHPCS is installed in the project, so offer `php -l` on changed files now and setting up PHPCS (MEOM-default for `ketunkoti-ai`, WordPress standard for Seravo) as a follow-up.
