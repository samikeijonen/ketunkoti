# ketunkoti-ai: home abilities for Claude

Date: 2026-10-30
Status: draft for approval

## Intent

Adding a home listing to Ketun koti means filling in about twenty meta fields and six taxonomies by hand, then writing the content. The editor wants Claude (on their computer, through MCP) to do this work: copy an existing home exactly as it is, then change the meta, content and images. Sometimes a published home also needs an update, for example a new price, and then Claude must always ask before anything changes.

`meom-ai-assistant` already lets Claude create and edit its own drafts (title, content, excerpt, featured image, terms), upload images and propose approved changes to published content. It cannot copy a post or write custom meta. `ketunkoti-ai` fills exactly that gap, as an add-on for `meom-ai-assistant`.

## Goals

- Claude can read a whole home (meta, terms, calculated investment figures) in one call.
- Claude can copy an existing `home` or `rental` into a new draft that it may then edit freely.
- Claude can set `home_*` meta on its own drafts directly.
- Claude can change `home_*` meta on any other home (published or not) only through a propose → user says yes → apply flow that the server enforces.

## Non-goals

- No changes to `meom-ai-assistant`. It is MEOM's shared plugin and cannot be changed now. (Later, meta support may move into its `update_fields` change type; see "Built to move".)
- No wp-admin chat integration and no approval cards in wp-admin. First user is Claude over MCP.
- Title, content, excerpt, featured image and terms of homes Claude did not create keep going through the assistant's own `propose-change` cards. ketunkoti-ai does not write them on existing content.
- No image generation. Images go through the assistant's `meom-ai/upload-media`.
- No publishing. The editor opens the draft, checks it and publishes.

## Anti-goals (failure even if it works)

- Any path where a published home's meta changes without the editor having seen the before/after values and said yes.
- A copy that silently differs from the source (missing fields or terms) so the editor has to re-check everything by hand.
- Writing calculated figures (`home_rental_yield`, `home_cash_flow`, `home_equity_return`) as meta. They are computed by `ketunkoti-features` and must stay computed.
- Bypassing the validation the block editor uses for the same fields.

## Context

- Post types `home` (for sale and investment flats) and `rental`, registered in `plugins/ketunkoti-features/includes/post-types.php`.
- Taxonomies in `includes/taxonomies.php`: `home-city`, `home-status`, `home-type`, `home-rooms` (both types), `home-plot` and the purpose taxonomy (`PURPOSE_TAXONOMY`, `home` only).
- Meta in `includes/meta.php`: about 20 `home_*` keys registered with `register_post_meta` (some on both types via `register_listing_meta`, some on one type only), each with type, description, `sanitize_callback`, `show_in_rest` and `auth_callback` (`edit_post`).
- Calculated figures in `includes/investment.php` and `includes/bindings.php` (`COMPUTED_FIELDS`).
- `meom-ai-assistant`: abilities under `meom-ai/*`; ownership in `MEOM\AI_Assistant\Ownership` (`mark`, `record_write`, `is_ai_owned`, meta `_meom_ai_owner`, `_meom_ai_last_write`, `_meom_ai_last_hash`); MCP only exposes other plugins' abilities listed in the filter `meom_ai_mcp_abilities`; per-user switch `meom_ai_allow_mcp_apply` (user meta) gates applying over MCP; chat capability from filter `meom_ai_capability`.
- Active on the site: The SEO Framework (`autodescription`), no Yoast, no Polylang.
- Stack: WordPress 7.1+, PHP 8.2+, MEOM PHPCS (4 spaces), text domain of the new plugin `ketunkoti-ai`.

## Design

### Plugin

New plugin `plugins/ketunkoti-ai`, declared with `Requires Plugins: meom-ai-assistant, ketunkoti-features`. It registers an ability category `ketunkoti` and its abilities on the Abilities API hooks.

The assistant documents `meom_ai_mcp_abilities` as "add only abilities that read". ketunkoti-ai adds write abilities on purpose; the site owner (MEOM) agreed on 2026-10-30, because the server-side approval below covers every write to existing content.

MCP exposure needs two things (checked against `meom-ai-assistant/includes/class-mcp.php`): every ability name is added to `meom_ai_mcp_abilities` (otherwise the assistant hides it), and every ability sets `meta.mcp.public = true` and `show_in_rest = true` in its own registration (otherwise the MCP Adapter does not list it as a tool).

