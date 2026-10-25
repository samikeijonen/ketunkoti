# Vuokrakodit as their own post type, and yield calculations for sijoitusasunnot

Date: 2026-10-25
Status: Design agreed in conversation, builder check done. Awaiting approval.

Two related changes in `plugins/ketunkoti-features/` and `themes/ketunkoti/`:

1. A new custom post type for homes offered **for rent to tenants** (vuokrakodit).
2. Two new calculated figures, **kassavirta** and **oman pääoman tuotto**, plus one
   new input field, for **investment homes** (sijoitusasunnot, the existing `home`
   post type). Vuokratuotto already exists.

## Why

### Vuokrakodit

Sami (the owner) rents out homes as well as selling them. A rental listing talks to a
tenant, not a buyer or an investor. In Sami's words, the whole content of the page is
different from the same flat listed for sale as a sijoitusasunto.

What tenants ask about, per Sami: **vuokra, vakuus, vapautuminen, lemmikit ja kuvat**
(rent, deposit, when the flat becomes free, pets, photos).

When a rental home is let, it **stays visible with a "Vuokrattu" status**, the same
way sold homes stay visible as "Myyty".

### Yield calculations

On 24–25.10.2026 Sami and Claude worked through the numbers of an upcoming Lahti
investment flat by hand. The result: investors need more than vuokratuotto, they need
the monthly **kassavirta** and **oman pääoman tuotto**, calculated with exactly those
formulas (below). These figures are a key part of the "numbers in the open,
calculated by a former maths teacher" message in the site strategy
(`docs/strategia/`).

