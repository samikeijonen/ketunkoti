# Vuokrakodit and yield calculations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `rental` post type for homes let to tenants, and calculated kassavirta, oman pääoman tuotto and loan interest display for investment homes (`home`).

**Architecture:** The plugin (`plugins/ketunkoti-features`) registers the post type, meta and bindings. The yield arithmetic lives in one new PHP file of pure functions, mirrored by one new JS module of pure functions. Both are tested against the same four spec cases. The theme (`themes/ketunkoti`) gets a single template and three patterns. Content (posts, terms, pages) is never written by the builder.

**Tech Stack:** WordPress 7.1 (block bindings, `register_post_meta`, block theme patterns), PHP 8.2+, `@wordpress/scripts` 30 (build, Jest via `test-unit-js`, ESLint), wp-env for local runs.

**Spec:** `docs/superpowers/specs/2026-10-25-vuokrakodit-ja-tuottolaskelmat-design.md`. Read it before starting. It holds the formulas, null rules, output formats and test values; this plan points at them rather than repeating them.

## Global Constraints

- Branch: work on the current branch `feature/post-types`. Do **not** commit: Sami commits himself at the end. Each task ends with a verification step instead.
- Plugin PHP: namespace `Ketunkoti`, 4-space indent, short arrays `[]`, PHPDoc on every function, `ketunkoti-features` text domain.
- Theme PHP/patterns: tabs, `ketunkoti_` prefix for any global, `ketunkoti` text domain.
- Plugin JS: run `source ~/.nvm/nvm.sh && nvm use` in the repo root before any `npm` command. Build with `npm run build:plugins`. `build/` is committed output and must be rebuilt after JS changes.
- All user-visible strings are in Finnish and translatable.
- Local site: http://localhost:8888 (wp-env). WP-CLI: `npx wp-env run cli wp ...` from the repo root.
- Content changes stay on localhost only. Never write post content, terms, pages or navigation in production.
- Test fixtures created locally must be deleted at the end of the task that creates them.

## Review Focus

1. **Finnish decimal input in the new korko field** ("4,3" typed in the sidebar): it must be stored as 4.3, not as 4 or 43. Pinned in Task 3 (sanitizer test) and Task 5 (sidebar uses `isFloat`).
2. **A rate typed as a fraction** (0.043 meaning 4,3 %): there's no conversion, it's stored as entered. The sidebar help text must say "prosentteina, esim. 4,3". Pinned in Task 5.
3. **A `home` post with korko but no loan** (velaton = myyntihinta): korko is ignored, and the equity return is still computed. Pinned in Task 2 ("no loan" case).
4. **Rental posts in the editor:** none of the sale or investment panels may appear, and the Vuokratiedot panel must include Lemmikit. Pinned in Task 5 (manual check list).
5. **`/vuokrakoti/<slug>/` 404 after deploy** because rewrite rules were not flushed. Pinned in Task 1 (local flush and HTTP 200 check) and in the hand-over notes (Task 7).

---

### Task 1: `rental` post type and shared taxonomies

**Files:**
- Modify: `plugins/ketunkoti-features/includes/post-types.php`
- Modify: `plugins/ketunkoti-features/includes/taxonomies.php` (the `register_taxonomy()` calls for `home-city`, `home-status`, `home-type`, `home-rooms`)

**Interfaces:**
- Produces: post type key `rental`, rewrite slug `vuokrakoti`. `register_post_types()` registers both `home` and `rental`, so the activation hook covers both.

- [ ] **Step 1: Write the check that fails now**

```bash
npx wp-env run cli wp post-type get rental --field=name
# Expected now: Error: Post type rental doesn't exist.
```

- [ ] **Step 2: Register `rental`.** In `register_post_types()`, add a second `register_post_type( 'rental', ... )`. Use the same arguments as `home`, with these differences:
  - labels in the same style: Vuokrakodit / Vuokrakoti / "Lisää uusi vuokrakoti" / "Muokkaa vuokrakotia" / "Kaikki vuokrakodit" …
  - `'rewrite' => [ 'slug' => 'vuokrakoti' ]`
  - `menu_position` 21
  - a menu icon of your choice

  Build the shared arguments once rather than copying the array: for example, have a helper return the base args, and merge the `rental` overrides into it.

