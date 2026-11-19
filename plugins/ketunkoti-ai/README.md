# Ketun koti AI

Home abilities for Claude. Lets Claude read, copy and fill in home listings: the `home` (for sale and investment flats) and `rental` post types of Ketun koti Features. The abilities are registered with the WordPress Abilities API and exposed on the MCP Adapter's default server, so Claude on your computer can use them over MCP.

## Requirements

- WordPress 7.1+ (Abilities API), PHP 8.2+.
- Ketun koti Features active (`Requires Plugins`).
- For Claude on your computer: the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin and an MCP client connected to the site.

The plugin works with or without Seravo AI Assistant. When Seravo AI Assistant is active, its chat offers these abilities too, and Seravo's own generic abilities cover the rest of a listing:

- `seravo-ai-assistant/update-post`: title, content, excerpt, status, `terms` (`{ taxonomy: [term ids] }`, replaces only the taxonomies named) and `featured_media` (attachment id, `0` removes).
- `seravo-ai-assistant/list-taxonomies`, `list-terms`, `create-term`: which taxonomy belongs to which post type (the `home-*` taxonomies map to `home` and `rental`) and their terms.
- `seravo-ai-assistant/upload-media`, `update-media`, `get-media-image`: photos (https URL or base64) with alt text, title and caption.
- `seravo-ai-assistant/get-seo`, `update-seo`: SEO title, description, social titles, canonical and indexing in The SEO Framework (or Yoast).

This plugin only knows the `home_*` fields; Seravo knows nothing about homes.

Each ability checks only WordPress's own capabilities, per home (see the table). There is no separate AI permission and no draft ownership.

## Abilities

| Ability | Input | Access | What it does |
|---|---|---|---|
| `ketunkoti/list-homes` | `post_type`, `status`, `search`, `limit`, `offset` (all optional) | `edit_posts` | Lists homes and rentals: id, type, title, status, city, rooms. Read-only. |
| `ketunkoti/get-home` | `id` | `edit_post` on the home | Returns the whole listing: post fields, featured image, every `home_*` field with label, type, description and value, terms per taxonomy and the calculated investment figures. Read-only. |
| `ketunkoti/copy-home` | `id`, optional `title` | `edit_post` on the source and `create_posts` of its post type | Copies a listing into a new draft by the current user: content, excerpt, menu order, template, featured image, every `home_*` field and every term. SEO meta is not copied. The default title is the source title with " (kopio)". |
| `ketunkoti/update-home-meta` | `id`, `meta` | `edit_post` on the home | Writes `home_*` fields on any home or rental, published ones included. Saved at once and logged. |
| `ketunkoti/trash-home` | `id` | `delete_post` on the home | Moves any home or rental, published ones included, to the trash. Never deletes permanently; the editor can restore it in wp-admin. |

The three write abilities (`copy-home`, `update-home-meta`, `trash-home`) are not marked read-only, so the MCP client asks the user before each call. In the Seravo chat, the approval card does the same.

### Annotations

| Ability | `readonly` | `destructive` | `idempotent` |
|---|---|---|---|
| `list-homes`, `get-home` | true | false | true |
| `copy-home` | false | false | false |
| `update-home-meta` | false | true | true |
| `trash-home` | false | true | true |

### Notes

Some responses carry `notes`, things Claude should check with the user. Nothing is changed because of them.

- `copy-home`: the copy's title is already used by another home or rental. Two listings with one title look alike in lists, and the later one gets a `-2` slug.
- `update-home-meta`: a field changed without the field that usually changes with it. Vuokra without Vakuus, and Velaton hinta and Myyntihinta without each other. A related field sent in the same call, even with its current value, counts as checked.

The field list is read from the meta Ketun koti Features registers, so a new `home_*` field is picked up automatically. The calculated figures (`home_rental_yield`, `home_cash_flow`, `home_equity_return`) are never written.

### Field values

Every write runs the field's own sanitizer, the same one the block editor uses. Those sanitizers never fail: a bad value would silently become empty (an impossible date would show as "Heti vapaa"). So the plugin refuses, with `ketunkoti_ai_invalid_value` naming the field:

