# Investment calculator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A standalone "Sijoituslaskuri" block (`ketunkoti/investment-calculator`) that shows first-year figures, financing, ratios and two sensitivity tables from the investor's own inputs, with example defaults.

**Architecture:**
- **Loan maths.** First-year loan payments and interest for three lyhennystapa options go into the `ketunkoti-features` investment twin.
- **Shared helpers.** The number helpers now in the yield calculator move to shared modules in `ketunkoti-blocks`: `src/shared/numbers.js` and `includes/numbers.php`.
- **Calculator layer.** A new calculator layer with a PHP twin turns the typed fields into display strings.
- **The block.** A dynamic Interactivity API block renders the first paint on the server and updates live, with the same conventions as `yield-calculator`.

**Tech Stack:** PHP 8.2, WordPress 7.1 Interactivity API, `@wordpress/scripts` 37 (Vitest in `ketunkoti-blocks`, Jest in `ketunkoti-features`), wp-env.

**Spec:** `docs/superpowers/specs/2026-11-01-investment-calculator-design.md`

## Global Constraints

- PHP: 4-space indentation, PHPDoc on every function, `ABSPATH` guard. Namespaces: `Ketunkoti` (features) and `Ketunkoti\Blocks` (blocks).
- JS: tabs, JSDoc on every function. Run `npx wp-scripts format` on touched JS. Vitest tests import `describe`, `expect` and `test` from `vitest`.
- Text domain `ketunkoti-blocks`. All visible strings are Finnish and translatable. Notices are passed from PHP to JS through `wp_interactivity_state()`, as in `yield-calculator`.
- Every new calculation exists in **both** twins with the **same** test cases and byte-identical output strings. PHP output is normalised to Intl `fi`: U+00A0 for grouping and U+2212 for minus.
- Defaults: velaton hinta 90000, vuokra 590, pinta-ala 26, hoitovastike 4 €/m², omarahoitusosuus 30, korko `get_default_interest_rate()` (one decimal, or `''`), laina-aika 25, lyhennystapa `annuity`, tyhjät kuukaudet 0, vero 30, varainsiirtovero 1.5, remonttivara 0. Field values use dot decimals.
- Lyhennystapa values: `annuity` (Annuiteetti), `fixed` (Kiinteä tasaerä), `straight` (Tasalyhennys).
- Required inputs: velaton hinta, vuokra, pinta-ala and omarahoitusosuus. A required input that is empty or non-numeric shows `—` for every result and one notice. Velaton hinta and pinta-ala must also be > 0.
  - **Omarahoitusosuus 0 is valid.**
  - Optional cost fields (tyhjät kuukaudet, vero, varainsiirtovero, remonttivara) count as 0 when empty. Tyhjät kuukaudet is clamped to 0–12, and omarahoitusosuus to 0–100.
- Laina-aika < 1 counts as missing. Loan figures are needed only when the bank loan > 0.
- Block conventions as in `yield-calculator`:
  - no heading and no wrapper box styles
  - `<form>` with `data-wp-on--submit="actions.preventSubmit"`, where `preventSubmit` is wrapped in `withSyncEvent()`; no fieldset
  - `type="number"` inputs with visible labels
  - row separator lines, category `ketunkoti`, `supports.interactivity` and `align: [ 'wide' ]`.
- No commit steps: Sami commits himself. Run `nvm use` if a `.nvmrc` exists (there is none today, so the default Node 22 is fine). WP-CLI: `npx wp-env run cli wp ...` from the repo root.

## Review Focus

1. **Kiinteä tasaerä under a rate shock.** The payment stays at the base-rate annuity, so after-tax kassavirta goes **up** as the rate rises: more interest is deductible and the payment is the same (defaults: 73,76 → 89,69 → 105,76 → 138,35). This is mathematically right but surprising. Its notice must say that the loan term grows. Task 3 test `fixed shock keeps payment`.
2. **Payment below interest** (kiinteä tasaerä at +4 %: 325,62 € payment vs 409,50 € first-month interest). Interest is simulated on a growing balance; no negative or NaN output. Task 1 test `fixed payment below interest grows balance`.
3. **Omarahoitusosuus 0 %:** no division by zero. Equity is divided by oma pääoma = varainsiirtovero + remonttivara. If that is also 0, only oman pääoman tuotto shows `—`, with its own notice. Task 3 tests `share zero` and `no own money at all`.
4. **Vacant months 12** (or out-of-range values like 15 or −1): the income is negative, tax is 0, there is no NaN, and the value is clamped to 0–12. Task 3 test `vacancy twelve is a loss`.
5. **Select hydration:** the lyhennystapa `<select>` keeps the server-selected option when it hydrates, and changing it updates both tables. Manual check in Task 4, step 5.

