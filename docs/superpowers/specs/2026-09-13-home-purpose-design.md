# Sijoitusasunnot ja vuokra-asunnot: content model design

Date: 2026-09-13
Status: Plugin side implemented. Theme patterns and listing pages approved but deferred.

## Decision

Keep the single `home` post type. Add one new taxonomy, `home-purpose`, five new
meta fields, and one computed binding value for vuokratuotto.

No new custom post types.

## Context

The current model, in `plugins/ketunkoti-features/`:

- One CPT `home` (slug `koti`, `has_archive => false`).
- Four hierarchical taxonomies: `home-city`, `home-status`, `home-type`,
  `home-rooms`. `home-status` and `home-type` are registered but referenced
  nowhere in the theme. `home-type` is intended for talotyyppi (kerrostalo,
  rivitalo, omakotitalo), so it is a different axis from the one added here.
- Nine meta fields, all registered in `includes/meta.php`.
- One block bindings source, `ketunkoti/home-details`, in `includes/bindings.php`.
- A hand-written editor sidebar in `src/index.js`.
- Patterns `home-details.php` and `homes-grid.php`. `single-home.html` contains
  only `post-content`, so `home-details` is inserted into each post's content
  rather than pulled in by the template.

## Why one post type

A listing is normally one of myytävä / vuokrattava / sijoitusasunto, but the
categories overlap: a flat for sale can also be marketed as an investment, and a
rental can later go up for sale.

Every structural field is shared between the three: address, area, rooms, floor,
year built, building type, city. The genuinely new data is five fields.

Separate CPTs would triplicate nine meta registrations, the bindings source, the
editor sidebar, `single-home.html` and both patterns, in order to isolate those
five fields. The overlap case would break outright: one flat would become two
posts with duplicate photos, duplicate copy, and two URLs competing in search.

A `home_purpose` meta field was also rejected: it loses Query Loop filtering
(which filters by taxonomy, not meta, without custom `WP_Query` code), loses the
admin list-table filter dropdown, and handles multiple values awkwardly.

## Data model

### Taxonomy `home-purpose`

Registered in `includes/taxonomies.php` through the existing
`get_shared_taxonomy_args()` and `get_taxonomy_labels()` helpers. The current
defaults fit as they are, so neither helper changes.

Labels: Käyttötarkoitukset / Käyttötarkoitus.

`hierarchical => true`, matching the other taxonomies. Not for nesting: that flag
is what produces a checkbox list in the editor. A flat taxonomy would give the
tag-style token input, which is wrong for a small fixed vocabulary and invites
typo'd duplicates.

### Terms are editorial, not seeded

An earlier version of this design fixed three terms in code (`myynnissa`,
`vuokrattavana`, `sijoitusasunto`) and created them on activation, on the
grounds that the sidebar and the listing patterns would both branch on those
slugs. Neither consumer exists: the sidebar shows every panel unconditionally,
and a listing page picks its term through the Query Loop UI rather than through
code.

With no consumer, seeding is worse than doing nothing. Terms are database rows,
so a name passed through `__()` is frozen in whichever locale was active at
activation and cannot follow a later language switch. Renaming the term after
the fact leaves a slug that still reads `myynnissa` while the name says
something else, which is more misleading than an honest auto-generated slug.

So the plugin registers the taxonomy and stops there. Editors create the terms
they need, name them as they like, and pick them in the Query Loop.

### New meta fields

All on `home`, all reusing the existing `meta_auth_callback`.

| Key | Type | Sanitize | Label |
|---|---|---|---|
| `home_rent` | number | `sanitize_positive_number` | Vuokra (€/kk) |
| `home_deposit` | number | `sanitize_positive_number` | Vakuus (€) |
| `home_water_charge` | number | `sanitize_positive_number` | Vesimaksu (€/hlö/kk) |
| `home_other_charges` | string | `sanitize_text_field` | Muut kulut |
| `home_available_from` | string | new `sanitize_date` | Vapautuu |

`sanitize_positive_number` is reused deliberately: it is the one that survives
Finnish "1 285,50" notation.

