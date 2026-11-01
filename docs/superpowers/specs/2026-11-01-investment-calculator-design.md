# Standalone investment calculator (sijoituslaskuri)

Date: 2026-11-01
Status: Design agreed in conversation, builder check done. Awaiting approval.

A second block in `plugins/ketunkoti-blocks/`: a standalone calculator for an
investor who wants to test a flat's numbers quickly **without opening Excel**. It is
not tied to a Ketun koti listing. It opens with example numbers and the investor
types in their own.

The small calculator on the home page (`ketunkoti/yield-calculator`, spec
`2026-11-01-home-yield-calculator-design.md`) stays as it is. This is the "larger
calculator" that spec deferred.

## Why

Sami's words: an investor "who wants to test the numbers quickly without Excel".
Reference: [asuntokauppa.com/vuokratuottolaskuri](https://asuntokauppa.com/vuokratuottolaskuri/).
We went through its ~20 fields and kept the ones that change a decision (below).

## Goals

- One block, placed on a page by Sami, that works without any listing data.
- First-year figures, a few key ratios, and two small sensitivity tables.
- Results update live as the investor types.

## Non-goals (for now)

- **A long-term chart.** Sami showed his own Excel chart (asunnon arvo, lainan
  määrä, oma pääoma, kumulatiivinen kassavirta and "varallisuus korkoa korolle")
  but decided to leave it out for now. Rent increase and value growth fields are
  therefore also out, because they only matter over several years.
- **Taloyhtiölaina.** Simplified for now: the whole velaton hinta is financed with
  omarahoitus and a bank loan, as in the small calculator. No pääomavastike. Sami
  noted that in real life a small taloyhtiölaina is often kept. That is a later
  addition.
- Loan arrangement fees (fold into remonttivara if needed).
- Accounting treatment of rahoitusvastike (tuloutettu / rahastoitu).
- Prefilling from a Ketun koti listing.

## Anti-goals

- **Turning into the Excel the investor is avoiding.** Every field must earn its
  place. When in doubt, leave it out.
- **The same formula in two places that can drift.** Loan maths reuses and extends
  the `ketunkoti-features` investment twin (`includes/investment.php`,
  `src/investment.js`), as the small calculator does.

## Inputs

| Field | Default | Notes |
|---|---|---|
| Velaton hinta € | 90 000 | |
| Vuokra €/kk | 590 | |
| Pinta-ala m² | 26 | |
| Hoitovastike €/kk | 104 | Entered per month, because listings usually state it directly (Sami's decision, changed after the first build). Hoitovastike €/m²/kk is shown as a ratio. |
| Omarahoitusosuus % | 30 | Share of velaton hinta, as in the small calculator. **0 % is valid** (whole price on a bank loan, e.g. with lisävakuus; Sami's decision). Empty counts as missing. Field `min="0"`, `max="100"`. |
| Pankkilainan korko % | 12 kk Euribor + 0,6, one decimal | Same helper as the small calculator (`get_default_interest_rate()`). Field step 0.1. |
| Laina-aika, years | 25 | Under one year counts as missing, as in the small calculator. |
| Lyhennystapa | Annuiteetti | Select: annuiteetti / kiinteä tasaerä / tasalyhennys. |
| Tyhjät kuukaudet / vuosi | 0 | 0–12. (*Claude's call*: 0, because the vacancy table shows 0–3 anyway.) |
| Vuokratulon vero % | 30 | Pääomatulovero. (*Claude's call*. 34 % applies above 30 000 € of capital income, so it stays editable.) |
| Varainsiirtovero % | 1,5 | Of velaton hinta. (*Claude's call*: an editable field, because the rate has changed before.) |
| Remonttivara € | 0 | One combined reserve (asunto + taloyhtiö), as agreed. |

All fields are `type="number"` and the defaults use a dot decimal. The lyhennystapa
is a `<select>`. Fields sit in a `<form>` with no fieldset. Enter is prevented with
an action wrapped in `withSyncEvent()`.

## Derived values

- Vuokrakuukaudet = 12 − tyhjät kuukaudet
- Varainsiirtovero € = velaton hinta × varainsiirtovero %
- Omarahoitus € = velaton hinta × omarahoitusosuus %
- Pankkilaina = max(0, velaton hinta − omarahoitus)
- Oma pääoma (for the return) = omarahoitus + varainsiirtovero + remonttivara.
  These are all paid out of the investor's own pocket.

## Loan year 1, per lyhennystapa

Nominal monthly rate (annual / 12), simulated month by month for 12 months, as the
existing `calculate_first_year_interest()` does.

- **Annuiteetti.** Payment = annuity (existing `calculate_annuity_payment()`).
- **Kiinteä tasaerä.** Payment is fixed at the annuity payment of the **base**
  rate. With the base rate it equals the annuity. Under a rate shock the payment
  stays the same and only the interest share grows (in real life the term
  lengthens). If the payment no longer covers the interest, the balance grows.
  That is real and is shown as such.
- **Tasalyhennys.** Monthly repayment = loan / (years × 12), plus interest on the
  remaining balance. The payment falls every month. **Kassavirta shows the
  first-year average per month**: (12 × repayment + first-year interest) / 12.
  (*Claude's call*: the average rather than the first month, so it is comparable
  with the yearly figures.)

New twin functions in `ketunkoti-features` (PHP + JS, same test cases), next to the
existing ones. Builder names them.

- First-year payments and interest for a given loan, rate, years, lyhennystapa and,
  for kiinteä tasaerä, the base rate. Returns the yearly payments total and the
  yearly interest.

## Outputs (first year)

**Main results:**
- **Vuokratuotto (netto)** = (vuokra × vuokrakuukaudet − hoitovastike × 12) /
  (velaton hinta + varainsiirtovero + remonttivara) × 100. The same formula as
  asuntokauppa. It is more conservative than the listing's and the small
  calculator's vuokratuotto, which is fine because this calculator answers a
  different question.
- **Kassavirta ennen veroja, €/kk** = (vuokra × vuokrakuukaudet − hoitovastike × 12
  − bank loan payments for the year) / 12
- **Kassavirta verojen jälkeen, €/kk** = kassavirta ennen veroja − vero / 12, where
  vero = max(0, vuokra × vuokrakuukaudet − hoitovastike × 12 − first-year bank
  interest) × vuokratulon vero %.
  - Repayment is not deductible, interest is.
  - A loss gives no negative tax (*Claude's call*: a loss can only be deducted from
    other capital income, which the calculator does not know).
- **Oman pääoman tuotto %** = (vuokra × vuokrakuukaudet − hoitovastike × 12 −
  first-year bank interest) / oma pääoma × 100. It is before tax, like the small
  calculator's.

**Financing (Sami's decision):**
- Omarahoitus €
- Pankkilaina €
- Kuukausierä €/kk: the payment at the base rate. For tasalyhennys it is the
  first-year average per month, the same rule as kassavirta.

**Ratios:**
- Neliöhinta = velaton hinta / pinta-ala, €/m²
- Neliövuokra = vuokra / pinta-ala, €/m²/kk
- Hoitovastike €/m²/kk = hoitovastike / pinta-ala

**Sensitivity tables:**
- **Korkoriski.** Kassavirta verojen jälkeen, €/kk, at the current rate and at +1,
  +2 and +4 percentage points, with the chosen lyhennystapa. Kiinteä tasaerä keeps
  the base payment, as above.
- **Tyhjät kuukaudet.** Vuokratuotto (netto) at 0, 1, 2 and 3 vacant months.

"Not available" shows `—` with one notice, as in the small calculator. Examples:
missing velaton hinta, vuokra, pinta-ala or omarahoitusosuus (empty, not 0); a
bank loan without a rate or term. If oma pääoma is 0 (omarahoitus 0 %,
varainsiirtovero 0 and remonttivara 0), oman pääoman tuotto is `—` with its own
notice. Every other figure is still shown.

## Where the code goes

- `ketunkoti-features`: the new loan function(s) in the investment twin, with
  tests in both.
- `ketunkoti-blocks/src/blocks/investment-calculator/` (block
  `ketunkoti/investment-calculator`, title "Sijoituslaskuri", category
  `ketunkoti`). Same structure and conventions as `yield-calculator`:
  - dynamic `render.php`, server-rendered first paint
  - Interactivity API store
  - a JS calculator layer with a PHP twin in `includes/` (same strings in both,
    the same test cases)
  - no wrapper box styles
  - row separator lines
  - Finnish labels, translatable.
  - (*Claude's call*: keep the server-rendered first paint, and therefore the PHP
    twin, for consistency with the small calculator and so the numbers show
    without JS. It roughly doubles the calculator-layer code.)
- Reuse `parseDecimal`, the formatting helpers and the Euribor helper. Don't copy
  them. If something from `yield-calculator` is needed in both blocks, move it to a
  shared module.
- No heading in the block. Sami adds a Heading block above it.
- A short explanatory note inside the block, like the small calculator's.
  Wording is the builder's within the site's tone, and must state:
  - the whole velaton hinta is financed with omarahoitus and a bank loan
  - the figures are first-year estimates, not a binding offer.

## Shareable URL (added after the first build, Sami's decision)

Every input change writes all fields into the address bar with
`history.replaceState`, under short Finnish names, so the URL can be copied and
shared:

`?hinta=90000&vuokra=590&ala=26&vastike=104&tyhjat=0&osuus=30&korko=3.8&aika=25&tapa=annuiteetti&vsv=1.5&remontti=0&vero=30`

- `vastike` is euros per month.
- `tapa` is `annuiteetti`, `tasaera` or `tasalyhennys`.
- Every field is always written, the rate included, so a shared link keeps its
  rate when Euribor moves.
- An emptied field is written as an empty value.
- The server reads the parameters (`get_investment_fields_from_query()`) and
  renders the first paint with them.
- Invalid values and unknown `tapa` values keep the default.
- Only the investment calculator does this. The small calculator is tied to a
  home.
- A "Kopioi laskurin linkki" button (theme button style) at the end of the results column
  (after the tables) copies the link
  built from the current fields. "Linkki kopioitu." appears in a
  `role="status"` element. If the clipboard is blocked, the address bar is
  still updated and the message says "Kopiointi ei onnistunut. Kopioi linkki
  osoiteriviltä." The message clears on the next input.

## Left to the builder

- Exact layout of inputs, results, ratios and the two tables (accessible
  `<table>`s with captions for the sensitivity tables), within the theme.
- Grouping of the inputs (e.g. kohde / rahoitus / kulut) if it helps scanning.
  Use plain headings or visual grouping, not fieldsets, unless a real group label
  is needed.
- Notice wording.

## Test expectations

- Twin tests for the new loan function(s): all three lyhennystapa at a normal rate,
  0 % and a vanishing rate, a rate shock for kiinteä tasaerä including a payment
  below the interest, years < 1 and no loan.
- Calculator-layer twin tests with the defaults, hand-checked, plus: vacant months
  12, tax with a loss, omarahoitusosuus 100 % (no loan), and every "not available"
  notice.
- Browser check: first paint equals the hydrated paint, typing updates everything,
  no console warnings.

## Euribor fallback (added later, Sami's decision)

If the ECB has never answered and no earlier rate is stored, the calculators
use a fixed 12 kk Euribor of 3 % (`EURIBOR_FALLBACK`), so the korko field
defaults to 3,6 % rather than staying empty. The ECB fetch identifies itself as
`Ketunkoti/1.0 (+site URL)` because the ECB firewall rejects WordPress's default
user agent with a 503.