---

### Task 1: First-year loan for three lyhennystapa options (features twin)

**Files:**
- Modify: `plugins/ketunkoti-features/includes/investment.php` and `src/investment.js` (add after the annuity function)
- Test: `plugins/ketunkoti-features/tests/php/test-investment.php` and `src/investment.test.js`

**Interfaces:**
- PHP: `calculate_first_year_loan( float $loan, ?float $annual_rate_percent, float $years, string $method, ?float $base_rate_percent = null ): ?array` returns `array{payments: float, interest: float}`.
  - For `fixed`, `$base_rate_percent` fixes the payment. When it is null, the current rate is used.
  - Returns `[ 'payments' => 0.0, 'interest' => 0.0 ]` when the loan is ≤ 0.
  - Returns null when the rate is null, years < 1 or the method is unknown.
- JS: `calculateFirstYearLoan( loan, annualRatePercent, years, method, baseRatePercent )` returns the same shape, or `undefined`.
- Consumes: `calculate_annuity_payment` and its JS twin. Simulate 12 months on the monthly rate (annual / 12):
  - `annuity` / `fixed`: payment p, interest b × r, b −= p − interest.
  - `straight`: repayment L / (years × 12), interest b × r, payment = repayment + interest.

- [ ] **Step 1: Write failing PHP checks.** Hand-checked on 2026-11-01 with loan 63000, rate 3.8 and 25 years:

```php
$y = calculate_first_year_loan( 63000, 3.8, 25, 'annuity' );
check( 'loan annuity payments', 3907.44, round( $y['payments'], 2 ) );   // 325.62 × 12
check( 'loan annuity interest', 2367.36, round( $y['interest'], 2 ) );
$y = calculate_first_year_loan( 63000, 3.8, 25, 'fixed', 3.8 );
check( 'loan fixed equals annuity at base', 2367.36, round( $y['interest'], 2 ) );
$y = calculate_first_year_loan( 63000, 3.8, 25, 'straight' );
check( 'loan straight payments', 4870.11, round( $y['payments'], 2 ) );   // 2520 repayment + 2350.11
check( 'loan straight interest', 2350.11, round( $y['interest'], 2 ) );
$y = calculate_first_year_loan( 63000, 7.8, 25, 'fixed', 3.8 );
check( 'fixed payment below interest grows balance', 3907.44, round( $y['payments'], 2 ) );
check( 'fixed shock interest finite', true, is_finite( $y['interest'] ) && $y['interest'] > 63000 * 0.078 );
check( 'loan none', [ 'payments' => 0.0, 'interest' => 0.0 ], calculate_first_year_loan( 0, 3.8, 25, 'annuity' ) );
check( 'loan short term', null, calculate_first_year_loan( 63000, 3.8, 0.5, 'annuity' ) );
check( 'loan no rate', null, calculate_first_year_loan( 63000, null, 25, 'annuity' ) );
check( 'loan unknown method', null, calculate_first_year_loan( 63000, 3.8, 25, 'bullet' ) );
check( 'loan zero rate straight', 2520.0, round( calculate_first_year_loan( 63000, 0.0, 25, 'straight' )['payments'], 2 ) );
```

  Recompute the two marked totals in the implementation step if rounding differs by a cent. The per-month values 325.62 and 4870.11 / 12 = 405.84 are the reference.

- [ ] **Step 2: Run them and see them fail.** Run `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-features/tests/php/test-investment.php`. Expected: undefined function fatal.
- [ ] **Step 3: Implement the PHP function.** Rerun the step 2 command. Expected: `All checks passed.`
- [ ] **Step 4: Write the same cases in `investment.test.js`** (`toBeCloseTo( x, 2 )`), and run `npm run test:unit --workspace=plugins/ketunkoti-features`. Expected: FAIL, not a function.
- [ ] **Step 5: Implement the JS twin.** Rerun the step 4 command. Expected: all pass.

---

### Task 2: Shared number helpers

Move the helpers that both calculators need out of the yield calculator, without changing behaviour, and add a euro formatter.