- [ ] **Step 3: Attach the taxonomies.** Change the object type of `home-city`, `home-status`, `home-type` and `home-rooms` from `[ 'home' ]` to `[ 'home', 'rental' ]`. Leave `home-purpose` as `[ 'home' ]`. Add a one-line comment at `home-status` saying the taxonomy is shared on purpose, so the status badge and binding work for rentals (see spec, "Taxonomies").

- [ ] **Step 4: Verify**

```bash
npx wp-env run cli wp post-type get rental --fields=name,rewrite
npx wp-env run cli wp taxonomy get home-status --field=object_type   # ["home","rental"]
npx wp-env run cli wp taxonomy get home-purpose --field=object_type  # ["home"]
npx wp-env run cli wp rewrite flush
ID=$(npx wp-env run cli wp post create --post_type=rental --post_status=publish --post_title='Testivuokrakoti' --porcelain | tr -d '\r')
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8888/vuokrakoti/testivuokrakoti/   # 200
npx wp-env run cli wp post delete $ID --force
```

---

### Task 2: Investment calculations in PHP

**Files:**
- Create: `plugins/ketunkoti-features/includes/investment.php`
- Modify: `plugins/ketunkoti-features/ketunkoti-features.php` (require the new file, before `bindings.php`)
- Create: `plugins/ketunkoti-features/tests/php/test-investment.php`
- Modify: `.github/workflows/deploy.yml` (add `--exclude='tests/'` next to the existing rsync excludes)

**Interfaces:**
- Produces, all in namespace `Ketunkoti`. They take plain numbers and read no meta, so they're testable with `wp eval-file`; the formatters may use `number_format_i18n()` and `__()`:
  - `calculate_first_year_interest( float $loan, float $annual_rate_percent, float $monthly_payment ): float`
  - `calculate_cash_flow( float $rent, float $maintenance, float $capital ): ?float`, which returns null when `$rent <= 0`
  - `calculate_equity_return( float $rent, float $maintenance, float $capital, float $selling_price, float $debt_free_price, ?float $annual_rate_percent ): ?float`
  - `format_cash_flow( float $cash_flow ): string`
  - `format_interest_rate( float $rate ): string`
- Produces (reads meta):
  - `get_investment_inputs( int $post_id ): array` with keys `rent`, `maintenance`, `capital`, `selling_price`, `debt_free_price` (floats, empty → 0.0) and `interest_rate` (`?float`, empty meta string → null)

- [ ] **Step 1: Write the failing test** `tests/php/test-investment.php`.
  - Start with `if ( ! defined( 'WP_CLI' ) ) { exit; }`.
  - Add a tiny `check( string $name, $expected, $actual )` helper. It compares `null` and booleans strictly, floats rounded to 2 decimals and strings exactly, prints `PASS`/`FAIL name: expected X, got Y`, and calls `exit( 1 )` at the end if any check failed.
  - Cases, with the values from the spec section "Test cases":

```php
// Lahti
check( 'lahti interest', 4800.07, calculate_first_year_interest( 112725, 4.3, 600.72 ) );
check( 'lahti cash flow', 27.98, calculate_cash_flow( 780, 151.30, 600.72 ) );
check( 'lahti equity', 10.56, calculate_equity_return( 780, 151.30, 600.72, 26000, 138725, 4.3 ) );
check( 'lahti cash flow fmt', "+28\u{00A0}€/kk", format_cash_flow( 27.98 ) );
// Finnoo 358
check( 'finnoo cash flow', -304.30, calculate_cash_flow( 675, 106.40, 872.90 ) );
check( 'finnoo cash flow fmt', "\u{2212}304\u{00A0}€/kk", format_cash_flow( -304.30 ) );
check( 'finnoo equity null', null, calculate_equity_return( 675, 106.40, 872.90, 22000, 166000, null ) );
// No loan (korko set but irrelevant, and absent)
check( 'no loan equity', 7.2, calculate_equity_return( 800, 200, 0, 100000, 100000, null ) );
check( 'no loan equity w/ rate', 7.2, calculate_equity_return( 800, 200, 0, 100000, 100000, 4.3 ) );
check( 'no loan cash flow', 600.0, calculate_cash_flow( 800, 200, 0 ) );
// No rent
check( 'no rent cash flow', null, calculate_cash_flow( 0, 150, 600 ) );
check( 'no rent equity', null, calculate_equity_return( 0, 150, 600, 26000, 138725, 4.3 ) );
// No selling price
check( 'no price equity', null, calculate_equity_return( 780, 151.30, 600.72, 0, 138725, 4.3 ) );
// Rounding and zero
check( 'zero cash flow fmt', "0\u{00A0}€/kk", format_cash_flow( 0.4 ) );
check( 'half away from zero', "\u{2212}1\u{00A0}€/kk", format_cash_flow( -0.5 ) );
// Interest rate format
check( 'rate 4.3', "4,3\u{00A0}%", format_interest_rate( 4.3 ) );
check( 'rate 4.25', "4,25\u{00A0}%", format_interest_rate( 4.25 ) );
check( 'rate 4', "4\u{00A0}%", format_interest_rate( 4.0 ) );
```

  The test runs in a Finnish locale (the site is `fi`), so `number_format_i18n` gives `,` as the decimal separator. If the CLI locale differs, switch with `switch_to_locale( 'fi' )` at the top of the test.