The housing company loan interest rate (taloyhtiölainan korko) varies per home, so
it is **a field per home**, not a site-wide setting (Sami's decision).

## This supersedes part of the 2026-09-13 design

`docs/superpowers/specs/2026-09-13-home-purpose-design.md` decided "No new custom post
types" and modelled rentals inside `home` through the `home-purpose` taxonomy. Its main
argument was the overlap case: one flat both for sale and for rent would become two
posts with duplicate photos and two URLs.

Sami's decision now: the content is entirely different even for the same flat, so
**the same flat for rent and for sale is simply two separate entries that share no
data**. The overlap cost is accepted.

What stays from the 2026-09-13 design:

- `home` keeps `home-purpose` (terms in use: Kodiksi, Sijoitusasunto).
- `home` keeps its rental fields (`home_rent`, `home_deposit`, `home_water_charge`,
  `home_other_charges`, `home_available_from`). An investment home is sold with an
  existing tenant, and the buyer needs that tenancy's rent and deposit. The Finnoo
  investment homes use them today.
- Sidebar panels are never shown conditionally on a term (reasons in that document).
  This design does change which panels show per **post type**, which is synchronous and
  has neither of those problems.

## Goals

- Sami can add a rental home in the admin under its own menu, fill in tenant-relevant
  fields, and publish a page with its own text, photos and key facts.
- Rental homes can be listed on a page with the same card grid and status badge as
  the investment homes.
- Investment home pages can show kassavirta and oman pääoman tuotto, calculated
  automatically from the fields Sami already fills in plus the new interest field.
- The editor sidebar shows the three figures live while editing, as it already does
  for vuokratuotto.

## Non-goals

- No link or shared data between a rental entry and a sale entry of the same flat.
- No rental application form, viewing booking or availability calendar. Contact goes
  through the existing Contact Form 7 form ("Ota yhteyttä").
- No yield figures on rental homes. A tenant does not need them.
- No post type archive page. Listing pages are ordinary pages with a Query Loop, as
  today.
- No migration. There are no rental homes in the database yet (checked 25.10.2026:
  three `home` posts, two Sijoitusasunto, one Kodiksi).
- No tax calculation, vacancy, renovation reserve or value change in the figures.

## Anti-goals

- A figure that is wrong, or right but unexplained, is worse than no figure. The
  investor audience compares numbers across sellers, and this site sells on accuracy.
  Every figure states what it is (see "Display").
- Empty or incomplete input must never render as a misleading "0 %" or "0 €/kk".

## Part 1: post type `rental`

### Registration

In `includes/post-types.php`, next to `home`, following the same structure and label
style.

- Post type key: `rental`. *(Claude's call. Short, English like `home`.)*
- Rewrite slug: `vuokrakoti`, so URLs are `/vuokrakoti/<nimi>/` (mirrors `/koti/`).
- Labels: Vuokrakodit / Vuokrakoti ("Lisää uusi vuokrakoti", etc.).
- `has_archive => false`, `show_in_rest => true`, and the same `supports` as `home`,
  including `custom-fields` (needed for meta in the block editor).
- Menu position right after "Kodit". Icon is the builder's choice.
- The activation hook already registers post types before flushing rewrite rules. The
  plugin is already active in production, so that hook will not run on deploy:
  **rewrite rules must be flushed once after deploying** (`wp rewrite flush`), or
  `/vuokrakoti/...` URLs will 404.

### Taxonomies

Attach these existing taxonomies to `rental` as well as `home`:

- `home-city` (Sijainti)
- `home-rooms` (Huoneiden lukumäärä)
- `home-type` (talotyyppi)
- `home-status`

Not `home-purpose`, which only describes sale homes.

**Status is shared on purpose** *(Claude's call)*. The status badge CSS
(`.home-card__status` in `style.css`) is deliberately term-agnostic and already names
"Vuokrattu" in its comment, and the `home_status` binding key reads `home-status`.
Sharing means the badge, the binding and the Query Loop filter work for rentals with
no new code. The cost is that the rental editor also lists Myynnissä and Myyty in the
status checkbox list. Terms are editorial (per the 2026-09-13 design): Sami creates
**"Vuokrattu"** himself in the admin. The plugin does not seed it.

### Meta fields

Register these existing keys for `rental` too, with the same type, sanitizer, label
and `meta_auth_callback`. Change the registration to loop over post types; don't
copy and paste it.

| Key | Label |
|---|---|
| `home_area` | Asuinpinta-ala |
| `home_room_layout` | Huoneistoselitelmä |
| `home_floor` | Kerros |
| `home_address` | Osoite |
| `home_year_built` | Rakennusvuosi |
| `home_rent` | Vuokra (€/kk) |
| `home_deposit` | Vakuus (€) |
| `home_water_charge` | Vesimaksu (€/hlö/kk) |
| `home_other_charges` | Muut kulut |
| `home_available_from` | Vapautuu |

Reusing the `home_` keys keeps every existing binding, formatter and sidebar control
working on `rental` posts. The prefix now means "a home listing" rather than "the
`home` post type". This should be said in a code comment where the keys are
registered.

**New field, `rental` only:**

| Key | Type | Sanitize | Label |
|---|---|---|---|
| `home_pets` | string | `sanitize_text_field` | Lemmikit |

Free text *(Claude's call)*, because the real answers are nuanced ("Sovittavissa",
"Pienet lemmikit sallittu", "Ei lemmikkejä"). The UKK currently answers the pets
question with "Riippuu kodista ja lemmikistä". Binding output is the text as entered,
like the other free text fields.

Not registered for `rental`: `home_selling_price`, `home_debt_free_price`,
`home_maintenance_charge`, `home_capital_charge`, `home_loan_interest`. Those are owner
costs and sale data, not tenant information.

### Bindings

`get_binding_value()` already resolves by `postId` from block context and reads any
registered key generically, so the existing sources (`ketunkoti/home-details` for the
general facts, `ketunkoti/home-rental` "Vuokratiedot" for rent, deposit, water, other
charges and availability) work for `rental` posts unchanged. Add `home_pets` to the
"Vuokratiedot" group, both in PHP and in the client-side source in `src/index.js`.

The builder should confirm nothing in `bindings.php` or `src/index.js` assumes
`postType === 'home'` for value resolution. Line 526 of `src/index.js` gates the
sidebar on `POST_TYPE`, which is expected and handled below.

### Editor sidebar (`src/index.js`)

The panels shown depend on the post type:

| Panel | `home` | `rental` |
|---|---|---|
| Kodin tiedot (pinta-ala, osoite, kerros, rakennusvuosi, huoneistoselitelmä) | ✅ | ✅ |
| Hinnat ja vastikkeet (velaton, myynti, hoitovastike, pääomavastike, **korko**) | ✅ | — |
| Vuokratiedot (vuokra, vakuus, vesimaksu, muut kulut, vapautuu) | ✅ | ✅ + **lemmikit** |
| Sijoituslaskelma (read-only figures) | ✅ | — |

Lemmikit is shown only for `rental`, because the field is not registered on `home`.

### Theme

- `templates/single-rental.html`: the same structure as `single-home.html` (header,
  `post-content`, footer). The facts block lives in post content, as with `home`.
- `patterns/rental-details.php`: "Vuokrakodin tiedot", `Post Types: rental`. It uses
  the same rendering helper and visual style as `home-details.php`. Facts, in this
  order: **Vuokra, Vakuus, Vapautuu, Lemmikit**, then Asuinpinta-ala, Huoneistoselitelmä,
  Kerros, Sijainti. Vesimaksu and Muut kulut are available as bindings for Sami to add
  where he wants; they are not in the default pattern *(Claude's call, to keep the key
  facts to what tenants asked about)*.
- `patterns/rentals-grid.php`: "Vuokrakodit-ruudukko", a copy of `homes-grid.php` with
  `postType: rental`. Same card, same status badge. The card's secondary line shows rent
  (`home_rent`) where the sale grid shows price. `homes-grid.php` itself is unchanged:
  it has no purpose filter in the pattern, and the "Myytävät sijoitusasunnot" page
  (186) sets its `home-purpose` filter in that page's own Query Loop block.
- **Vapautuu on a let rental** *(Claude's call)*: an empty `home_available_from` renders
  "Heti vapaa", so a let home with no date would say "Vuokrattu" on its card and
  "Heti vapaa" on its page. No code branches on the status term (that would be the
  first such logic, which the 2026-09-13 design avoids on purpose). Instead, when Sami
  marks a rental Vuokrattu he either enters the date the current lease can end, or
  removes the Vapautuu row from that page. The field's help text in the sidebar says
  so: "Tyhjä = heti vapaa. Kun koti on vuokrattu, lisää päivä tai poista rivi
  sivulta."
- "Ota yhteyttä": rental pages end with the existing Contact Form 7 form, inserted in
  post content as a shortcode, like the investment pages. The 2026-10-25 conditional
  CF7 loader (`includes/contact-form.php`) checks the queried post's content for the
  shortcode, so it already works for `rental` posts. Do not put the form in the
  template, or the loader will not see it.
- Creating the listing page itself ("Vuokrattavat kodit" or similar) and adding it to
  the navigation is content work for Sami in the editor, using `rentals-grid`.

## Part 2: yield figures for `home`

### New input field

| Key | Type | Sanitize | Label | Post type |
|---|---|---|---|---|
| `home_loan_interest` | number | `sanitize_positive_number` | Taloyhtiölainan korko (%) | `home` only |

Stored as a percentage, `4.3` meaning 4,3 %. This is the total nominal annual rate
(viitekorko + marginaali). `sanitize_positive_number` accepts Finnish "4,3". The field
goes in the "Hinnat ja vastikkeet" sidebar panel, after pääomavastike.

### Formulas

Inputs, all per month unless noted:

- `vuokra` = `home_rent`
- `hoitovastike` = `home_maintenance_charge`
- `paaomavastike` = `home_capital_charge`
- `myyntihinta` = `home_selling_price` (the investor's own capital)
- `velaton` = `home_debt_free_price`
- `korko` = `home_loan_interest` / 100 (annual)

Derived:

```
nettovuokra   = vuokra - hoitovastike
lainaosuus    = velaton - myyntihinta
kassavirta    = nettovuokra - paaomavastike                 (€/kk)
```

First year's interest is simulated month by month with the **actual pääomavastike as
the payment**, on a nominal monthly rate:

```
i = korko / 12
saldo = lainaosuus
korot = 0
repeat 12 times:
    kuukauden_korko = saldo * i
    korot += kuukauden_korko
    saldo -= paaomavastike - kuukauden_korko
```

This needs no loan term, and it follows the housing company's actual charge rather
than a theoretical annuity. The nominal convention (rate / 12) is how Finnish bank
loans are quoted. The same choice was made in conversation after comparing with
prosenttilaskuri.fi, which uses the effective-rate convention and gives a ~5 € lower
payment.

```
oman pääoman tuotto-% = (nettovuokra * 12 - korot) / myyntihinta * 100
```

This is the "repayments count as the investor's savings" definition: the repayment
part of pääomavastike reduces the flat's debt, so it is not a cost. It is the figure
Sami approved (10,6 % for the Lahti example).

Existing vuokratuotto is unchanged:
`((vuokra - hoitovastike) * 12) / velaton * 100`.

### When a figure is null (hidden, the row shows its em dash)

| Figure | Null when |
|---|---|
| kassavirta | `vuokra <= 0` |
| oman pääoman tuotto | `vuokra <= 0`, or `myyntihinta <= 0`, or (`lainaosuus > 0` and `paaomavastike > 0` and `korko` is empty) |

- An empty hoitovastike or pääomavastike counts as 0. This matches how
  `get_rental_yield()` treats an empty hoitovastike today.
- If `lainaosuus <= 0` or `paaomavastike <= 0`, there is no loan to pay interest on, so
  `korot = 0` and no interest rate is needed.
- If the pääomavastike is smaller than the monthly interest, the balance grows. The
  simulation handles this without a special case, and the figure is still shown.
- Negative results are real and are shown (as vuokratuotto already does).

### Output format

| Key | Example | Format |
|---|---|---|
| `home_cash_flow` | `+28 €/kk`, `−304 €/kk` | Whole euros, `number_format_i18n( $x, 0 )`, always signed, with a real minus sign (U+2212) for negatives, plus non-breaking spaces as elsewhere in the file. Rounds half away from zero. |
| `home_equity_return` | `10,6 %` | Same as vuokratuotto: one decimal, NBSP, `%`. |

Both go into `COMPUTED_FIELDS` and the "Sijoituslaskelma" (`ketunkoti/home-investment`)
bindings source, next to `home_rental_yield`. The `'home_rental_yield' === $key ? ...`
ternary in `get_binding_value()` becomes a small lookup or `match` over the three keys.

### Same arithmetic in JS

The sidebar's live "Sijoituslaskelma" panel shows all three figures. That puts the
formulas in both `includes/bindings.php` and `src/index.js`, as the 2026-09-13 design
already accepted for vuokratuotto. To keep them from drifting, both implementations
must pass the same test cases (below), and each has a comment pointing to the other.

### Test cases (must match exactly)

**Lahti, worked by hand on 25.10.2026:**
myyntihinta 26 000, velaton 138 725, vuokra 780, hoitovastike 151,30,
pääomavastike 600,72, korko 4,3.

- lainaosuus 112 725; first year's interest 4 800,07
- kassavirta **+28 €/kk** (27,98 unrounded)
- oman pääoman tuotto **10,6 %** (2 744,33 / 26 000)
- vuokratuotto **5,4 %**

**Finnoo kulmayksiö (post 358, live data):**
myyntihinta 22 000, velaton 166 000, vuokra 675, hoitovastike 106,40,
pääomavastike 872,90, no korko entered.

- kassavirta **−304 €/kk** (−304,30)
- oman pääoman tuotto **null** (loan and pääomavastike exist, korko missing)

**No loan:** myyntihinta = velaton = 100 000, vuokra 800, hoitovastike 200, no
pääomavastike, no korko.

- kassavirta **+600 €/kk**; oman pääoman tuotto **7,2 %** (7 200 / 100 000)

**No rent:** any home without `home_rent`, so both new figures are null.

### Display on investment pages

The home detail facts live in each post's content, not in a template, so a pattern
change does not update existing posts.

**New pattern `patterns/investment-figures.php`, "Sijoituslaskelma"** (Sami's
decision, 25.10.2026). It has `Post Types: home` and is inserted under the "Kodin
tiedot" block on Sijoitusasunto pages only. `home-details.php` is **not changed**,
because it is also used on Kodiksi (owner-occupier) pages, where investment rows and
a tax note would look broken.

- It uses the same rendering helper and visual style as `home-details.php`. The rows,
  all from the `ketunkoti/home-investment` ("Sijoituslaskelma") source, in this order:
  1. Vuokratuotto (`home_rental_yield`). No page shows it today; this is its first
     front-end use.
  2. Kassavirta (`home_cash_flow`)
  3. Oman pääoman tuotto (`home_equity_return`)
  4. Taloyhtiölainan korko (`home_loan_interest`)
- **Korko is shown** (Sami's decision), so the investor sees the assumption behind
  oman pääoman tuotto. It becomes a bindable key with a formatter case in both
  `bindings.php` and `src/index.js`. Format: up to two decimals with trailing zeros
  dropped, NBSP, `%` (`4,3 %`, `4,25 %`). Empty means null. It belongs to the
  "Sijoituslaskelma" source rather than "Hinnat ja vastikkeet", because it is shown
  with the figures it explains *(Claude's call)*.
- Directly under the rows, inside the pattern, one short explanatory line as editable
  paragraph text:
  *"Laskelmat ennen veroja. Oman pääoman tuotto on ensimmäisen vuoden tuotto
  myyntihinnalle, ja lyhennykset lasketaan sijoittajan varallisuudeksi."*
  This is the anti-goal above made concrete. Sami can edit the wording.
- Existing posts (358 Finnoo kulmayksiö, 319 the sold Finnoo yksiö): Sami inserts the
  pattern and enters the korko himself in the editor. The builder does not write post
  content.

## Constraints

- Plugin PHP follows the plugin's existing style: namespace `Ketunkoti`, 4-space
  indent, short arrays, PHPDoc on every function. Theme PHP follows the theme's
  style: tabs, `ketunkoti_` prefix.
- Plugin JS is built with `@wordpress/scripts` (`npm run build` in the plugin, after
  `nvm use`). The built `build/` output is committed, as today.
- WordPress 7.1, PHP 8.2+ (production runs 8.3).
- All user-visible strings are translatable (`ketunkoti-features` text domain in the
  plugin, `ketunkoti` in the theme), and in Finnish.
- Content changes (posts, terms, pages, nav) go to localhost only. Sami deploys code and
  makes production content changes himself.
- After deploy: `wp rewrite flush`, then purge the LiteSpeed cache
  (`wp litespeed-purge all`).

## Left to the builder

- Icon choices for the new pattern rows and the admin menu.
- Exact JS structure for per-post-type panels.
- Whether `rentals-grid.php` shares markup with `homes-grid.php` through a helper or is
  a straight copy (one copy is acceptable at this size).
- How the meta registration loop is organised.
- PHP and JS unit test tooling for the formulas, if any. At minimum, the four test
  cases must be verified (for example with `wp eval` against fixture posts locally) and
  reported.