**Files:**
- Create: `plugins/ketunkoti-blocks/src/shared/numbers.js` and `numbers.test.js`
- Create: `plugins/ketunkoti-blocks/includes/numbers.php`. Require it in `ketunkoti-blocks.php` **before** `yield-calculator.php`.
- Modify: `src/blocks/yield-calculator/calculator.js` (import from shared) and `includes/yield-calculator.php` (functions moved out)

**Interfaces** (same names in both twins):
- JS exports:
  - `DASH`
  - `parseDecimal( value )`
  - `finiteOrUndefined( value )`
  - `formatPercentageOrDash( value, formatNumber )`
  - `formatEuros( value, formatNumber, decimals = 0, suffix = '' )`, which returns `'27 000 €'`, `'22,69 €/m²/kk'` style strings, or `DASH` when the value is undefined or not finite.
- PHP functions in `Ketunkoti\Blocks`:
  - `CALCULATOR_DASH` (move the const)
  - `parse_decimal`
  - `finite_or_null`
  - `normalise_formatted_number`
  - `format_percentage_or_dash`
  - `format_field_number`
  - `format_euros( ?float $value, int $decimals = 0, string $suffix = '' ): string`

- [ ] **Step 1: Write failing tests** for `formatEuros` and `format_euros`:
  - `( 27000 )` → `'27 000 €'`
  - `( 22.6923, 2, '/m²/kk' )` → `'22,69 €/m²/kk'`
  - `( 3461.54, 0, '/m²' )` → `'3 462 €/m²'`
  - `( undefined )` / `( null )` → `'—'`

  Add the PHP cases to a new `tests/php/test-numbers.php`, using the same harness as `test-yield-calculator.php`.
- [ ] **Step 2: Run them and see them fail.** Run `npm run test:unit --workspace=plugins/ketunkoti-blocks` and `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-numbers.php`.
- [ ] **Step 3: Move the helpers and implement `formatEuros` / `format_euros`.** The suffix follows a U+00A0 + `€`.
- [ ] **Step 4: Run all `ketunkoti-blocks` suites.** Run the JS command and the PHP tests: `test-numbers.php`, `test-yield-calculator.php` and `test-euribor.php`. Expected: all pass. The yield-calculator tests must pass unchanged.

---

### Task 3: Investment calculator layer (PHP and JS twins)

**Files:**
- Create: `plugins/ketunkoti-blocks/src/blocks/investment-calculator/calculator.js` and `calculator.test.js`
- Create: `plugins/ketunkoti-blocks/includes/investment-calculator.php` (require it in the plugin file) and `tests/php/test-investment-calculator.php`

**Interfaces:**
- PHP:
  - `get_investment_defaults(): array`. Keys: `debtFreePrice, rent, area, maintenancePerSquare, ownCapitalShare, rate, years, method, vacantMonths, taxRate, transferTaxRate, renovation`. All values are strings.
  - `get_investment_messages(): array`. Keys: `debtFreePrice, rent, area, ownCapitalShare, loan, ownMoney, fixedTerm`.
  - `calculate_investment_results( array $fields ): array`.
- JS: `calculateInvestmentResults( fields, formatNumber, messages = DEFAULT_MESSAGES )`.
- Result keys (all strings):
  - `ownCapital`, `bankLoan`, `monthlyPayment`
  - `rentalYield`, `cashFlowBeforeTax`, `cashFlowAfterTax`, `equityReturn`
  - `pricePerSquare`, `rentPerSquare`, `maintenancePerSquare`
  - `rateTable` (array of 4: base, +1, +2, +4 → kassavirta verojen jälkeen)
  - `vacancyTable` (array of 4: 0–3 vacant months → vuokratuotto)
  - `notice`
- Consumes: `calculate_first_year_loan`, the Task 2 helpers, `format_cash_flow` / `formatCashFlow` and `format_percentage` / `formatPercentage`.
- Formulas: from the spec's Outputs section, verbatim. Formats:
  - `formatEuros( value )` for euro sums
  - `formatEuros( payments / 12, …, 0, '/kk' )` for the monthly payment
  - `formatCashFlow` for cash flows
  - `formatPercentageOrDash` for percentages
  - `formatEuros( …, 0, '/m²' )` for the price per m², and `formatEuros( …, 2, '/m²/kk' )` for the rent and hoitovastike per m².