- [ ] **Step 2: Run it and see it fail**

```bash
npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-investment.php
# Expected: fatal "Call to undefined function Ketunkoti\calculate_first_year_interest"
```

- [ ] **Step 3: Implement `includes/investment.php`.**
  - The file header docblock explains the three figures and points to the JS twin `src/investment.js`.
  - Formulas, null rules and the month-by-month interest loop follow the spec section "Formulas" exactly.
  - `calculate_equity_return` computes `lainaosuus = debt_free_price - selling_price` itself. It needs the rate only when `lainaosuus > 0 && capital > 0`.
  - `format_cash_flow`:
    - round half away from zero (PHP `round()`)
    - format with `number_format_i18n( abs( $rounded ), 0 )`
    - prefix `+` for > 0, U+2212 for < 0, nothing for 0
    - add NBSP, then `€/kk`, through a translatable `sprintf` string with a translators comment
  - `format_interest_rate`: 0 decimals if the value is whole, 1 if one decimal place is enough, otherwise 2, then NBSP + `%`.

- [ ] **Step 4: Run the test and see it pass**

```bash
npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-investment.php
# Expected: every line PASS, exit code 0
```

- [ ] **Step 5: Exclude tests from deploy.** Add `--exclude='tests/'` to the rsync excludes in `.github/workflows/deploy.yml`.

---

### Task 3: Meta fields for `rental`, `home_pets`, `home_loan_interest`

**Files:**
- Modify: `plugins/ketunkoti-features/includes/meta.php`
- Modify: `plugins/ketunkoti-features/tests/php/test-investment.php` (add a sanitizer check)

**Interfaces:**
- Consumes: post type `rental` (Task 1)
- Produces:
  - meta key `home_loan_interest` (number, `home` only)
  - meta key `home_pets` (string, `rental` only)
  - these existing keys also registered on `rental`: `home_area`, `home_room_layout`, `home_floor`, `home_address`, `home_year_built`, `home_rent`, `home_deposit`, `home_water_charge`, `home_other_charges`, `home_available_from`

- [ ] **Step 1: Add the failing checks** to the test file:

```php
check( 'korko finnish comma', 4.3, sanitize_positive_number( '4,3' ) );
check( 'rental has rent meta', true, registered_meta_key_exists( 'post', 'home_rent', 'rental' ) );
check( 'rental has pets meta', true, registered_meta_key_exists( 'post', 'home_pets', 'rental' ) );
check( 'home has no pets meta', false, registered_meta_key_exists( 'post', 'home_pets', 'home' ) );
check( 'home has loan interest', true, registered_meta_key_exists( 'post', 'home_loan_interest', 'home' ) );
check( 'rental has no selling price', false, registered_meta_key_exists( 'post', 'home_selling_price', 'rental' ) );
```

- [ ] **Step 2: Run them and see them fail.** Use the same command as in Task 2. Expected: the four `rental`, `pets` and `loan interest` checks FAIL. The comma check passes already.

- [ ] **Step 3: Implement.**
  - For each of the ten shared keys, register the same arguments for both post types by looping over `[ 'home', 'rental' ]`. One registration per key, so labels and sanitizers are written once.
  - Add a comment where the keys are registered: the `home_` prefix means "a home listing", not the `home` post type.
  - Register `home_loan_interest` on `home` only: label "Taloyhtiölainan korko", description "Taloyhtiölainan korko prosentteina, esimerkiksi 4,3.", `sanitize_positive_number`.
  - Register `home_pets` on `rental` only: label "Lemmikit", description "Esimerkiksi \"Sovittavissa\" tai \"Ei lemmikkejä\".", `sanitize_text_field`.

