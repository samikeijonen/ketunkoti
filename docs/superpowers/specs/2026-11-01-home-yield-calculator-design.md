# Yield calculator block on the home page (vuokratuottolaskuri)

Date: 2026-11-01
Status: Design agreed in conversation, builder check done. Awaiting approval.

A new plugin for custom blocks, `plugins/ketunkoti-blocks/`, with its first block: an
interactive yield calculator on a single home (`home` post type) page. The calculator
is prefilled with the home's own figures and lets the visitor try out their own bank
loan.

## Why

Investors who look at a sijoitusasunto want to know what the numbers look like **for
them**, with their own money and their own loan. The listing already shows
vuokratuotto, kassavirta and oman pääoman tuotto (see
`2026-10-25-vuokrakodit-ja-tuottolaskelmat-design.md`). Those figures assume the buyer
pays the whole myyntihinta with their own money. The calculator adds the visitor's
bank loan.

Reference: [asuntokauppa.com/vuokratuottolaskuri](https://asuntokauppa.com/vuokratuottolaskuri/).
Sami asked for something like it but much leaner. The full standalone calculator
(more inputs, its own page) is **a later project**. This one is the small calculator
on the home page.

## Goals

- A block on the single home page, prefilled from that home's meta.
- The visitor can change four things: **vuokra, laina-aika, korko, omarahoitusosuus (%)**.
- Three results, updated live as the visitor types: **kassavirta, vuokratuotto,
  oman pääoman tuotto**.
- The korko field is prefilled with **12 kk Euribor + 0,6 % marginaali**. The
  Euribor rate is fetched from the ECB and cached for a day.

## Non-goals (for now)

- Varainsiirtovero. Sami left it out on purpose and will come back to it later.
- Taxes, rent increases, vacant months, interest-rate risk scenarios, renovation
  reserves, tasalyhennys or bullet loans.
- A breakdown of the rate (e.g. "Euribor 3,25 % + marginaali 0,6 %"). Sami said it
  is not needed. Only the single korko value is shown.
- Layout and visual design. Sami wants the basics working first. Plain, accessible
  markup using the theme's existing styles is enough. Design comes later.
- The standalone calculator page.

## Anti-goals

- **Two different vuokratuotto figures on the same home.** The calculator's
  vuokratuotto must equal the one the listing already shows for the same inputs.
- **The same formula in two places that can drift apart.** The calculations live in
  the existing PHP/JS pair in `ketunkoti-features` (below), not as a copy in the
  new plugin.

## Inputs

| Field | Default | Editable by visitor |
|---|---|---|
| Vuokra €/kk | `home_rent` | yes |
| Omarahoitusosuus % | 30 % of `home_debt_free_price` (Sami's decision, changed during build) | yes |
| Laina-aika, years | 25 | yes |
| Korko % | 12 kk Euribor + 0,6, rounded to one decimal (field step 0.1, Sami's decision) | yes |
| Myyntihinta | `home_selling_price` | no, shown as a locked source figure |
| Velaton hinta | `home_debt_free_price` | no, shown |
| Hoitovastike €/kk | `home_maintenance_charge` | no, shown |
| Pääomavastike €/kk | `home_capital_charge` | no, shown |
| Taloyhtiölainan korko % | `home_loan_interest` | no, shown when set |

The home's own figures are locked (Sami's decision).

**Changed during build (Sami's decision): debt-free purchase.** The visitor finances the whole velaton hinta with omarahoitus and a bank loan, which pays off the flat's taloyhtiölaina share. So no pääomavastike and no taloyhtiölaina interest remain in the calculator, and pääomavastike / taloyhtiölainan korko are not shown in it. Omarahoitus € = velaton hinta × omarahoitusosuus / 100 (myyntihinta when velaton hinta is empty). Bank loan = velaton hinta − omarahoitus, repaid as an **annuity**. Kassavirta = vuokra − hoitovastike − bank payment. Oman pääoman tuotto = (nettovuokra × 12 − bank interest, year 1) / omarahoitus. At 100 % it equals vuokratuotto, not the listing's figures. This supersedes the formulas and the "equals the listing" check below. Laina-aika under one year counts as missing (the first year is simulated month by month). Fields are `type="number"` (Sami's decision), in the order vuokra, laina-aika, korko, omarahoitusosuus.

## Formulas

All existing formulas stay as they are. New pieces are marked **new**.

- **Bank loan monthly payment (new).** Annuity on a nominal monthly rate, the way
  the existing code treats rates:
  `r = korko / 100 / 12`, `n = laina-aika × 12`,
  `payment = loan × r / (1 − (1 + r)^−n)`. With `r = 0`, `payment = loan / n`. With
  no loan, the payment is 0.
- **Vuokratuotto (unchanged).** `(vuokra − hoitovastike) × 12 / velaton hinta × 100`.
  This is `get_rental_yield()` / `calculateRentalYield()`. The only difference is
  that vuokra comes from the visitor's field.
- **Kassavirta (new variant).** `vuokra − hoitovastike − pääomavastike − bank loan
  payment`, per month. It includes the loan repayment because that is cash leaving
  the investor's pocket.
- **Oman pääoman tuotto (new variant).**
  `(nettovuokra × 12 − taloyhtiölaina interest, year 1 − bank loan interest, year 1)
  / omarahoitus × 100`.
  - The taloyhtiölaina interest works exactly as in `calculate_equity_return()`. If
    there is a loan share and a pääomavastike but no `home_loan_interest`, the result
    is "not available", not a guess.
  - Bank loan interest, year 1: reuse `calculate_first_year_interest( loan, korko,
    payment )`. It already simulates month by month with a given payment.
  - With omarahoitus 0 or empty, the result is "not available" (no division by zero).
  - Sami treats omarahoitus as the investor's own capital (agreed in conversation).

The home's own taloyhtiölaina figures (pääomavastike, `home_loan_interest`) are
used exactly as the listing uses them (Sami's requirement). The only difference
from the listing is the visitor's bank loan. With omarahoitus = myyntihinta, the
calculator gives the listing's figures.

**Labels.** The listing's investment-figures pattern already shows Kassavirta and
Oman pääoman tuotto without a bank loan, just above the calculator. So the
calculator's result labels say the bank loan is included: "Kassavirta
pankkilainan kanssa" and "Oman pääoman tuotto pankkilainan kanssa". Vuokratuotto
keeps its plain label, because it is the same figure. (Proposed by Claude, not
objected to.)

Results that are "not available" show a dash and a short reason in the same style
the listing uses today.

Formatting reuses the existing helpers: `format_cash_flow` (signed, whole euros,
"€/kk") and `format_percentage` (one decimal).

## Where the code goes

### `ketunkoti-features` (calculations)

Add the new functions to `includes/investment.php` **and** the JS twin
`src/investment.js`, with matching cases in `tests/php/test-investment.php` and
`src/investment.test.js`:

- `calculate_annuity_payment( loan, annual_rate_percent, years )`
- `calculate_leveraged_cash_flow( rent, maintenance, capital, bank_payment )`
- `calculate_leveraged_equity_return( ... )`

The existing functions are not changed. The JS twin is what the block runs in the
browser, so it becomes front-end code too. Update the "PHP is authoritative for the
front end" comments to say that both copies are now used and must give the same
results.

### `ketunkoti-blocks` (new plugin)

- Plugin header with `Requires Plugins: ketunkoti-features`. It uses the
  calculations and meta from there.
- `npm` workspace like `ketunkoti-features` (`@wordpress/scripts`). Add it to the
  root `package.json` build/watch scripts and to `.wp-env.json`.
- `src/blocks/yield-calculator/`, following the block structure in the global
  instructions: `block.json` (apiVersion 3), `edit.js`, `render.php`, `view.js`,
  `style.scss`. Block name `ketunkoti/yield-calculator`.
- **Dynamic block.** `render.php` reads the current post's meta through
  `get_investment_inputs()`, calculates the initial results server-side (so the
  numbers are there without JS), and outputs the form.
- **Interactivity API** (`viewScriptModule`) for live updates. `view.js` imports the
  functions from `ketunkoti-features/src/investment.js` with a relative workspace
  import, and the bundler includes them (*Claude's call*. The alternative is a
  shared package, which is overkill for now).
- **Visibility.** The block renders nothing when the home has no vuokra or no
  myyntihinta. Rentals have no myyntihinta, so the block is hidden there by this
  rule. The `home-status` term does **not** affect visibility: the calculator also
  shows on Myyty and Varattu homes (Sami's decision).
- **Editor.** `edit.js` shows a `ServerSideRender` preview or a simple placeholder.
  No block settings for now.
- **Placement.** Sami inserts the block into a home's content by hand (changed from
  the template after the design review). It is not added to `single-home.html`.
- **Deploy (Claude's call).** `.github/workflows/deploy.yml` today rsyncs only
  `themes/ketunkoti`, `plugins/ketunkoti-features` and `plugins/ketunkoti-ai`. Add
  `plugins/ketunkoti-blocks` (built files included) the same way, and update the
  header comment. Sami activates the plugin on production himself. The template can
  ship independently.

### Euribor (in `ketunkoti-blocks`, e.g. `includes/euribor.php`)

- Source: ECB Data Portal, no API key needed:
  `https://data-api.ecb.europa.eu/service/data/FM/M.U2.EUR.RT.MM.EURIBOR1YD_.HSTA?lastNObservations=1&format=jsondata`.
  It returns the **monthly average** of 12 kk Euribor. The ECB does not publish
  daily values, for licensing reasons. Verified working on 2026-11-01.
- `wp_remote_get` with a short timeout. Parse the latest observation and validate
  that it is a number in a sane range (−5…20).
- Cache it in a transient (`ketunkoti_euribor_12m`) for `DAY_IN_SECONDS`.
- Also store the last good value in an option (`ketunkoti_euribor_12m_last`,
  autoload off). If a fetch fails, use the stored value and set the transient for
  one hour only, so a down API is not hit on every page view.
- If no value has ever been fetched, the korko field is prefilled with nothing, and
  the results that need it show "not available" until the visitor types a rate
  (*Claude's call*).
- Log failures with `error_log` when `WP_DEBUG_LOG` is on.
- The marginaali 0,6 is a constant in the plugin, not a setting (*Claude's call*.
  Easy to make a setting later).

## Input handling

- Finnish visitors type decimal commas. Korko and money fields accept both `3,8`
  and `3.8`.
- Empty or invalid input gives "not available" for the affected results, never
  `NaN` or `Infinity` on screen.
- Every field has a visible `<label>`. Units go in the label or in a visible suffix.
  Results sit in an `aria-live="polite"` region so screen readers hear the updates.
  Keyboard-only use works without any extra handling, because these are plain form
  fields.
- All visible strings are translatable (`ketunkoti-blocks` text domain) and in
  Finnish.

## Open for the builder

- Exact markup and class names (BEM, `js-` prefix for hooks), within the theme's
  existing styles.
- Whether laina-aika is a number field or a select (any whole number 1–40 is fine).
- Exact wording of the field labels and "not available" reasons, in Finnish and in
  the site's tone of voice (light, dry, never "kettu did X").

## Test expectations

- PHP and JS tests for the new functions, with the same cases in both. At least:
  annuity with a normal rate, with 0 % and with no loan; kassavirta with and without
  a bank loan; equity return with omarahoitus 0, with a missing taloyhtiölaina rate,
  and a hand-checked normal case.
- With omarahoitus = myyntihinta (no bank loan), the calculator's kassavirta and
  oman pääoman tuotto must equal the listing's existing figures for the same home.
- The Euribor fetch is tested with a filtered `pre_http_request` response: success,
  HTTP error, and malformed JSON.

## Euribor fallback (added later, Sami's decision)

If the ECB has never answered and no earlier rate is stored, the calculators
use a fixed 12 kk Euribor of 3 % (`EURIBOR_FALLBACK`), so the korko field
defaults to 3,6 % rather than staying empty. The ECB fetch identifies itself as
`Ketunkoti/1.0 (+site URL)` because the ECB firewall rejects WordPress's default
user agent with a 503.