- Notice: the first that applies.
  1. Missing required inputs, in field order:
     - "Syötä velaton hinta."
     - "Syötä vuokra."
     - "Syötä pinta-ala."
     - "Syötä omarahoitusosuus."
  2. A loan needed but the rate or term is missing: "Syötä korko ja laina-aika."
  3. Oma pääoma 0: "Oman pääoman tuottoa ei voi laskea ilman omaa pääomaa."
  4. The method is `fixed` and the shocked payment is below the shocked interest: "Kiinteässä tasaerässä koronnousu pidentää laina-aikaa: erä pysyy samana, mutta lyhennys pienenee."

     Wording of 3 and 4 is the builder's, within the site's tone. Rule 4 fires whenever the method is `fixed`, so the reader understands why the table rises.

- [ ] **Step 1: Write the failing JS tests.** Defaults are as in Global Constraints, with `rate: '3.8'`. Hand-checked on 2026-11-01:
  - `defaults annuity`:
    - financing: ownCapital `'27 000 €'`, bankLoan `'63 000 €'`, monthlyPayment `'326 €/kk'`
    - results: rentalYield `'6,4 %'`, cashFlowBeforeTax `'+160 €/kk'`, cashFlowAfterTax `'+74 €/kk'`, equityReturn `'12,2 %'`
    - ratios: pricePerSquare `'3 462 €/m²'`, rentPerSquare `'22,69 €/m²/kk'`, maintenancePerSquare `'4,00 €/m²/kk'`
    - rateTable `['+74 €/kk','+54 €/kk','+33 €/kk','−16 €/kk']`
    - vacancyTable `['6,4 %','5,7 %','5,1 %','4,4 %']`
    - notice `''`
  - `fixed shock keeps payment`: method `fixed` → rateTable `['+74 €/kk','+90 €/kk','+106 €/kk','+138 €/kk']`, notice = the fixedTerm message.
  - `straight`: monthlyPayment `'406 €/kk'`, cashFlowBeforeTax `'+80 €/kk'`, cashFlowAfterTax `'−7 €/kk'`, equityReturn `'12,3 %'`, rateTable `['−7 €/kk','−43 €/kk','−79 €/kk','−151 €/kk']`.
  - `share 100 no loan`: bankLoan `'0 €'`, monthlyPayment `'0 €/kk'`, cashFlowBeforeTax `'+486 €/kk'`, cashFlowAfterTax `'+340 €/kk'`, equityReturn `'6,4 %'`, and rate `''` → still no notice.
  - `share zero`: ownCapital `'0 €'`, bankLoan `'90 000 €'`, equityReturn `'181,5 %'`, no notice.
  - `no own money at all`: share `'0'`, transferTaxRate `'0'`, renovation `'0'` → equityReturn `'—'` and the ownMoney notice. cashFlowAfterTax is still a number.
  - `vacancy twelve is a loss`: vacantMonths `'12'` → cashFlowBeforeTax `'−430 €/kk'` and cashFlowAfterTax `'−430 €/kk'` (no tax). `'15'` gives the same (clamped).
  - Missing inputs, one test each:
    - `debtFreePrice: ''` → every result `'—'`, notice "Syötä velaton hinta."
    - `area: '0'` → notice "Syötä pinta-ala."
    - `ownCapitalShare: ''` → "Syötä omarahoitusosuus."
    - `rate: ''` → loan notice, and cash flows, equityReturn and rateTable entries are `'—'`.
    - `years: '0.5'` → loan notice.
- [ ] **Step 2: Run and see it fail.** Run `npm run test:unit --workspace=plugins/ketunkoti-blocks`. Expected: module not found.
- [ ] **Step 3: Implement `calculator.js`.** Rerun the step 2 command. Expected: pass.
- [ ] **Step 4: Write the same cases in `test-investment-calculator.php`.** Pin the Euribor transient as in `test-yield-calculator.php`. Add one defaults check: `get_investment_defaults()['rate'] === '3.8'` with the transient at 3.247, and `''` with `'none'`. Run `npx wp-env run cli wp eval-file wp-content/plugins/ketunkoti-blocks/tests/php/test-investment-calculator.php`. Expected: undefined function fatal.
- [ ] **Step 5: Implement `includes/investment-calculator.php`.** Rerun the step 4 command. Expected: `All checks passed.`

---

### Task 4: The block