`home_other_charges` is free text so it can name the cost ("autopaikka 25 €/kk").
It therefore cannot be summed into a monthly total.

`home_available_from` stores an ISO `YYYY-MM-DD` string. A new `sanitize_date()`
validates it with `DateTimeImmutable::createFromFormat` and returns `''` on
anything malformed. Empty means the flat is available now. This beats free text:
the sidebar gets a real date picker, and the value stays sortable.

## Computed values

`get_binding_value()` reads `get_post_meta()` and returns `null` for an empty
value before its switch, so a computed key with no meta row of its own can never
reach the switch. Computed keys are resolved earlier, where `TAXONOMY_FIELDS` is
already handled:

```php
const COMPUTED_FIELDS = [ 'home_rental_yield' ];
```

resolved just after the taxonomy block, delegating to its own
`get_rental_yield( int $post_id ): ?string`. The arithmetic stays in one place
rather than buried in a switch arm.

### Vuokratuotto

```
vuokratuotto-% = ((vuokra - hoitovastike) * 12) / velaton hinta * 100
```

Net yield, transfer tax excluded.

Returns `null` -- so the row falls back to its em dash rather than printing
"0 %" -- when `home_debt_free_price <= 0` or `home_rent <= 0`. A negative result
(hoitovastike above rent) is returned as-is; it is real, and suppressing it would
misrepresent the listing.

Formatted as `number_format_i18n( $yield, 1 )` plus a non-breaking space plus
`%`, giving "4,2 %". This matches Finnish typography and the comma decimal the
rest of the file already produces.

Two deliberate exclusions:

- **Pääomavastike is not subtracted.** The denominator is velaton hinta, which
  already includes the flat's share of taloyhtiö debt. Subtracting the charge
  that services that debt would count it twice and understate the yield.
- **Vesimaksu is not subtracted.** The tenant pays it on top of rent, so it is
  not a landlord cost.

### New binding keys

| Key | Renders |
|---|---|
| `home_rent` | `1 250 €/kk` |
| `home_deposit` | `1 250 €` |
| `home_water_charge` | `25 €/hlö/kk` |
| `home_other_charges` | free text, as entered |
| `home_available_from` | `1.6.2026`, or "Heti vapaa" when empty |
| `home_rental_yield` | `4,2 %`, computed |
| `home_purpose` | term names, added to `TAXONOMY_FIELDS` |

`home_available_from` is the one exception to the "empty means null" rule: empty
means available now, which is information rather than absence. It is resolved
alongside the computed fields for that reason.

The repeated `sprintf( __( '%s €...' ) )` formatting is extracted into
`format_price()`, `format_monthly_charge()` and `format_date()`. The switch grows
from nine keys to fourteen and is already the longest thing in the file.

## Editor sidebar

The fields are grouped into four always-visible panels:

| Panel | Fields |
|---|---|
| Kodin tiedot | pinta-ala, osoite, kerros, rakennusvuosi, huoneistoselitelmä |
| Hinnat ja vastikkeet | velaton hinta, myyntihinta, hoitovastike, pääomavastike |
| Vuokratiedot | vuokra, vakuus, vesimaksu, muut kulut, vapautuu |
| Sijoituslaskelma | read-only vuokratuotto |

### Panels are not shown conditionally

Showing each panel only for its matching purpose term was designed, built and
then removed. Two reasons:

- The terms resolve asynchronously through `getEntityRecords`, so every panel
  rendered first and two of them disappeared once the terms arrived. Panels
  vanishing a moment after load reads as a glitch.
- A hidden field keeps its value. Meta entered under one purpose survives the
  purpose being removed, and is still bound and still rendered, so hiding the
  control makes "removed from the editor" look like "removed from the site".

Panels are collapsible and WordPress remembers each user's collapsed state, so
the decluttering the conditional version was meant to buy is already available
without hiding anything.

Because every panel is always visible, no field is repeated across panels: the
investment panel holds only the calculated figure, and the inputs that feed it
live in the two panels above it. "Hinnat ja vastikkeet" rather than
"Myyntitiedot", since it holds velaton hinta for a home that is only ever a
sijoitusasunto.