- a non-empty text value (date, floor, yes/no and so on) that would save as empty, `0` on a date included;
- a number field value that is not a number, or that the sanitizer would change: a negative price, or decimals on a whole-euro price.

Numbers may use Finnish formatting: spaces as thousands separators and a decimal comma (`250 000`, `1 285,50`). Zero is a real value for numbers (`0`, `0,00`). `null` or `""` clears a field. One bad field refuses the whole call.

## A typical new home

1. `ketunkoti/list-homes` to find a similar home.
2. `ketunkoti/copy-home` to copy it into a draft.
3. `ketunkoti/update-home-meta` for the new prices, area, floor and other fields.
4. With Seravo AI Assistant active:
   - photos with `seravo-ai-assistant/upload-media` (with alt text);
   - title, content, terms (city, status, type, rooms…) and the featured image in one `seravo-ai-assistant/update-post` call (`list-taxonomies` and `list-terms` give the term ids, `create-term` adds a missing one);
   - SEO title and description with `seravo-ai-assistant/update-seo`.

   Without Seravo AI Assistant, these are done in wp-admin.
5. The editor opens the draft, checks it and publishes.

A test copy that is no longer needed goes to the trash with `ketunkoti/trash-home`.

### The "New home" prompt

The steps above are also an MCP prompt, `ketunkoti/new-home` (MCP name `ketunkoti-new-home`), so you don't have to explain them to Claude each time:

- Claude Code: type `/` and pick the ketunkoti server's `new-home` prompt (e.g. `/mcp__ketunkoti-production__ketunkoti-new-home`).
- Claude Desktop: the "+" menu, then the ketunkoti server's prompts.

Arguments, both optional: `source` (ID or search words of the home to copy) and `details` (the new home's details as free text: address, price, area, rooms, floor, availability…).

The prompt only returns instructions; it changes nothing. Each write is still its own tool call that the MCP client asks you to approve, and the prompt tells Claude to leave the listing as a draft. Without Seravo AI Assistant, it lists the wp-admin steps instead. It is MCP-only: not a tool, not in the Seravo chat and not in the REST API, and it needs `edit_posts`.

The instructions live in `includes/class-new-home-prompt.php`; change them there when the workflow changes.

## Changing an existing home

1. Claude calls `ketunkoti/update-home-meta` with the `home_*` fields to change (title, content, terms, photos and SEO go through the Seravo abilities above, with the same approval and edit-lock rules).
2. The MCP client, or the Seravo chat's approval card, asks you first. Nothing is written until you allow it, unless the tool is set to always allow in the MCP client.
3. The change is saved at once: on a published home it shows on the site immediately.

The server refuses the write, and changes nothing, when:

- another user has the home open in the editor (`ketunkoti_ai_post_locked`, the block editor's own edit lock; your own open editor does not block);
- any value is invalid (`ketunkoti_ai_invalid_value`);
- the post is not a home or a rental (`ketunkoti_ai_not_a_home`);
- you may not edit the home (the permission check fails).

`ketunkoti/trash-home` follows the same edit lock and `not_a_home` rules, with `delete_post` as the permission. It only moves homes to the trash: when the site has trash disabled (`EMPTY_TRASH_DAYS` 0) it refuses (`ketunkoti_ai_trash_disabled`) instead of deleting permanently. A home already in the trash is reported as trashed again, without changes.

Every successful `update-home-meta` call is logged on the home in the post meta `_ketunkoti_ai_meta_log`: one row per call with `user_id`, `changed` (Unix time), `before` (the stored values, read before the write; `null` when a field was not set) and `after` (the values written; `null` when a field was cleared).

Older rows, from before 1.1.0, have `proposal_id` and `applied` instead of `changed`.

## Tests

The tests run in the wp-env tests environment and clean up after themselves:

```bash
for t in fields read copy write; do
    npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-$t.php
done
```

Each prints one `PASS`/`FAIL` line per check and ends with `ALL PASSED`, or exits with status 1.
