# Salkku: private apartment portfolio in wp-admin

Date: 2026-11-22
Status: Design agreed in conversation, builder check done. Awaiting approval.

## Why

Sami tracks the apartments he owns in an Excel workbook, with one sheet for
personally owned flats and one for flats owned by his company. He wants
ketunkoti.fi to be **the hub of all data**, and to see the same results as the
Excel, easily:

- Vapaata vakuutta yht.
- Velat yhteensä
- Nettovarallisuus
- Asuntojen arvo yht.
- Velkavipu

plus a table of the apartments like the one in the Excel.

## Goals

- One private record per owned apartment, holding the Excel's fields.
- A wp-admin page "Salkku" that shows the five totals and the apartment table,
  per owner (personal, company) and combined.
- Abilities so Claude can read the portfolio and add or update apartments.
- First data entry happens **locally** (wp-env), from the Excel.

## Non-goals

- No public output of any kind: no front-end page, no block, no binding, no
  schema, no sitemap entry, no REST route for the post type.
- No history of values over time (snapshots, charts). Later, if wanted.
- No import from Excel files. The one-off local data entry is done with the
  create ability (below), which also exercises it.
- The workbook's other sheets (purchase calculations and similar) are out of
  scope.
- No production rollout in this work. Copying the data to production is a
  separate, later step.

## Anti-goals

- **Leaking the data.** Exact addresses, loans and free
  collateral must never reach a public page, a public REST response, the
  front-end HTML, a search result or a feed. A design that works but leaves any
  such path open is a failure.
- **Portfolio data in git.** The repository is on GitHub. No real figures or
  addresses in code, tests, fixtures, docs or commit messages. Tests use
  invented numbers.

## Approaches

**A. Separate private post type (picked by Sami).** One `portfolio` post per
apartment, closed to the public at registration level, with its own meta. An
optional link to the public `home` listing when the apartment is for sale.
Trade-off: a few basic facts (area, year, floor) are typed twice when a flat is
also listed for sale.

**B. Portfolio fields on the existing `home` posts.** One record per
apartment. Rejected: `home` is public and its meta flows into bindings, home
schema and the home abilities, so financial data would sit one mistake away
from a public page. Rentals are a separate post type anyway, so "one record per
apartment" would not hold.

**C. Keep the Excel as the source and read it.** Rejected: the opposite of the
site being the hub.

## Design

### Where the code goes

- `plugins/ketunkoti-features/includes/portfolio.php`: post type, taxonomy,
  meta, edit screen meta box.
- `plugins/ketunkoti-features/includes/portfolio-calculator.php`: the
  calculations, used by both the admin page and the abilities. One source of
  truth for every formula.
- `plugins/ketunkoti-features/includes/portfolio-admin.php`: the Salkku
  overview page.
- `plugins/ketunkoti-ai/includes/class-portfolio-abilities.php`: abilities,
  registered through the existing `Registrar`.

Follows the existing patterns: namespaced functions in `ketunkoti-features`,
final classes in `ketunkoti-ai`, Finnish UI strings, `kala`-style PHPCS (4
spaces).

### Post type `portfolio`

Labels: Salkku (menu), Asunnot / Asunto. Menu icon `dashicons-portfolio`.

Registration closes every public path:

- `public => false`, `publicly_queryable => false`, `exclude_from_search =>
  true`, `query_var => false`, `rewrite => false`, `has_archive => false`.
- `show_in_rest => false`. The block editor is not used; the classic edit
  screen with a meta box is enough for 15 numbers. This keeps the post type out
  of `/wp/v2/` entirely.
- `show_ui => true`, `show_in_menu => true`, `show_in_nav_menus => false`,
  `show_in_admin_bar => false`.
- `supports => [ 'title', 'revisions' ]`. Title is the apartment's short name,
  as in the Excel header (for example "Kaksio" plus the area name).
- Capabilities: every capability maps to `manage_options`. Editors see
  nothing.