- [ ] **Step 4: Run the tests and see them pass.** Same command. Expected: all PASS.

---

### Task 4: Bindings: new computed keys, korko and pets

**Files:**
- Modify: `plugins/ketunkoti-features/includes/bindings.php`

**Interfaces:**
- Consumes:
  - `get_investment_inputs()`, `calculate_cash_flow()`, `calculate_equity_return()`, `format_cash_flow()`, `format_interest_rate()` (Task 2)
  - meta keys from Task 3
- Produces these binding keys:
  - `home_cash_flow`, `home_equity_return`: computed, in `COMPUTED_FIELDS`
  - `home_loan_interest`: in the switch, via `format_interest_rate`
  - `home_pets`: free text, same case as `home_other_charges`

- [ ] **Step 1: Write the failing check.** Create a local fixture `home` post with the Lahti meta, then render the bindings:

```bash
ID=$(npx wp-env run cli wp post create --post_type=home --post_status=draft --post_title='Lahti fixture' --porcelain | tr -d '\r')
for kv in home_rent=780 home_maintenance_charge=151.3 home_capital_charge=600.72 home_selling_price=26000 home_debt_free_price=138725 home_loan_interest=4.3; do npx wp-env run cli wp post meta update $ID ${kv%%=*} ${kv#*=}; done
npx wp-env run cli wp eval "foreach ( [ 'home_cash_flow', 'home_equity_return', 'home_rental_yield', 'home_loan_interest' ] as \$k ) { echo \$k, ': ', var_export( Ketunkoti\get_binding_value( [ 'key' => \$k ], (object) [ 'context' => [ 'postId' => $ID ] ] ), true ), PHP_EOL; }"
```

Expected now: `home_cash_flow: NULL`, `home_equity_return: NULL`, `home_loan_interest: NULL`.

- [ ] **Step 2: Implement.**
  - Add the two computed keys to `COMPUTED_FIELDS`.
  - Replace the `'home_rental_yield' === $key ? ...` ternary with a `match` over the three computed keys. The two new arms read `get_investment_inputs( $post_id )`, call the calculator, and return null or the formatted value. Equity return uses the same one-decimal `%` formatting as `get_rental_yield()`; extract a shared `format_percentage( float $value ): string` if that keeps it in one place.
  - Add `case 'home_loan_interest'` (calls `format_interest_rate( (float) $value )`, null when ≤ 0) and add `home_pets` to the free text cases.
  - Update the file-level and `INVESTMENT_BINDINGS_SOURCE` comments so they list the new keys.

- [ ] **Step 3: Run the check again and see the expected values.**

```
home_cash_flow: '+28 €/kk'
home_equity_return: '10,6 %'
home_rental_yield: '5,4 %'
home_loan_interest: '4,3 %'
```

The spaces are NBSPs.

- [ ] **Step 4: Check the Finnoo null case on the same fixture.** Set Finnoo's numbers:
  - rent 675, hoitovastike 106.4, pääomavastike 872.9
  - myyntihinta 22000, velaton 166000
  - delete `home_loan_interest`

  Expected: `home_cash_flow: '−304 €/kk'`, `home_equity_return: NULL`. Then delete the fixture: `npx wp-env run cli wp post delete $ID --force`.

---

### Task 5: Editor JS: calculation module, bindings fields, panels per post type

**Files:**
- Create: `plugins/ketunkoti-features/src/investment.js`
- Create: `plugins/ketunkoti-features/src/investment.test.js`
- Modify: `plugins/ketunkoti-features/src/index.js`
- Modify: `plugins/ketunkoti-features/package.json` (add `"test:unit": "wp-scripts test-unit-js"`)
- Rebuild: `plugins/ketunkoti-features/build/*`

**Interfaces:**
- Produces, exported from `src/investment.js` (pure, the JS twins of Task 2):
  - `calculateFirstYearInterest( loan, annualRatePercent, monthlyPayment ) => number`
  - `calculateCashFlow( rent, maintenance, capital ) => number | undefined`
  - `calculateEquityReturn( { rent, maintenance, capital, sellingPrice, debtFreePrice, annualRatePercent } ) => number | undefined`. `annualRatePercent` is `undefined` when empty.
  - `formatCashFlow( value, formatNumber ) => string` and `formatInterestRate( value, formatNumber ) => string`. `formatNumber` is passed in, so the module stays free of `document`.