**Files:**
- Create in `plugins/ketunkoti-blocks/src/blocks/investment-calculator/`: `block.json`, `index.js`, `edit.js`, `render.php`, `view.js` and `style.scss`
- Modify: `plugins/ketunkoti-blocks/ketunkoti-blocks.php`. `register_blocks()` registers every folder in `build/` that has a `block.json` (loop), so later blocks need no code change.

**Interfaces:**
- `block.json`:
  - name `ketunkoti/investment-calculator`, title "Sijoituslaskuri", category `ketunkoti`, icon `calculator`
  - textdomain `ketunkoti-blocks`
  - supports `{ html: false, interactivity: true, align: [ 'wide' ] }`
  - `render`, `viewScriptModule`, `style` and `editorScript`, as in `yield-calculator`
  - no `usesContext`.
- `render.php`:
  - Calls `wp_interactivity_state( 'ketunkoti/investment-calculator', [ 'messages' => get_investment_messages() ] )`.
  - The context is `fields` (defaults) and `results` (`calculate_investment_results( defaults )`).
  - Markup:
    - **Inputs:** a `<form>` with grouped inputs. Visual group headings are allowed, but no fieldset. Number inputs get min/step/max:
      - years step 1, min 1
      - rate step 0.1, min 0
      - share 0–100
      - vacancy 0–12, step 1
      - percentages step 0.1.
    - **Lyhennystapa:** a `<select name="method">` with three `<option>`s. The server marks the default option `selected`. `data-wp-bind--value="context.fields.method"` and `data-wp-on--input="actions.update"`.
    - **Results:** an `aria-live="polite"` region with the financing, main results and ratios `<dl>`s.
    - **Tables:** two `<table>`s with `<caption>` ("Kassavirta verojen jälkeen, jos korko nousee" and "Vuokratuotto tyhjillä kuukausilla") and `<th scope="row">` labels. The cells use `data-wp-text="context.results.rateTable.0"` etc.
    - **Notice:** a notice paragraph, as in `yield-calculator`.
    - **Note:** a short explanatory note stating the financing model and "first-year estimate, not a binding offer".
- `view.js`:
  - `store( 'ketunkoti/investment-calculator', { actions: { preventSubmit: withSyncEvent( … ), update( event ) } } )`
  - `update` writes `event.target.value` to `context.fields[ event.target.name ]` and recalculates `context.results` with `calculateInvestmentResults( context.fields, formatNumber, state.messages )`.
- `edit.js`: `useBlockProps` and `ServerSideRender` inside `Disabled`. No post-type gate: it works anywhere.
- `style.scss`:
  - layout only: a two-column grid from 40rem
  - row separator lines as in `yield-calculator`
  - table cells right-aligned for numbers
  - visible `:focus-visible` on inputs and select
  - no wrapper box.

- [ ] **Step 1: Create the files and the `register_blocks()` loop.** Run `npm run build --workspace=plugins/ketunkoti-blocks`. Expected: `build/investment-calculator/` exists, and `view.js` contains `calculateFirstYearLoan` (bundled).
- [ ] **Step 2: Server render check.** Create a throwaway published page with `<!-- wp:ketunkoti/investment-calculator /-->` via WP-CLI, then curl it. Expected: `27 000 €`, `+74 €/kk` and `6,4 %` appear, with both captions.
- [ ] **Step 3: Editor endpoint check.** Use `rest_do_request` on `/wp/v2/block-renderer/ketunkoti/investment-calculator` as user 1. Expected: 200, and the rendered output contains `+74`.
- [ ] **Step 4: Check that the small calculator still renders.** curl the Lahti home (`/koti/kaksio-sijoitusasunnoksi-lahden-keskustan-kupeesta/`). Expected: `+127` is still present (with Euribor 3.247).
- [ ] **Step 5: Browser check (Playwright)** on the throwaway page:
  - the first paint equals the hydrated paint
  - switching the method to `straight` updates the payment to `406 €/kk` and the rate table
  - `fixed` shows the fixedTerm notice
  - share `0` keeps the figures
  - Enter does not reload
  - every input and the select has a label
  - 0 console warnings.
- [ ] **Step 6: Clean up.** Delete the throwaway page. Run `npm run lint:js --workspace=plugins/ketunkoti-blocks` and `npx wp-scripts lint-style "plugins/ketunkoti-blocks/src/**/*.scss"`. Expected: no problems.
