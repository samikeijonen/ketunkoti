# Standard MCP writes for Seravo AI Assistant and Ketun koti AI

Date: 2026-11-19
Status: draft, waiting for approval

## Intent

Claude (in the Seravo chat in wp-admin, and in Claude Desktop / Claude Code over MCP)
must be able to read **and write** site content using only WordPress core and MCP
standards. No home-made safety protocol on top of MCP.

Why now: `ketunkoti-ai` depends on `meom-ai-assistant` for draft ownership, the
propose/apply flow and the MCP apply opt-in. Those are MEOM-specific, non-standard
mechanisms. Replacing them with standard ones lets `ketunkoti-ai` stand alone and
lets `seravo-ai-assistant` be the site's assistant.

Reference: WordPress/ai issue #1287 (one AI agent across wp-admin) and its AI Workspace
code (PR #1004, #1314, #1382). AI Workspace is not released yet (PR #1004 open,
`feat/ai-workspace` 93 commits ahead of `develop`, latest release 1.5.0 without it).

## Decisions made (by Sami)

1. **Seravo keeps its own chat for now**, but is built so it can later lean on AI
   Workspace (whose writes are proposal kinds, registered through
   `wpai_workspace_proposal_kinds`).
2. **Over MCP, the client's approval is enough.** Claude Desktop / Claude Code ask
   before running a tool. No server-side propose/apply over MCP, no profile opt-in
   (`meom_ai_allow_mcp_apply`), no ownership meta (`_meom_ai_owner` and friends).
3. **Annotations follow the MCP meaning strictly** (`readonly`, `destructive`,
   `idempotent`; see the rules below).
4. **Writes are available over MCP**, on the existing default endpoint
   `/wp-json/mcp/mcp-adapter-default-server`. No new MCP endpoint.
5. **Each plugin exposes its own abilities** (approach 1 below).
6. **Writes get `meta.mcp.public = false`** so they run only as their own tool, never
   through the generic `mcp-adapter-execute-ability`.
7. **`ketunkoti-ai` may update fields and trash any home the user can edit**
   (published ones too), not only drafts Claude created.
8. **Production switches from MEOM to Seravo.** `meom-ai-assistant` and
   `meom-ai-translate` are deactivated there and removed from `.wp-env.json`.
9. **Seravo can update any public post type by default**, with the existing filter
   kept for narrowing or widening.
10. **Ketun koti's abilities appear in the Seravo wp-admin chat** (writes behind
    approval cards, like Seravo's own).
11. **Seravo has no site-specific knowledge.** No code, filter, default, test, doc or
    ability description in `seravo-ai-assistant` mentions homes, rentals, `home_*`
    fields or Ketun koti. Anything home-specific lives in `ketunkoti-ai`, which may
    use Seravo's generic abilities and filters but never the other way round.

## Goals

- Every ability in both plugins has correct, explicit `readonly`, `destructive` and
  `idempotent` annotations.
- Every ability in both plugins is its own MCP tool on the default server, so the MCP
  client shows a per-tool approval prompt with the right hints.
- A write cannot be run through `mcp-adapter-execute-ability`.
- `ketunkoti-ai` works with no assistant plugin active (only Ketun koti Features and
  the MCP Adapter), using core capabilities only.
- Write logic is separate from ability registration, so AI Workspace can wrap it as a
  proposal kind later without rewriting it.

## Non-goals

- Porting the abilities Seravo is missing compared to `meom-ai-assistant`
  (list-content, terms, media upload/update, menus, SEO, redirects, analysis). That is
  the next project, one group at a time.
- A server-side preview / propose step over MCP (including MCP elicitation).
- Registering anything with AI Workspace now.
- Changing `meom-ai-assistant` or `meom-ai-translate` (they are switched off instead).
- Migrating data stored by the MEOM plugins (see Known gaps).

## Anti-goals (would make this a failure even if it technically works)