### Duplicated formula

The live vuokratuotto in the sidebar means the yield arithmetic exists in both
`includes/bindings.php` and `src/index.js`, and the two can drift.

Accepted deliberately: it is one line of arithmetic, unlikely to change, and the
live editor feedback is the point of a sijoitusasunto. Each copy carries a
comment naming the other. The alternatives were omitting it from the editor (no
drift, but loses the number that matters most) or exposing it as a REST field
(correct, but only refreshes after save, which loses the live feedback).

## Patterns and listing pages

Deferred. The design below stands, but none of it is built yet: the new
meta is enterable and bindable, and reaches the front end only where an
editor binds a block to it by hand.

### Detail patterns, one per purpose

Two siblings are added to the existing pattern, all three reusing the same
`$ketunkoti_home_detail` closure shape and all carrying `Post Types: home`:

| Pattern | Rows |
|---|---|
| `home-details` (existing, retitled "Kodin tiedot - myynti") | myyntihinta, velaton hinta, pinta-ala, sijainti |
| `home-details-rental` | vuokra, vakuus, vesimaksu, vapautuu, pinta-ala, sijainti |
| `home-details-investment` | vuokratuotto, velaton hinta, vuokra, hoitovastike, pinta-ala, sijainti |

The editor inserts the one that matches; the overlap case inserts two, the same
gesture as ticking two purpose terms.

Per-post branching inside a pattern file is not possible -- pattern files are
included by the registry at `init`, before `$post` exists -- and is not needed
here, because `single-home.html` contains only `post-content` and the pattern is
inserted into each post's content.

Bound paragraphs already carry `&#8212;` as fallback content, so a binding
returning `null` renders an em dash rather than a blank.

This keeps the work inside the pattern-plus-bindings architecture already in
place, rather than introducing the project's first custom block for five fields.

### Grid patterns

`homes-grid` hardcodes `home_selling_price`, which is an em dash on every rental.
Two siblings, each with its Query Loop pre-filtered by `taxQuery`:

- `homes-grid-rentals` -- card shows `home_rent`
- `homes-grid-investments` -- card shows `home_rental_yield`

Each grid picks its term through the Query Loop UI, not through code. Since the
terms are editorial rather than seeded, a pattern cannot assume any particular
slug exists, and a hardcoded term ID would be worse still: IDs differ between
local, staging and production, so a literal would silently return zero results
after deploy.

That makes a per-purpose grid pattern a thin thing -- a Query Loop whose term the
editor sets once on the page. Whether that earns a registered pattern at all, or
is simply built on the page, is a decision for whoever picks this up.

### Listing pages

`/vuokra-asunnot/` and `/sijoitusasunnot/` are ordinary WordPress pages with the
matching grid pattern inserted. No rewrite rules, and the taxonomies keep
`public => false`.

## Files touched

- `plugins/ketunkoti-features/includes/taxonomies.php` -- register `home-purpose`
- `plugins/ketunkoti-features/includes/meta.php` -- five fields, `sanitize_date()`
- `plugins/ketunkoti-features/includes/bindings.php` -- `COMPUTED_FIELDS`,
  `get_rental_yield()`, new keys, extracted formatters
- `plugins/ketunkoti-features/src/index.js` -- four panels, purpose term reading
- `themes/ketunkoti/patterns/home-details.php` -- retitle
- `themes/ketunkoti/patterns/home-details-rental.php` -- new
- `themes/ketunkoti/patterns/home-details-investment.php` -- new
- `themes/ketunkoti/patterns/homes-grid-rentals.php` -- new
- `themes/ketunkoti/patterns/homes-grid-investments.php` -- new

## Out of scope

- `home-status` and `home-type` stay registered and unused. Wiring them up is
  separate work.
- Remonttivaraus and tehdyt remontit were considered and dropped.
- Varainsiirtovero (1,5 %) is not modelled; the chosen yield formula excludes it.
