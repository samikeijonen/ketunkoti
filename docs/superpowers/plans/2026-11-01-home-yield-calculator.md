# Home yield calculator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An interactive yield calculator block (`ketunkoti/yield-calculator`) on single `home` pages, prefilled from the home's meta, where the visitor tries their own bank loan (12 kk Euribor + 0,6 % prefilled).

**Architecture:** The bank-loan formulas are added to the existing PHP/JS twin in `ketunkoti-features` (`includes/investment.php`, `src/investment.js`). A new plugin `ketunkoti-blocks` holds the Euribor fetch (ECB API, transient + fallback option), a thin "calculator" layer that turns inputs into the three formatted results (PHP twin for the server render, JS twin for live updates), and the dynamic block rendered with the Interactivity API.

**Tech Stack:** PHP 8.2, WordPress 7.1 (Interactivity API, `viewScriptModule`), `@wordpress/scripts` 30 (`--experimental-modules`), Jest via `wp-scripts test-unit-js`, wp-env.

**Spec:** `docs/superpowers/specs/2026-11-01-home-yield-calculator-design.md`

## Global Constraints

- PHP: 4-space indentation, namespaced functions (`Ketunkoti` in features, `Ketunkoti\Blocks` in the new plugin), PHPDoc on every function, `ABSPATH` guard on every file.
- JS: tabs (as `wp-scripts format` produces in this repo), JSDoc on every function.
- Text domain for the new plugin: `ketunkoti-blocks`. All visible strings in Finnish and translatable.
- Existing functions in `investment.php` / `investment.js` must **not** change behaviour. Every new function exists in **both** files with the **same** test cases.
- Defaults: omarahoitus = 25 % of myyntihinta rounded to the nearest 100 €; laina-aika = 25; korko = round(Euribor + 0,6, 2); marginaali is the constant `0.6`.
- Euribor source: `https://data-api.ecb.europa.eu/service/data/FM/M.U2.EUR.RT.MM.EURIBOR1YD_.HSTA?lastNObservations=1&format=jsondata`. Transient `ketunkoti_euribor_12m` for `DAY_IN_SECONDS`, last good value in option `ketunkoti_euribor_12m_last` (autoload off), failure → transient for `HOUR_IN_SECONDS` with the fallback value. Sane range −5…20.
- Result labels: "Vuokratuotto", "Kassavirta pankkilainan kanssa", "Oman pääoman tuotto pankkilainan kanssa".
- Not available → em dash `—` (as the listing pattern shows), plus a short notice (Task 3).
- Visibility: render nothing without `home_rent > 0` and `home_selling_price > 0`. `home-status` is ignored.
- The home's own figures are locked (shown, not editable).
- No varainsiirtovero, no rate breakdown.
- Sami commits himself: **no commit steps** in this plan. Leave changes uncommitted.
- Local commands run from the repo root. Run `nvm use` before any npm/npx command. WP-CLI: `npx wp-env run cli wp ...`.

## Review Focus

