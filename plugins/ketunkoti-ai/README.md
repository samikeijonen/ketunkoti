# Ketun koti AI

Home abilities for Claude. An add-on for [MEOM AI Assistant](../meom-ai-assistant/README.md) that lets Claude on your computer (through MCP) read, copy and fill in home listings: the `home` (for sale and investment flats) and `rental` post types of Ketun koti Features.

MEOM AI Assistant already lets Claude write the title, content, excerpt, featured image and terms of its own drafts, and upload images. This plugin adds what is specific to homes: copying a listing and writing its `home_*` fields.

## Requirements

- WordPress 7.1+, PHP 8.2+.
- MEOM AI Assistant and Ketun koti Features active (`Requires Plugins`). Without the assistant the plugin registers nothing.
- For Claude on your computer: the MCP setup described in the assistant's README.

The abilities follow the assistant's access rules: the user needs the assistant's capability (`meom_ai_capability`), the assistant must be enabled, and the user must be able to edit the home in question.

## Abilities

| Ability | Input | What it does |
|---|---|---|
| `ketunkoti/list-homes` | `post_type`, `status`, `search`, `limit`, `offset` (all optional) | Lists homes and rentals: id, type, title, status, city, rooms. |
| `ketunkoti/get-home` | `id` | Returns the whole listing: post fields, featured image, every `home_*` field with label, type, description and value, terms per taxonomy, the calculated investment figures (read-only) and `ai_owned`. |
| `ketunkoti/copy-home` | `id`, optional `title` | Copies a listing into a new draft: content, excerpt, menu order, template, featured image, every `home_*` field and every term. SEO meta is not copied. The default title is the source title with " (kopio)". The draft is Claude's own. |
| `ketunkoti/update-home-meta` | `id`, `meta` | Writes `home_*` fields directly on a draft Claude created. |
| `ketunkoti/propose-home-meta` | `id`, `meta` | Proposes `home_*` changes to any other home. Changes nothing; returns the before and after values. |
| `ketunkoti/apply-home-meta` | `proposal_id` | Applies a proposal after the user said yes. |
| `ketunkoti/discard-home-meta` | `proposal_id` | Drops a proposal. |
| `ketunkoti/trash-home` | `id` | Moves a draft Claude created to the trash, for example a test copy. Any other home is refused; the editor trashes it in wp-admin. Never deletes permanently. |

### Notes

Some responses carry `notes`, things Claude should check with the user. Nothing is changed because of them.

- `copy-home`: the copy's title is already used by another home or rental. Two listings with one title look alike in lists, and the later one gets a `-2` slug.
- `update-home-meta` and `propose-home-meta`: a field changed without the field that usually changes with it. Vuokra without Vakuus, and Velaton hinta and Myyntihinta without each other. A related field sent in the same call, even with its current value, counts as checked.

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
4. `meom-ai/update-ai-draft` for the title, content and terms, `meom-ai/upload-media` for the images.
5. The editor opens the draft, checks it and publishes.

A test copy that is no longer needed goes to the trash with `ketunkoti/trash-home`.

A draft stays Claude's own until someone saves it by hand. After that, it counts as an existing home.

## Changing an existing home

Changes to any home Claude did not create, published or not, always need approval:

1. Claude calls `ketunkoti/propose-home-meta` and shows you each change as "label: before → after".
2. You say yes or no in the conversation.
3. Claude calls `ketunkoti/apply-home-meta`, or `ketunkoti/discard-home-meta`.

The server refuses to apply when:

- the proposal is someone else's (`ketunkoti_ai_not_your_proposal`),
- it is older than 30 minutes, or already applied or discarded (`ketunkoti_ai_proposal_not_found`),
- any of its fields changed after the proposal (`ketunkoti_ai_stale`),
- the user has **Allow applying changes from Claude on my computer** off in their profile (`ketunkoti_ai_apply_disabled`). This is the assistant's own switch. Proposing is refused too while it is off, because there is no chat card to apply the proposal from.

Title, content, excerpt, featured image and terms of existing homes go through the assistant's own `meom-ai/propose-change`.

Every applied proposal is logged on the home in the post meta `_ketunkoti_ai_meta_log`, one row per change with the user, time and the before and after values.

## Tests

The tests run in the wp-env tests environment and clean up after themselves:

```bash
for t in fields read copy proposals; do
    npx wp-env run tests-cli wp eval-file wp-content/plugins/ketunkoti-ai/tests/php/test-$t.php
done
```

Each prints one `PASS`/`FAIL` line per check and ends with `ALL PASSED`, or exits with status 1.