- Move the existing `calculateRentalYield` into this module too, so all four formulas sit together. `index.js` imports it.

- [ ] **Step 1: Write the failing Jest test** `src/investment.test.js`. It mirrors the PHP cases from Task 2, Step 1 one to one, with the same numbers:
  - the four spec cases
  - no rent, no price
  - rounding: +28 / −304 / 0 / −1
  - rate formats 4,3 / 4,25 / 4

  For the format tests, use a `formatNumber` stub built on `Intl.NumberFormat( 'fi', … )`. Compare floats with `toBeCloseTo( x, 2 )`.

- [ ] **Step 2: Run it and see it fail**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run test:unit --workspace=plugins/ketunkoti-features
# Expected: Cannot find module './investment'
```

- [ ] **Step 3: Implement `src/investment.js`.** The header comment points to `includes/investment.php` as the authoritative twin.

- [ ] **Step 4: Run the tests and see them pass.** Same command. Expected: all pass.

- [ ] **Step 5: Update `src/index.js`.**
  - `FIELDS`: add `loanInterest: 'home_loan_interest'`, `pets: 'home_pets'`, `cashFlow: 'home_cash_flow'`, `equityReturn: 'home_equity_return'`.
  - `TEXT_FIELDS`: add `FIELDS.pets`.
  - `resolveField`: the computed keys resolve through the module, and `home_loan_interest` uses `formatInterestRate`.
  - Bindings `getFieldsList`:
    - "Vuokratiedot" gets Lemmikit
    - "Sijoituslaskelma" gets Kassavirta, Oman pääoman tuotto, Taloyhtiölainan korko, in the spec's order: vuokratuotto, kassavirta, OPT, korko
  - Panels:
    - Replace the single `POST_TYPE` gate with `[ 'home', 'rental' ].includes( postType )`.
    - Render each panel according to the spec table "Editor sidebar (`src/index.js`)".
    - "Hinnat ja vastikkeet" (home only) gets a korko field: `numberField( FIELDS.loanInterest, …, { step: '0.01', isFloat: true, help: 'Prosentteina, esim. 4,3.' } )`.
    - "Vuokratiedot" gets a Lemmikit text field on `rental` only. Its availability field gets this help text on `rental`: "Tyhjä = heti vapaa. Kun koti on vuokrattu, lisää päivä tai poista rivi sivulta."
    - "Sijoituslaskelma" (home only) shows four read-only rows: vuokratuotto, kassavirta, oman pääoman tuotto and korko. Use the existing `__yield` markup pattern, with `—` for undefined. Update its help line to name all the inputs.
  - Update the doc comment above `HomeDetailsPanels`: panels now vary by post type, still never by term (spec, "This supersedes…").

- [ ] **Step 6: Build and lint**

```bash
source ~/.nvm/nvm.sh && nvm use && npm run build:plugins && npm run lint:js --workspace=plugins/ketunkoti-features
# Expected: build succeeds, lint reports 0 errors
```

- [ ] **Step 7: Manual editor check.** Local login doesn't work with the imported database. Ask Sami to open a `home` post and a new `rental` post in the local editor and confirm:
  - `home` shows Kodin tiedot, Hinnat ja vastikkeet (with korko), Vuokratiedot (no Lemmikit), and Sijoituslaskelma with four rows that update live.
  - `rental` shows only Kodin tiedot and Vuokratiedot, with Lemmikit and the availability help text.
  - Typing "4,3" in korko shows 4,3 % in Sijoituslaskelma.

---

### Task 6: Theme: rental template and patterns, investment pattern

**Files:**
- Create: `themes/ketunkoti/templates/single-rental.html` (same structure as `single-home.html`)
- Create: `themes/ketunkoti/patterns/rental-details.php`
- Create: `themes/ketunkoti/patterns/rentals-grid.php`
- Create: `themes/ketunkoti/patterns/investment-figures.php`

**Interfaces:**
- Consumes these binding keys and sources: `ketunkoti/home-details`, `ketunkoti/home-rental` (incl. `home_pets`), `ketunkoti/home-investment` (`home_rental_yield`, `home_cash_flow`, `home_equity_return`, `home_loan_interest`)
- `rental-details.php` and `investment-figures.php` need the same row renderer as `home-details.php`, which is a closure local to that file. Copy the closure into each new pattern under its own variable name (`$ketunkoti_rental_detail`, `$ketunkoti_investment_figure`). A shared helper is not worth adding for three patterns. Pattern files can't share a closure, and the reason is given in its existing docblock.

- [ ] **Step 1: Write the failing check**

```bash
npx wp-env run cli wp eval "foreach ( [ 'ketunkoti/rental-details', 'ketunkoti/rentals-grid', 'ketunkoti/investment-figures' ] as \$p ) { echo \$p, ': ', WP_Block_Patterns_Registry::get_instance()->is_registered( \$p ) ? 'yes' : 'no', PHP_EOL; }"
# Expected now: three "no"
```

- [ ] **Step 2: Create the four files.**
  - `rental-details.php`:
    - Title "Vuokrakodin tiedot", `Post Types: rental`, same wrapper group and grid as `home-details.php`.
    - Rows in the spec's order: Vuokra, Vakuus, Vapautuu, Lemmikit, Asuinpinta-ala, Huoneistoselitelmä, Kerros, Sijainti.
    - Use the `ketunkoti/home-rental` source for the first four and the default source for the rest.
  - `rentals-grid.php`:
    - Title "Vuokrakodit-ruudukko", `Block Types: core/query`, a copy of `homes-grid.php`.
    - `"postType":"rental"`.
    - The price paragraph is bound to `ketunkoti/home-rental` / `home_rent`.
    - No-results text: "Ei vuokrakoteja näytettäväksi."
  - `investment-figures.php`:
    - Title "Sijoituslaskelma", `Post Types: home`.
    - Same wrapper style as `home-details.php`, with four rows in the order vuokratuotto, kassavirta, oman pääoman tuotto, taloyhtiölainan korko.
    - Under the grid, a small paragraph with the exact spec text: "Laskelmat ennen veroja. Oman pääoman tuotto on ensimmäisen vuoden tuotto myyntihinnalle, ja lyhennykset lasketaan sijoittajan varallisuudeksi."
  - `single-rental.html`: copy `single-home.html`.

- [ ] **Step 3: Run the check again.** Same command. Expected: three "yes".

- [ ] **Step 4: Render check with local fixtures.**
  1. Create a `rental` fixture with meta rent 850, deposit 1700, pets "Sovittavissa", area 44.5, and with the `rental-details` pattern plus `[contact-form-7 id="6aa0d27" title="Yhteydenottolomake"]` as content (pattern content from `wp eval "echo WP_Block_Patterns_Registry::get_instance()->get_registered( 'ketunkoti/rental-details' )['content'];"`).
  2. Publish it and fetch `/vuokrakoti/<slug>/`. Expected:
     - "850 €/kk", "1 700 €", "Heti vapaa" and "Sovittavissa" all appear
     - `class="wpcf7-form` is present
     - CF7 CSS is loaded (`contact-form-7/includes/css`)
  3. Repeat with a `home` fixture (Lahti meta from Task 4) and the `investment-figures` pattern. Expected: "+28 €/kk", "10,6 %", "5,4 %", "4,3 %" and the explanatory sentence.
  4. Delete both fixtures with `--force`.

---

### Task 7: Final verification and hand-over

**Files:** none new.

- [ ] **Step 1: Run all checks.**
  - The PHP test command from Task 2
  - `npm run test:unit --workspace=plugins/ketunkoti-features`
  - `npm run lint:js --workspace=plugins/ketunkoti-features`
  - `php -l` on every changed PHP file via `npx wp-env run cli php -l <path>`

  Expected: all pass, no syntax errors.
- [ ] **Step 2: Regression check of existing pages.** Fetch `/`, `/myytavat-sijoitusasunnot/` and `/koti/kulmayksio-sijoitusasunnoksi-finnoon-metroaseman-vieresta/` locally. Expected:
  - status 200
  - the existing facts still render ("22 000 €", "166 000 €", "675 €/kk")
  - the sale grid still lists the investment homes with their status badges
- [ ] **Step 3: Report to Sami.** Include:
  - files changed
  - test output
  - the manual editor checks still owed (Task 5, Step 7)
  - after-deploy steps: `wp rewrite flush`, then `wp litespeed-purge all`
  - content work for him:
    - create the "Vuokrattu" status term
    - insert Sijoituslaskelma and enter korko on posts 358 and 319
    - create the rentals listing page with `rentals-grid` and add it to the navigation