Every ability requires `MEOM\AI_Assistant\Settings::user_can_chat()` (the assistant's capability from `meom_ai_capability` and its "Enabled" setting, so ketunkoti-ai switches off with the assistant) plus `edit_post` on the post it touches.

The list of editable meta keys is read at runtime from the meta registered for the post type (`get_registered_meta_keys( 'post', $type )`), limited to keys starting with `home_`. No hard-coded field list, so a new field in `ketunkoti-features` is picked up automatically. Each key's type and description come from its registration and go into the ability schemas and responses, so Claude knows units and formats.

### Abilities

1. **`ketunkoti/get-home`** (read). Input: `id`. Returns post type, status, title, excerpt, featured image (id, url, alt), every registered `home_*` field with value, type and description, terms per taxonomy (id, name), the calculated figures (read-only, marked as such) and `ai_owned` (whether Claude may edit it directly). Accepts `home` and `rental`.

2. **`ketunkoti/list-homes`** (read). Input: optional `post_type`, `status`, `search`, `limit`/`offset`. Returns compact rows (id, type, title, status, city, rooms) so Claude can find a source to copy.

3. **`ketunkoti/copy-home`** (write, no approval: it creates a new draft and changes nothing existing). Input: `id`, optional `title`. Creates a draft of the same post type with the same content, excerpt, menu order, template, featured image, all registered `home_*` meta and all terms. The title defaults to the source title with a "(kopio)" suffix. SEO meta, edit locks, `_meom_ai_*` and other plugins' private meta are not copied. Marks the draft as Claude's own with `Ownership::mark()` and `Ownership::record_write()`, so the assistant's `meom-ai/update-ai-draft` works on it too. Returns the new id and edit link.

4. **`ketunkoti/update-home-meta`** (write, no approval). Input: `id`, `meta` (object of key → value; `null` deletes). Only on an AI-owned draft (`Ownership::is_ai_owned`); otherwise returns an error telling Claude to use `propose-home-meta`. Calls `record_write()` after writing.

5. **`ketunkoti/propose-home-meta`** (stores a proposal, changes nothing). Input: `id`, `meta`. Works on any home or rental. Refused up front when the user has the assistant's switch `meom_ai_allow_mcp_apply` off, because there is no wp-admin card to apply it from; the error tells Claude to ask the user to turn on **Allow applying changes from Claude on my computer** in their profile. Returns `proposal_id` and, per field, the label, the current value and the value as it will be saved (after the field's sanitizer), leaving out fields that already have that value. Claude shows this to the editor and asks.

6. **`ketunkoti/apply-home-meta`** (write). Input: `proposal_id`. Refused unless:
   - the current user made the proposal,
   - it is under 30 minutes old and still pending,
   - none of the target fields changed since the proposal (compare the stored "before" values),
   - the user has turned on the assistant's switch `meom_ai_allow_mcp_apply`,
   - the user still may edit the post.

   On success, writes the values and marks the proposal applied, keeping before and after values.

7. **`ketunkoti/discard-home-meta`**. Input: `proposal_id`. Marks it discarded.

### Shared write rules

- Every write (directly or through apply) goes through `update_post_meta` / `delete_post_meta`, so the field's registered `sanitize_callback` runs: the same validation as the block editor.
- Unknown keys, keys not registered for that post type, and the calculated figures are refused with an error naming the key. One bad key refuses the whole call; nothing is half written.
- The `ketunkoti-features` sanitizers never fail: a bad value silently becomes `0` or `''` (an impossible date becomes `''`, which the site shows as "Heti vapaa"). So every write and proposal runs the field's sanitizer first and **refuses** the call when a non-empty input sanitizes to an empty value (`0` or `''`), naming the field and the input. Empty input and `null` mean "clear the field" and are allowed. *(Builder's call, made in the design: no silent "not set" on drafts, which have no preview.)*
- Numbers are not strictly type-checked before sanitizing: `sanitize_positive_number` accepts Finnish formats such as `"1 285,50"`, and that stays allowed. The response always returns the values as saved, so Claude can confirm them.

### Approval flow over MCP

The editor's "ask before changes" is honoured twice: Claude must show the before/after list from `propose-home-meta` and get a yes in the conversation, and the server only applies a stored proposal from the same user with the profile switch on. Claude's per-tool approval prompt on `apply-home-meta` is the final human step, as with the assistant's own `apply-change`.

### Built to move

Proposal storage, staleness check and apply live in one class with no MCP-specific code, so the logic can later move into `meom-ai-assistant` as meta support in `update_fields` without rewriting the abilities' inputs.

## Left to the builder

- Where proposals and applied records are stored (for example a private post type, or post meta on the home plus a transient for pending ones), as long as applied before/after values are kept and readable.
- Exact response shapes beyond what is listed above.
- Error codes (prefix `ketunkoti_ai_`).
- Tests: at least the apply refusals (other user, expired, stale, switch off) and the copy completeness (every registered `home_*` key and every taxonomy copied).
- A short README covering setup, the abilities and the approval flow.