**Access for the AI user (Sami's decision).** Claude's MCP connection logs in
as the user `samikeijonen-ai`, which is an Editor today, so it would be
refused. Sami chose to make that user an **administrator** rather than add a
custom capability. This is a site setting, not code: locally it is one WP-CLI
command (`wp user set-role samikeijonen-ai administrator` in wp-env) before
data entry; on production it is done by hand at rollout. The code keeps
`manage_options` everywhere.
- `can_export => true`, so a WordPress export (admin only) still works as a
  backup.

### Taxonomy `portfolio-owner`

Separates personal and company apartments (Sami's request). Registered for
`portfolio` only.

- Hierarchical (checkbox list in the meta box UI), `public => false`,
  `publicly_queryable => false`, `show_in_rest => false`, `rewrite => false`,
  `show_admin_column => true`, `show_ui => true`, same `manage_options`
  capabilities.
- Labels: Omistajat / Omistaja.
- Terms are content, not seeded from code (the convention in
  `taxonomies.php`). Initial terms, created during data entry: one for personally
  owned flats and one for the company.
- An apartment has exactly one owner term. The edit screen and the abilities
  enforce one; an apartment with none is shown under "Ei omistajaa" on the
  overview so it is never silently left out of totals.

### Meta fields

All registered with `register_post_meta( 'portfolio', ... )`, `single => true`,
`show_in_rest => false`, auth callback `current_user_can( 'manage_options' )`.
Keys are prefixed `portfolio_`. Numbers reuse the existing sanitizers in
`meta.php` (`sanitize_positive_number`, `sanitize_year`, the ISO date
sanitizer), so Finnish input like "1 285,50" works.

| Key | Label (fi) | Type | Excel row |
|-----|------------|------|-----------|
| `portfolio_address` | Osoite | string | Osoite |
| `portfolio_area` | Pinta-ala (m²) | number | Pinta-ala |
| `portfolio_year_built` | Valmistunut | integer | Valmistunut |
| `portfolio_floor` | Kerros | string, e.g. "4/8" | Kerros |
| `portfolio_debt_free_price` | Ostohinta (velaton) | number | Ostohinta (velaton) |
| `portfolio_company_loan` | Yhtiölainaa | number | Yhtiölainaa |
| `portfolio_company_loan_checked` | Yhtiölainan tarkastuspäivä | date | Yhtiölainan tarkastus pvm |
| `portfolio_bank_loan` | Pankkilainaa | number | Pankkilainaa |
| `portfolio_used_collateral` | Käytetyt vakuudet | number | Käytetyt vakuudet |
| `portfolio_rent` | Vuokra + vesi (€/kk) | number | Vuokra + vesi |
| `portfolio_charges` | Vastikkeet (€/kk) | number | Vastikkeet |
| `portfolio_estimated_value` | Arvioitu arvo | number | Arvioitu arvo |
| `portfolio_collateral_rate` | Vakuusarvo-% | number, default 75 | (75 % or 80 % inline in formulas) |
| `portfolio_home_id` | Myynti-ilmoitus | integer, `home` post ID or 0 | (new) |

The collateral rate is a field because the Excel mixes 75 % and 80 % per flat.
An empty value means 75.

`portfolio_floor` stays a string because the Excel holds "1/4"; one Excel cell
holds a date serial (44964) where a floor was meant, which data entry fixes by
hand.

### Calculations (`portfolio-calculator.php`)

Per apartment, exactly as the Excel:

- Velat yhteensä = yhtiölaina + pankkilaina
- Nettoarvo = arvioitu arvo − velat yhteensä
- Vakuusarvo = arvioitu arvo × vakuusarvo-% / 100
- Vapaata vakuutta = max(0, vakuusarvo − velat yhteensä − käytetyt vakuudet)
  (owner's decision: never negative)

Per group (each owner term, and all apartments combined):

- Vapaata vakuutta yht. = sum of vapaata vakuutta, each apartment floored at 0
  before summing
- Velat yhteensä = sum
- Nettovarallisuus = sum of nettoarvo
- Asuntojen arvo yht. = sum of arvioitu arvo
- Velkavipu = velat yhteensä / asuntojen arvo yht., shown as a percentage; "–"
  when the value sum is 0
- Also summed for the table footer: rent and charges per month, debt-free
  purchase price.

Totals always include every published or private `portfolio` post in the
group. Drafts, pending and scheduled posts are left out (and listed), and
trashed posts are ignored, which gives a way to keep a planned purchase out of
the totals.

The calculator is pure: it takes arrays of field values and returns arrays of
results, with no WordPress calls, so it is unit tested without a database. A
thin function loads the posts and feeds it.

### Edit screen

Classic edit screen: title, one meta box "Asunnon tiedot" with the fields in
the table order, grouped under small headings (Perustiedot, Velat, Vuokra ja
vastikkeet, Arvo ja vakuudet, Myynti-ilmoitus), the owner as radio buttons
inside the same box (replacing the default taxonomy box), and a read-only line
under the fields showing the calculated nettoarvo, vakuusarvo and vapaata
vakuutta for the saved values. Nonce, `manage_options` check, autosave and
revision guards on save. `portfolio_home_id` is a select of `home` posts (any
status), with "Ei ilmoitusta" as the empty choice.

### Salkku overview page

A submenu page "Yhteenveto" under the Salkku menu, first in the menu, rendered
in PHP. Capability `manage_options`.

```
Salkku – yhteenveto                         Päivitetty: tiedot tallennushetken mukaan

           Asuntoja  Vapaata vakuutta   Velat    Nettovarallisuus   Asuntojen arvo   Velkavipu
Omistaja A    n       xx xxx €      x xxx xxx €    xxx xxx €        x xxx xxx €      xx,x %
Omistaja B    ...
Yhteensä      ...

Omistaja A
Asunto | Osoite | m² | Valm. | Kerros | Ostohinta | Yhtiölaina (tark.) | Pankkilaina |
Velat | Vuokra | Vastikkeet | Arvioitu arvo | Nettoarvo | Vakuus-% | Vakuusarvo |
Käytetyt vakuudet | Vapaata vakuutta
...rows...
Yhteensä row

Omistaja B
...same table...
```

- Summary table first (rows: each owner term by name, "Ei omistajaa" if any,
  then the combined "Yhteensä" row last, with an "Asuntoja" count column), then
  one apartment table per owner term.
- Money in Finnish format: `number_format_i18n`-style with a non-breaking space
  as thousands separator and " €" (whole euros in the tables, cents only in
  the edit screen). Free collateral is never negative (floored at 0 per
  apartment), so there is no warning styling for it.
- Apartment name links to its edit screen. A loan-check date older than 12
  months gets a small "tarkista" marker, since the housing company loan
  changes every month.
- A real `<table>` with `<caption>`, `<th scope>`; horizontal scroll on narrow
  screens. Admin styles inline in a small enqueued CSS file for this page only.

### Abilities (in `ketunkoti-ai`)

Registered through `Registrar::register()` and added to `Registrar::NAMES`.
All have permission `manage_options`. Writes log before/after values in a
private meta `_ketunkoti_ai_portfolio_log`, like the home abilities do.

- `ketunkoti/get-portfolio` (read-only): the five totals per group and every
  apartment with all fields, its owner and calculated figures. Optional
  `owner` filter (term slug).
- `ketunkoti/create-portfolio-apartment` (write, not destructive): title,
  owner (term name or slug; the term is created if it does not exist) and any
  of the fields. Creates a **published** post, since the post type has no
  public side. Returns the saved values. Used for the local data entry.
- `ketunkoti/update-portfolio-apartment` (write, destructive, idempotent): id
  and any of the fields and owner. Same "show label: before → after, then ask"
  instruction in the description as `update-home-meta`. One invalid value
  refuses the whole call. Refused while another user holds the edit lock.

No trash ability: removing an apartment is rare and done in wp-admin.

**MCP only (Sami's decision).** The portfolio abilities are exposed to MCP
clients (Claude Code / Desktop) and **not** to the Seravo AI Assistant chat in
wp-admin, whose tools go to whichever AI provider Seravo is configured with.
Today `Registrar::register()` sets `meta.public => true` and `show_in_rest =>
true` on every tool, and the Seravo chat takes every ability with
`meta.public => true` (`class-agent.php`, `get_available_abilities()`). So the
Registrar gets an option to register a tool as MCP-only: `meta.public =>
false` and `show_in_rest => false`, still listed in `NAMES` so
`add_tools()` keeps it on the default MCP server, with `mcp.public` set as for
other tools. The existing home abilities keep their current exposure. A test
asserts that the three portfolio abilities are absent from
`wp_get_abilities( [ 'meta' => [ 'public' => true ] ] )` and present in the MCP
server's tool list.

Seravo's own content abilities cannot reach `portfolio` posts: they only
accept post types that are both `public` and `show_in_rest`
(`Abilities::public_post_types()`), checked during the builder review.

### Data entry (local only)

After the code works locally and the AI user is an administrator, Sami's
apartments are entered with `ketunkoti/create-portfolio-apartment` through the
local MCP server, values read from the Excel, then compared against the
Excel's totals. The data lives only in the local database; nothing is written
to the repository.

**Flats not yet completed (Sami's decision)** are left out of the Excel's
totals. Such a flat is entered as a **draft**, so it is saved but stays out of
the totals until Sami publishes it. The create
ability therefore takes an optional `status` (`publish`, the default, or
`draft`). The overview lists drafts in a small "Luonnokset (ei mukana
summissa)" line under each owner's table, so a draft is never invisible.

The expected local result is the Excel's totals, per owner.

### Tests

- Unit tests for the calculator with invented numbers: per-apartment figures,
  an over-used apartment whose free collateral floors at 0 in the group sum, empty collateral rate defaulting
  to 75, leverage with zero value.
- Integration tests (wp-env tests site, existing `tests/php` setup in
  `ketunkoti-ai`) for the abilities: permissions (a non-admin is refused),
  create with a new owner term, update logging, one-owner rule.
- Privacy checks: the post type is not in `/wp/v2/types` output for a
  logged-out request, a `portfolio` post URL with `?p=ID&post_type=portfolio`
  returns 404 for a logged-out visitor, and the post type is absent from the
  core sitemap index.

## Decided by Sami

- Approach A, a private post type named Salkku.
- A taxonomy separates personal and company apartments.
- Abilities, yes.
- Data is entered locally first.
- The AI user `samikeijonen-ai` becomes an administrator (no custom
  capability).
- Portfolio abilities are MCP-only, not in the Seravo chat.
- A flat not yet completed is a draft until completed.

## Calls made in this design (Claude)

- Post type key `portfolio`, taxonomy `portfolio-owner`, meta prefix
  `portfolio_`.
- Classic edit screen with a meta box, REST off for the post type.
- `manage_options` for everything.
- Collateral rate per apartment, default 75 %.
- Drafts excluded from totals.
- Free collateral is floored at 0 per apartment, so the total never includes negatives.
- Optional link to a `home` listing.
- "tarkista" marker for loan checks older than 12 months.

## Left to the builder

- Exact markup and CSS of the overview page within the sketch above.
- Order and wording of help texts in the meta box.
- Test file layout.