- A write reachable through any MCP path without its own approval prompt.
- `ketunkoti-ai` needing `seravo-ai-assistant` (or MEOM) to be active.
- Seravo deciding MCP exposure for other plugins' abilities.
- Seravo knowing about homes (or any other site's content model).
- Custom Seravo-only consent mechanics (opt-in switches, ownership fingerprints,
  proposal tables for MCP).

## Constraints

- WordPress 7.1 locally (7.1.5); core 7.2 adds read abilities (`core/content-query`,
  `core/settings-get`, `core/users-query`) but no write abilities.
- MCP Adapter v0.7.1 (from `.wp-env.json`).
- Seravo is PHP 8.2, tabs (its own WordPress-standard PHPCS). `ketunkoti-ai` follows
  MEOM-default PHPCS (4 spaces).
- `seravo-ai-assistant` is its own git repo (`samikeijonen/seravo-ai-assistant`),
  gitignored in the ketunkoti repo. `ketunkoti-ai` lives in the ketunkoti repo.
- Sami commits himself; nothing in this project commits.

## How the MCP Adapter behaves (verified in v0.7.1 source)

- The default server exposes three generic tools: `mcp-adapter-discover-abilities`,
  `mcp-adapter-get-ability-info`, `mcp-adapter-execute-ability`. These list and run
  only abilities whose MCP exposure is public
  (`McpAbilityExposure::is_public()`: `meta.mcp.public` if set, otherwise
  `meta.public`).
- `mcp_adapter_default_server_config` can add ability names to `$config['tools']`.
  Each becomes its own MCP tool (name `plugin-slug-ability-name`), with annotations
  mapped to `readOnlyHint` / `destructiveHint` / `idempotentHint`
  (`McpAnnotationMapper`).
- `McpComponentRegistry::register_ability_tool()` and `ToolsHandler` (`tools/call`)
  do **not** check MCP exposure; they run the ability's `permission_callback` only.
  So an ability with `mcp.public = false` that is listed in `tools` is callable as its
  own tool and refused by `execute-ability`. Must be confirmed with a real MCP call
  (see Verification).
- `meom-ai-assistant` already uses this pattern (`includes/class-mcp.php`), which is
  why live sites show `meom-ai-*` tools next to the three generic ones.

## Approaches considered

1. **Each plugin exposes its own abilities (chosen).** Seravo gets a small MCP class
   that lists its abilities as tools on the default server. `ketunkoti-ai` does the
   same for its own (about 20 lines). Both work alone or together, and alongside AI
   Workspace later. Cost: the same small pattern exists twice.
2. **`ketunkoti-ai` hooks into Seravo** through a Seravo filter (like
   `meom_ai_mcp_abilities`). Less code, but re-creates the dependency being removed.
3. **Seravo lists every public ability on the site as a tool.** No MCP code in other
   plugins, but floods the client with tools (the problem the adapter's three generic
   tools exist to avoid) and has Seravo decide exposure for other plugins.

## Annotation rules

| Annotation | true when |
|---|---|
| `readonly` | The call changes nothing stored on the site (posts, meta, options, terms, files, users). Calling an external AI model does not count as a site change. |
| `destructive` | Only meaningful when `readonly` is false. True when the call can overwrite, change or remove existing data (update, upsert, trash, replace). False when it only adds something new. |
| `idempotent` | Repeating the same call with the same arguments has no additional effect on the site. Reads are always true. Updates/trash are true. Creates are false. |

Every ability states all three explicitly (no relying on defaults).

### Seravo: target annotations

| Ability | Today r/d/i | Target r/d/i | Change |
|---|---|---|---|
| get-site-overview, get-post, get-guidelines, get-glossary, get-translation-job, get-post-translations, search-media | t/f/t | t/f/t | none |
| get-design-tokens, list-patterns, get-pattern, get-block-types | via shared design helper | t/f/t | builder verifies the helper sets all three |
| create-post | f/f/f | f/f/f | none |
| update-post | f/t/t | f/t/t | none |
| update-guidelines | f/f/t | **f/t/t** | replaces existing guidelines |
| upsert-glossary-term | f/f/t | **f/t/t** | can update an existing term |
| delete-glossary-term (trash) | f/t/t | f/t/t | none |
| translate-text | f/f/f | **t/f/t** | returns translated text, stores nothing. Builder must confirm it writes nothing (cache, examples, logs to options). If it does store something, it stays f/f/f and this row is noted. |
| translate-post | f/f/f | f/f/f | creates a new draft and a job; keep non-idempotent (a second call while a job runs may start another) |

Effect in the Seravo chat: the chat asks for approval for every ability not marked
`readonly` (`class-agent.php`, `requires_confirmation()`). `translate-text` stops
asking. Nothing else changes in the chat.

### Ketun koti AI: target annotations

| Ability | Target r/d/i | Notes |
|---|---|---|
| get-home | t/f/t | drop `ai_owned` from the output |
| list-homes | t/f/t | |
| copy-home | f/f/f | new draft; no ownership marking |
| update-home-meta | f/t/t | any home the user can edit |
| trash-home | f/t/t | any home the user can delete; still never deletes permanently |
| propose-home-meta, apply-home-meta, discard-home-meta | removed | replaced by client approval of update-home-meta |

## Design

### 1. Shared rule for meta (both plugins)

Each plugin builds ability meta in one place (Seravo: `Abilities::meta()`;
`ketunkoti-ai`: `Registrar::register()`):

- `public` = true. Puts the ability in the Seravo chat (`Agent::get_available_abilities()`
  takes every `meta.public` ability) and in core's abilities REST API. Unchanged for
  Seravo; **new for `ketunkoti-ai`**, which today sets only `show_in_rest` and
  `mcp.public`, so its abilities join the Seravo chat when both are active.
- `annotations` = the three values, required.
- `mcp.public` = `annotations.readonly === true`. Reads stay discoverable through the
  generic tools; writes do not.
- `mcp.type` = `tool`.

### 2. MCP exposure in Seravo

New class (e.g. `includes/class-mcp.php`, `MCP`), hooked from `bootstrap()`:

- Filter `mcp_adapter_default_server_config`: append the name of **every** ability this
  plugin registers (reads and writes) to `$config['tools']`, de-duplicated.
- The list comes from what the plugin registers, not a hand-kept copy (builder's call
  how: a registry in `Abilities` that the other ability classes add to, or filtering
  `wp_get_abilities()` by the `seravo-ai-assistant/` prefix at filter time).
- No changes to other plugins' abilities, no app-password or capability filters.
- Docblock explains why writes are `mcp.public = false`.

### 3. Seravo write logic separate from registration

To be ready for AI Workspace proposal kinds: each write ability's execute callback is
a thin call into a method that takes validated input and returns the result
(`(array $input): array|WP_Error`), with no chat, REST-controller or MCP code in it.
Where a callback already looks like that, leave it. Do not build any proposal-kind
adapter now.

### 3b. Seravo post types: every public type by default

`Abilities::editable_post_types()` (filter `seravo_ai_assistant_editable_post_types`)
defaults to `post` and `page` today, so `update-post` refuses any custom post type.
New default: `get_post_types( array( 'public' => true, 'show_in_rest' => true ) )`
minus `attachment`. The filter stays and receives that list. This is generic: Seravo
names no site's post types.

On this site, Ketun koti Features registers `home` and `rental` as public with
`show_in_rest` (`post-types.php`), so Seravo's generic `update-post` covers their
title and content with no code in either plugin. Their `home_*` fields stay
`ketunkoti-ai`'s job.

My call: `seravo_ai_assistant_creatable_post_types` (create-post) gets the same
default, so Claude can create what it can update. The per-post capability checks
(`edit_post`, the type's `create_posts` / `publish_posts`) are unchanged and still
decide per user.

### 4. Post lock (both plugins, my call, core standard)

Writes to an existing post (`seravo-ai-assistant/update-post`,
`ketunkoti/update-home-meta`, `ketunkoti/trash-home`) refuse with a `WP_Error` when
`wp_check_post_lock()` reports another user editing that post. AI Workspace does the
same. Not applied to glossary terms or guidelines.

### 5. Ketun koti AI standalone

- Header: `Requires Plugins: ketunkoti-features` (already done). Description and
  README drop "Add-on for MEOM AI Assistant".
- `Plugin::init()`: register when the Abilities API exists
  (`function_exists( 'wp_register_ability' )`), not when MEOM's `Settings` exists.
  Hooks: categories, abilities, and its own `mcp_adapter_default_server_config` filter.
- `Registrar`: drop `MEOM\AI_Assistant\Settings`; the permission callback is the
  ability's own core capability check only. Apply the meta rule from section 1.
  `mcp_names()` becomes the `mcp_adapter_default_server_config` callback that appends
  `Registrar::NAMES`; the `meom_ai_mcp_abilities` hook is removed.
- Capabilities: list-homes `edit_posts`; get-home and update-home-meta
  `edit_post` on the id; copy-home `create_posts` of the source's post type;
  trash-home `delete_post` on the id.
- Remove `MEOM\AI_Assistant\Ownership` everywhere (`Home_Abilities`, `Home_Copier`,
  `Home_Reader`).
- Remove `Meta_Proposals` and the three proposal abilities.
- update-home-meta keeps its field validation (`Home_Fields::prepare`) and its
  `notes`, and now also writes the before/after row to `_ketunkoti_ai_meta_log`
  (today only applied proposals do). My call: post meta does not get revisions, so
  this log is the only record of what Claude changed in `home_*` fields.
- README: rewrite requirements, abilities table, "a typical new home" and
  "changing an existing home" for the new model. Step 4 of "a typical new home"
  (title, content, terms, images) becomes `seravo-ai-assistant/update-post` for
  title and content (works for homes through 3b); terms and images are manual in
  wp-admin until Seravo gains those abilities (next project). Mention that the
  abilities also appear in the Seravo chat.
- Tests: drop `test-proposals.php`; update `test-copy.php` and `test-read.php` for no
  ownership and the new MCP filter; add cases for update-home-meta on a published
  home, the meta log row, the post lock, and trash-home on a published home.

### 6. Local environment and production rollout

Local (`.wp-env.json`): remove `./plugins/meom-ai-assistant` and
`./plugins/meom-ai-translate` from `plugins`; the `.gitignore` lines for them stay
(the folders may still exist on disk). Restart wp-env.

Production rollout, in this order, done by Sami (the deploy workflow does not carry
Seravo, which is gitignored and cloned separately):

1. Install `seravo-ai-assistant` on production (how is Sami's call), activate it and
   connect its AI provider.
2. Deactivate `meom-ai-assistant` and `meom-ai-translate` with WP-CLI: `ssh ketunkoti`,
   then in `~/public_html` run `wp plugin deactivate meom-ai-translate meom-ai-assistant`.
   Not in wp-admin: the old production `ketunkoti-ai` and `meom-ai-translate` declare
   `Requires Plugins: meom-ai-assistant`, so wp-admin hides MEOM's Deactivate link.
   Between steps 2 and 3 the old `ketunkoti-ai` registers nothing, so Claude briefly
   has no home tools.
3. Merge to `main`, which deploys `ketunkoti-ai` (`.github/workflows/deploy.yml`).
4. Reconnect or refresh Claude Desktop / Claude Code against the default MCP
   endpoint and check the tool list (Verification step 1, on production).

Merging before step 2 is not safe: MEOM's `Mcp::limit_exposure` (priority 150 on
`wp_register_ability_args`) forces `mcp.public = false` on every non-MEOM ability.

## Verification

In the local wp-env (http://localhost:8888), with an application password, with
`meom-ai-assistant` and `meom-ai-translate` not active:

1. MCP `tools/list` on `/wp-json/mcp/mcp-adapter-default-server` lists every Seravo
   and `ketunkoti-ai` ability as its own tool, with `readOnlyHint`,
   `destructiveHint` and `idempotentHint` matching the tables above.
2. `tools/call` on a write tool (e.g. `seravo-ai-assistant-update-post`,
   `ketunkoti-update-home-meta`) succeeds for a user who may edit the post and fails
   for one who may not.
3. `mcp-adapter-execute-ability` with a write ability's name is refused;
   with a read ability's name it works. `discover-abilities` lists reads only.
4. With Seravo deactivated, `ketunkoti-ai` still registers and exposes its abilities.
   With `meom-ai-assistant` deactivated (current local state), same.
5. Seravo chat: a write still shows an approval card; `translate-text` no longer does.
   The home abilities are offered in the chat; `ketunkoti/update-home-meta` shows an
   approval card, `ketunkoti/get-home` does not.
5b. Seravo's own tests use a test-only custom post type registered public in the
   test, never `home`. On this site, `seravo-ai-assistant/update-post` updates a
   `home` the user can edit and is refused for one who cannot (checked from the
   `ketunkoti-ai` side or by hand, not in Seravo's suite).
5c. `grep -ri "home\|rental\|ketun"` over `seravo-ai-assistant` (excluding
   `node_modules`, `build`, `.git`) finds nothing added by this project.
6. `ketunkoti-ai` PHP tests pass in the tests container; Seravo's own test suite
   (`tools/tests/run.sh`) is adapted to wp-env or the relevant tests are run with
   `wp eval-file` (builder's call).
7. PHPCS: `ketunkoti-ai` with MEOM-default; Seravo with its own ruleset.

If step 2 or 3 contradicts the adapter reading above, stop and report before
continuing: the `mcp.public = false` decision depends on it.

## Left to the builder

- How Seravo collects its own ability names for the MCP filter.
- File and class names of new pieces.
- Exact error codes and messages (`ketunkoti_ai_*`, `seravo_ai_assistant_*`).
- Whether translate-post needs any change beyond its annotations (none expected).
- Splitting the work into commits-sized steps (Sami commits).

## Known gaps after this project

- Seravo cannot yet set terms or upload/update media, so parts of the home workflow
  stay manual until the next project.
- Attribution of Claude's changes to an agent user waits for WordPress/ai Agent Users
  (#923).
- Data the MEOM plugins stored on production is not migrated: `meom-ai-translate`'s
  glossary and translation links, MEOM's change log table and ownership meta. Sami
  should check whether production has glossary terms worth re-entering in Seravo's
  glossary before switching.
- `docs/mcp-parannusehdotukset.md` should get a line for anything found during
  verification that only MCP clients hit.