1. **Finnish decimal input** (`3,85`, `6 500`, `6 500` with a non-breaking space, `""`, `abc`): a comma is a decimal point, spaces are ignored, and anything unparseable is "empty", never `NaN` on screen. Test in Task 3 (`parseDecimal`).
2. **Omarahoitus ≥ myyntihinta**: no bank loan. Kassavirta equals the listing's kassavirta, and the equity return divides by the visitor's omarahoitus (it may be below the listing's). Test in Task 1 (`no bank loan equals listing`, `overfunded`).
3. **Laina-aika 0 or empty with a bank loan**: kassavirta and equity are "not available" with the notice to enter a rate and term. No division by zero. Test in Task 1 (`annuity zero years`) and Task 3 (`notice for missing term`).
4. **ECB API down, returning HTML, or returning a value outside the range**: the calculator still renders, using the last good value or an empty korko field. Test in Task 2 (`http error uses fallback`, `malformed json`, `out of range`).
5. **PHP and JS producing different text for the same inputs** (number formatting, sign, rounding). The server-rendered first paint must not visibly change when JS hydrates. Test in Task 3 (the same `calculator` cases in both twins, with string outputs compared). Manual check in Task 4 step 6.

---

### Task 1: Bank-loan formulas in the investment twin

**Files:**
- Modify: `plugins/ketunkoti-features/includes/investment.php` (add after `calculate_equity_return()`, update file docblock)
- Modify: `plugins/ketunkoti-features/src/investment.js` (add after `calculateEquityReturn`, update file docblock)
- Test: `plugins/ketunkoti-features/tests/php/test-investment.php`, `plugins/ketunkoti-features/src/investment.test.js`

**Interfaces:**
- Produces (PHP, namespace `Ketunkoti`):
  - `calculate_annuity_payment( float $loan, ?float $annual_rate_percent, float $years ): ?float`. Returns `0.0` when `$loan <= 0`. Returns `null` when `$years <= 0` or the rate is `null`. Returns `$loan / ( $years * 12 )` when the rate is `0`. Otherwise it returns the annuity on the nominal monthly rate.
  - `calculate_leveraged_cash_flow( float $rent, float $maintenance, float $capital, float $bank_payment ): ?float`. Returns `null` when `$rent <= 0`. Otherwise it returns `calculate_cash_flow(...) - $bank_payment`.
  - `calculate_leveraged_equity_return( array $inputs ): ?float` with keys `rent, maintenance, capital, selling_price, debt_free_price, interest_rate (?float), own_capital, bank_rate (?float), years`.
- Produces (JS, named exports): `calculateAnnuityPayment( loan, annualRatePercent, years )`, `calculateLeveragedCashFlow( rent, maintenance, capital, bankPayment )`, `calculateLeveragedEquityReturn( { rent, maintenance, capital, sellingPrice, debtFreePrice, annualRatePercent, ownCapital, bankRate, years } )`. `undefined` replaces PHP `null`.
- Consumes: the existing `calculate_cash_flow`, `calculate_first_year_interest` (and their JS twins).

`calculate_leveraged_equity_return` rules, in order:
1. `rent <= 0`, `selling_price <= 0` or `own_capital <= 0` → null.
2. Taloyhtiö interest: exactly as in `calculate_equity_return()` (loan share = debt_free − selling. If the loan share > 0 and capital > 0, a `null` rate gives null; otherwise `calculate_first_year_interest( share, rate, capital )`).
3. `bank_loan = max( 0, selling_price − own_capital )`. If `bank_loan > 0`: `payment = calculate_annuity_payment( bank_loan, bank_rate, years )`, and `null` makes the result null. Bank interest = `calculate_first_year_interest( bank_loan, bank_rate, payment )`.
4. `( ( rent − maintenance ) × 12 − company_interest − bank_interest ) / own_capital × 100`.

- [ ] **Step 1: Write the failing PHP checks.** Append them before the summary block in `test-investment.php`, and add the three `use function` imports. The values were hand-checked with the formulas on 2026-11-01.

```php
// Bank loan annuity, 2026-11-01.
check( 'annuity 100k', 519.59, calculate_annuity_payment( 100000, 3.85, 25 ) );
check( 'annuity zero rate', 400.0, calculate_annuity_payment( 120000, 0.0, 25 ) );
check( 'annuity no loan', 0.0, calculate_annuity_payment( 0, 3.85, 25 ) );
check( 'annuity zero years', null, calculate_annuity_payment( 19500, 3.85, 0 ) );
check( 'annuity no rate', null, calculate_annuity_payment( 19500, null, 25 ) );

// Lahti with the calculator defaults: 25 % omarahoitus, 3,85 %, 25 years.
check( 'lahti bank payment', 101.32, calculate_annuity_payment( 19500, 3.85, 25 ) );
check( 'lahti leveraged cash flow', -73.34, calculate_leveraged_cash_flow( 780, 151.30, 600.72, 101.32 ) );
$lahti = [ 'rent' => 780.0, 'maintenance' => 151.30, 'capital' => 600.72, 'selling_price' => 26000.0, 'debt_free_price' => 138725.0, 'interest_rate' => 4.3, 'own_capital' => 6500.0, 'bank_rate' => 3.85, 'years' => 25.0 ];
check( 'lahti leveraged equity', 30.80, calculate_leveraged_equity_return( $lahti ) );
check( 'no bank loan equals listing', 10.56, calculate_leveraged_equity_return( array_merge( $lahti, [ 'own_capital' => 26000.0, 'bank_rate' => null ] ) ) );
check( 'overfunded', round( ( ( 780 - 151.30 ) * 12 - 4800.07 ) / 52000 * 100, 2 ), calculate_leveraged_equity_return( array_merge( $lahti, [ 'own_capital' => 52000.0 ] ) ) );
check( 'leveraged equity no own capital', null, calculate_leveraged_equity_return( array_merge( $lahti, [ 'own_capital' => 0.0 ] ) ) );
check( 'leveraged equity no bank rate', null, calculate_leveraged_equity_return( array_merge( $lahti, [ 'bank_rate' => null ] ) ) );
check( 'leveraged equity no company rate', null, calculate_leveraged_equity_return( array_merge( $lahti, [ 'interest_rate' => null ] ) ) );
check( 'leveraged cash flow no rent', null, calculate_leveraged_cash_flow( 0, 151.30, 600.72, 101.32 ) );
```

- [ ] **Step 2: Run and see them fail.**
Run: `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-investment.php`
Expected: a fatal "Call to undefined function Ketunkoti\calculate_annuity_payment".

- [ ] **Step 3: Implement the three PHP functions** in `investment.php` with the signatures and rules above. Annuity: `r = rate / 100 / 12`, `n = years * 12`, `loan * r / ( 1 - ( 1 + r ) ** -n )`. Update the file docblock: the JS twin now also runs on the front end in the calculator block, so both copies must give the same results.

- [ ] **Step 4: Run the PHP checks.** Same command as step 2. Expected: `All checks passed.`

- [ ] **Step 5: Write the failing JS tests** in `investment.test.js` as `describe( 'Bank loan, 2026-11-01', … )`. Use the same cases and values as step 1 (`toBeCloseTo( value, 2 )`, and `toBeUndefined()` for null).

- [ ] **Step 6: Run and see them fail.**
Run: `npm run test:unit --workspace=plugins/ketunkoti-features`
Expected: FAIL, `calculateAnnuityPayment is not a function`.

- [ ] **Step 7: Implement the three JS exports** in `investment.js`, mirroring PHP. Update the file docblock as in step 3.

- [ ] **Step 8: Run the JS tests.** Same command as step 6. Expected: all pass.

---

### Task 2: `ketunkoti-blocks` plugin and the Euribor rate

**Files:**
- Create: `plugins/ketunkoti-blocks/ketunkoti-blocks.php`. Header: Plugin Name "Ketun koti Blocks", Requires at least 7.1, Requires PHP 8.2, `Requires Plugins: ketunkoti-features`, Text Domain `ketunkoti-blocks`. Constants `KETUNKOTI_BLOCKS_DIR` and `KETUNKOTI_BLOCKS_URL`, and it `require_once`s the includes.
- Create: `plugins/ketunkoti-blocks/includes/euribor.php`
- Create: `plugins/ketunkoti-blocks/package.json` (same shape as `ketunkoti-features/package.json`. Scripts: `build` = `wp-scripts build --webpack-src-dir=src/blocks --experimental-modules`, `start` = same with `start`, `lint:js`, `format`, `test:unit`. devDeps: `@wordpress/scripts ^30`. dependencies: `@wordpress/interactivity`)
- Create: `plugins/ketunkoti-blocks/tests/php/test-euribor.php` (same harness as `test-investment.php`: the `WP_CLI` guard, the `check()` helper and the exit code)
- Modify: root `package.json`. `build:plugins` and `watch:plugins` cover both plugin workspaces.
- Modify: `.wp-env.json`. Add `./plugins/ketunkoti-blocks` after `./plugins/ketunkoti-features`.
- Modify: `.github/workflows/deploy.yml`. Add `sync 'plugins/ketunkoti-blocks/' "${DEPLOY_PATH}/plugins/ketunkoti-blocks/"` and the path in the header comment.

**Interfaces (namespace `Ketunkoti\Blocks`):**
- `const EURIBOR_MARGIN = 0.6;`
- `parse_ecb_euribor( string $body ): ?float`. Reads `dataSets[0].series.<first key>.observations.<highest key>[0]`. Returns null for invalid JSON, a missing path, a non-numeric value, or a value outside −5…20.
- `fetch_euribor_12m(): ?float`. Calls `wp_remote_get( URL, [ 'timeout' => 5 ] )`. Returns null on `WP_Error` or a non-200 response, otherwise `parse_ecb_euribor( body )`. Logs failures with `error_log()` when `WP_DEBUG_LOG` is on.
- `get_euribor_12m(): ?float`. Transient hit → value. Miss → fetch. On success: set the transient for a day and update the option. On failure: read the option (may be false), store it in the transient for an hour (cache an empty result as the string `'none'` so `false` stays "miss"), and return it or null.
- `get_default_interest_rate(): ?float`. `null === euribor ? null : round( euribor + EURIBOR_MARGIN, 2 )`.

- [ ] **Step 1: Scaffold.** Create the plugin file, `package.json` and the `.wp-env.json` / root `package.json` / `deploy.yml` edits. Run `nvm use && npm install && npx wp-env start`.
Expected: `npx wp-env run cli wp plugin list --name=ketunkoti-blocks --field=status` prints `active`.

- [ ] **Step 2: Write the failing checks** in `test-euribor.php`. Stub HTTP with a `pre_http_request` filter that returns a canned array (`[ 'response' => [ 'code' => 200 ], 'body' => … ]`). Delete the transient and the option before each case.

```php
$ecb = '{"dataSets":[{"series":{"0:0:0:0:0:0:0":{"observations":{"0":[3.247,0,0,null,null]}}}}]}';
check( 'parse ok', 3.247, parse_ecb_euribor( $ecb ) );
check( 'malformed json', null, parse_ecb_euribor( '<html>' ) );
check( 'missing path', null, parse_ecb_euribor( '{"dataSets":[]}' ) );
check( 'out of range', null, parse_ecb_euribor( str_replace( '3.247', '42', $ecb ) ) );
// With the 200 stub:
check( 'fetch caches', 3.247, get_euribor_12m() );
check( 'option stored', 3.247, (float) get_option( 'ketunkoti_euribor_12m_last' ) );
check( 'default rate', 3.85, get_default_interest_rate() );
// With a 500 stub, transient deleted, option = 3.1:
check( 'http error uses fallback', 3.1, get_euribor_12m() );
// With a 500 stub, transient and option deleted:
check( 'no value ever', null, get_euribor_12m() );
check( 'no default rate', null, get_default_interest_rate() );
```

- [ ] **Step 3: Run and see them fail.**
Run: `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-euribor.php`
Expected: a fatal, undefined function `Ketunkoti\Blocks\parse_ecb_euribor`.

- [ ] **Step 4: Implement `includes/euribor.php`** per the interfaces.

- [ ] **Step 5: Run the checks.** Same command. Expected: `All checks passed.`

- [ ] **Step 6: Live check.**
Run: `npx wp-env run cli wp eval 'delete_transient("ketunkoti_euribor_12m"); var_dump( Ketunkoti\Blocks\get_euribor_12m() );'`
Expected: a float around 2–4 (the latest ECB monthly average).

---

### Task 3: Calculator layer (inputs → formatted results), PHP and JS twins

The block's state is strings (what the visitor typed). This layer parses them, calls the Task 1 formulas, and returns display strings plus one notice. PHP renders the first paint, and JS updates it live, so both must give identical text.

**Files:**
- Create: `plugins/ketunkoti-blocks/includes/yield-calculator.php`
- Create: `plugins/ketunkoti-blocks/src/blocks/yield-calculator/calculator.js`
- Test: `plugins/ketunkoti-blocks/tests/php/test-yield-calculator.php`, `plugins/ketunkoti-blocks/src/blocks/yield-calculator/calculator.test.js`

**Interfaces:**
- PHP (`Ketunkoti\Blocks`):
  - `parse_decimal( string $value ): ?float`. Strips spaces, U+00A0 and U+202F, and turns `,` into `.`. Returns null for empty or non-numeric input.
  - `get_calculator_home( int $post_id ): ?array`. Returns `\Ketunkoti\get_investment_inputs()`, or null when rent ≤ 0 or selling_price ≤ 0.
  - `get_calculator_defaults( array $home ): array{rent: string, ownCapital: string, years: string, rate: string}`. The strings use a decimal comma and no grouping: rent as stored (`780`, `151,3`), ownCapital = `round( selling * 0.25 / 100 ) * 100`, years `'25'`, and rate from `get_default_interest_rate()` formatted with two decimals (`'3,85'`), or `''`.
  - `calculate_calculator_results( array $home, array $fields ): array{rentalYield: string, cashFlow: string, equityReturn: string, notice: string}`. `$fields` has the same keys as the defaults.
- JS (`calculator.js`): `parseDecimal( value )` and `calculateResults( home, fields, formatNumber )`, which returns the same object. `home` uses the camelCase keys `rent, maintenance, capital, sellingPrice, debtFreePrice, interestRate`. It imports from `../../../../ketunkoti-features/src/investment` (relative workspace import, the spec's call).
- Consumes: Task 1's functions, plus the existing `calculateRentalYield` / `get_rental_yield` formula and `format_cash_flow` / `format_percentage` (JS: `formatCashFlow`, `formatPercentage` with `formatNumber` = `Intl.NumberFormat( 'fi' )` as in `investment.test.js`).

Result rules:
- `rentalYield`: the existing formula with the typed rent. `—` when unavailable.
- `cashFlow`: `calculate_leveraged_cash_flow` with the annuity payment for `max( 0, selling − ownCapital )`. A missing ownCapital counts as 0 (all loan). `—` when unavailable.
- `equityReturn`: `calculate_leveraged_equity_return`. `—` when unavailable.
- `notice`: the first that applies, otherwise `''`:
  1. rent empty → "Syötä vuokra."
  2. ownCapital empty or 0 → "Syötä omarahoitus."
  3. a bank loan exists and the rate or the years are missing → "Syötä korko ja laina-aika."
  4. equity unavailable because the home has no taloyhtiölainan korko → "Taloyhtiölainan korkoa ei ole kerrottu, joten oman pääoman tuottoa ei voi laskea."

- [ ] **Step 1: Write the failing JS tests** in `calculator.test.js`. Lahti home: `{ rent: 780, maintenance: 151.3, capital: 600.72, sellingPrice: 26000, debtFreePrice: 138725, interestRate: 4.3 }`.
  - `parseDecimal`: `'3,85'`→3.85, `'6 500'`→6500, `'6 500'`→6500, `''`→undefined, `'abc'`→undefined.
  - Defaults case `{ rent: '780', ownCapital: '6500', years: '25', rate: '3,85' }` → `{ rentalYield: '5,4 %', cashFlow: '−73 €/kk', equityReturn: '30,8 %', notice: '' }`.
  - No bank loan, `ownCapital: '26000'` → `cashFlow: '+28 €/kk'`, `equityReturn: '10,6 %'` (the listing's figures).
  - `rate: ''` → cashFlow `—`, equityReturn `—`, notice "Syötä korko ja laina-aika." (`notice for missing term`, also with `years: '0'`).
  - `ownCapital: ''` → equityReturn `—`, notice "Syötä omarahoitus."
  - home `interestRate: undefined` → equityReturn `—`, notice the taloyhtiölaina text, cashFlow still a number.
- [ ] **Step 2: Run and see them fail.** Run: `npm run test:unit --workspace=plugins/ketunkoti-blocks`. Expected: FAIL, module not found.
- [ ] **Step 3: Implement `calculator.js`.**
- [ ] **Step 4: Run the JS tests.** Same command. Expected: pass.
- [ ] **Step 5: Write the failing PHP checks** in `test-yield-calculator.php`, with the same cases and the same expected strings (`switch_to_locale( 'fi' )` as in the features test). Add `get_calculator_defaults` cases: selling 26000 → ownCapital `'6500'`, selling 138 725 → `'34700'`, and rent 151.3 → `'151,3'`. Add `get_calculator_home` returning null for a home without a selling price: create a throwaway post with `wp_insert_post`, then delete it.
- [ ] **Step 6: Run and see them fail.** Run: `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-yield-calculator.php`. Expected: an undefined function fatal.
- [ ] **Step 7: Implement `includes/yield-calculator.php`** and require it from the plugin file.
- [ ] **Step 8: Run the PHP checks.** Same command. Expected: `All checks passed.` If the fi locale's thousands separator differs from Intl's (an entity vs U+00A0), normalise PHP output to U+00A0 in this layer. The JS and PHP strings must be byte-identical.

---

### Task 4: The block and its placement

**Files:**
- Create in `plugins/ketunkoti-blocks/src/blocks/yield-calculator/`: `block.json`, `index.js`, `edit.js`, `render.php`, `view.js`, `style.scss`
- Modify: `plugins/ketunkoti-blocks/ketunkoti-blocks.php`. On `init`, register the block from `build/yield-calculator` (skip silently if missing, like `ketunkoti-features` does with its build).
- Placement: **not** added to `single-home.html`. Sami inserts the block into home content manually. For the checks below, insert it into the Lahti home's content with WP-CLI and remove it afterwards (read the latest revision before writing, because Sami edits in the editor too).

**Interfaces:**
- `block.json`: apiVersion 3, name `ketunkoti/yield-calculator`, title "Vuokratuottolaskuri", category `widgets`, textdomain `ketunkoti-blocks`, `supports: { interactivity: true, html: false, align: [ 'wide' ] }`, `render: file:./render.php`, `viewScriptModule: file:./view.js`, `style: file:./style-index.css`, `editorScript: file:./index.js`, `usesContext: [ 'postId' ]`.
- `render.php`: `$home = get_calculator_home( $block->context['postId'] ?? get_the_ID() )`. Empty output when null. Otherwise it outputs a `<section>` wrapper (`get_block_wrapper_attributes()`, `data-wp-interactive="ketunkoti/yield-calculator"`, `wp_interactivity_data_wp_context( [ 'home' => camelCase home, 'fields' => defaults, 'results' => calculate_calculator_results( … ) ] )`) containing:
  - an `h2` "Laske oma tuottosi" (*builder may reword in the site's tone*)
  - a `<dl>` of the locked figures: Myyntihinta, Velaton hinta, Hoitovastike, Pääomavastike, and Taloyhtiölainan korko when set (`format_interest_rate`)
  - four `<label>` + `<input type="text" inputmode="decimal">` pairs (Vuokra €/kk, Omarahoitus €, Laina-aika vuotta, Korko %), each `data-wp-bind--value="context.fields.<key>"` `data-wp-on--input="actions.update"` `name="<key>"`, with unique ids via `wp_unique_id()`
  - an `aria-live="polite"` `<dl>` with the three labels from Global Constraints and `data-wp-text="context.results.<key>"`
  - a `<p data-wp-text="context.results.notice" data-wp-bind--hidden="!context.results.notice">`.
  - BEM classes `yield-calculator__*`. Escape everything with `esc_html` / `esc_attr`.
- `view.js`: `store( 'ketunkoti/yield-calculator', { actions: { update( event ) } } )`. It writes `event.target.value` to `context.fields[ event.target.name ]`, then sets `context.results = calculateResults( context.home, context.fields, formatNumber )`.
- `edit.js`: `useBlockProps` + `ServerSideRender` from `@wordpress/server-side-render`, with `urlQueryArgs: { post_id }`. The `ketunkoti-features` sidebar already reads the postId the same way. On a non-home post it shows a `Placeholder` "Laskuri näkyy kodin sivulla."
- `style.scss`: a minimal readable layout with theme custom properties (`var(--wp--preset--spacing--40)`, colours `accent-5` / `accent-6` as the investment-figures pattern does), visible `:focus-visible` outlines on inputs, and a two-column grid from 40rem up. No other design work (spec non-goal).

- [ ] **Step 1: Create the block files** per the interfaces. Run `npm run build --workspace=plugins/ketunkoti-blocks`.
Expected: `build/yield-calculator/` contains `block.json`, `render.php`, `view.js` and `view.asset.php`, and `view.js` contains the bundled `calculateAnnuityPayment` (`grep -c calculateAnnuityPayment` or the minified equivalent → the import was resolved, not left as a bare path).
- [ ] **Step 2: Temporarily add the block** to the end of the Lahti home's `post_content` for testing (save the original content to the scratchpad first, and restore it after step 6).
- [ ] **Step 3: Server render check.**
Run: `curl -s http://localhost:8888/?p=534 | grep -o 'yield-calculator__[a-z-]*' | sort -u` (Lahti; look the ID up with `wp post list --post_type=home` if it differs).
Expected: the input, label and result classes, and the HTML contains `−73` and `30,8`.
- [ ] **Step 4: Hidden case.** curl a `home` without a myyntihinta (create a draft with WP-CLI, then delete it). Expected: no `yield-calculator` in the HTML.
- [ ] **Step 5: Editor check.** Open the Lahti home in the editor (http://localhost:8888/wp-admin). Expected: the block can be inserted from the inserter, shows its server preview without errors in the console, and on a non-home post shows the placeholder.
- [ ] **Step 6: Browser check** (Chrome DevTools MCP or Playwright on the Lahti page):
  - the first paint equals the curl output, and nothing changes on hydration
  - typing `26000` in Omarahoitus shows `+28 €/kk` and `10,6 %`
  - clearing Korko shows `—` and "Syötä korko ja laina-aika."
  - Tab reaches all four inputs with a visible focus ring
  - no console errors.
- [ ] **Step 7: Restore the Lahti content** from the saved copy and confirm with `wp post get <id> --field=post_content | grep -c yield-calculator` → `0`.
- [ ] **Step 8: Lint.** Run `npm run lint:js --workspace=plugins/ketunkoti-blocks` and `npm run lint:js --workspace=plugins/ketunkoti-features`. Expected: no errors. (The repo has no PHPCS setup. Note it for Sami instead of adding one.)
