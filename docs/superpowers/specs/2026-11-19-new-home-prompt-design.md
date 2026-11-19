# "New home" MCP prompt

Date: 2026-11-19
Status: approved 2026-11-19

## Intent

Sami adds new homes by copying an existing listing. Instead of explaining the steps to
Claude every time, `ketunkoti-ai` offers them as an MCP prompt ("skill"), an idea taken
from Valu's agent skills. Iterate on the text later as real use shows what is missing.

## Approach (chosen)

A read-only ability `ketunkoti/new-home` registered as an MCP prompt
(`meta.mcp.type = prompt`), with its text in code. The MCP Adapter discovers MCP-public
prompt abilities on the default server automatically; `@automattic/mcp-wordpress-remote`
forwards prompts to Claude Code (slash command) and Claude Desktop ("+" menu).

Rejected: text editable in wp-admin (premature); an MCP resource (Claude would have to
know to fetch it).

## Design

- Arguments (optional): `source` (ID or search words of the home to copy), `details`
  (free text about the new home).
- Returns `{ text }`: steps list-homes / get-home → copy-home (check notes) →
  update-home-meta (related-field notes) → upload-media with Finnish alt text →
  list-taxonomies / list-terms (create-term only after the user agrees) → one
  update-post with title, content, terms and featured image (Finnish, keep the source's
  structure and tone, never publish) → get-seo / update-seo → leave as draft, give the
  edit link and a summary.
- Without Seravo AI Assistant (no `seravo-ai-assistant/update-post`): the Seravo steps are
  replaced by a list of wp-admin steps.
- Meta: annotations t/f/t; `public` false (not a Seravo chat tool), `show_in_rest` false,
  `mcp.public` true, `mcp.type` prompt; not in `Registrar::NAMES` (not a tool).
  `Registrar::register()` gains `$mcp_type` (`tool` | `prompt`; prompts must be read-only).
- Permission: `edit_posts`.

## Verification

`tests/php/test-prompt.php` (meta, not a tool, text names the tools and passes the
arguments, never-publish line, no-Seravo variant, execute + subscriber refused);
`test-read.php` compares the tool list with tool-type abilities only. Live: STDIO MCP
`prompts/list` shows `ketunkoti-new-home` with `source` and `details`; `prompts/get`
returns the text; it is not in `tools/list`.
