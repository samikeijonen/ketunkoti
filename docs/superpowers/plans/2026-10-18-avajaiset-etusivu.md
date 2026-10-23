# Avajaiset-etusivu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the ketunkoti.fi front page with a playful launch page whose only job is getting people onto the Buttondown list, illustrated with SVG fox characters.

**Architecture:** Five theme block patterns (`themes/ketunkoti/patterns/launch-*.php`) assembled onto the front page (page 7) via `wp:pattern` blocks. The existing synced Buttondown form (reusable block 46) gets better markup and lives only in the hero. Six SVG characters are theme assets inserted as core image blocks. All styling and motion go into `themes/ketunkoti/style.css`, compiled to `style.min.css`.

**Tech Stack:** WordPress 7.1 block theme, core blocks, PHP 8.3 pattern files, plain CSS (postcss + cssnano build), hand-written SVG, wp-env (Docker), WP-CLI, Buttondown embed form.

**Spec:** `docs/superpowers/specs/2026-10-18-avajaiset-etusivu-design.md`

## Global Constraints

- Copy language: Finnish. Code comments and commit messages: English.
- Tone: playful and humorous, never sarcastic toward the reader.
- Colours in SVGs: only `#B58A63`, `#A27A55`, `#5C4632`, `#F5EFE2`, `#2E2419`.
- CSS uses theme custom properties (`--wp--preset--color--*`, `--wp--preset--spacing--*`, `--wp--preset--font-size--*`) wherever one exists.
- Characters are decorative: `alt=""`.
- Motion only `transform` / `opacity`, only inside `@media (prefers-reduced-motion: no-preference)`, never looping.
- The signup form (block 46) appears at most once per page.
- No new plugins, no new blocks, no AJAX.
- **Do not commit.** The user commits at the end. Each task ends with a "hand off" step instead.
- Node: run `source ~/.nvm/nvm.sh && nvm use` before npm (the repo has no `.nvmrc`; if `nvm use` complains, the default version is fine).
- Build CSS: `npm run build:theme` from the repo root.
- WP-CLI: `docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp ...` (call it `$CLI` below). If the container name changed, find it with `docker ps --format '{{.Names}}' | grep cli-1 | grep -v tests`.
- Local site: `http://localhost:8888`. `SCRIPT_DEBUG` is on locally, so the unminified `style.css` is served; still rebuild `style.min.css` every time.
- Browser checks use the Playwright MCP tools. The stylesheet URL is versioned `?ver=1.0`, so after a CSS change, bust the cache in the page before measuring:
  ```js
  async () => { const l = document.querySelector('link#ketunkoti-style-css'); await new Promise(r => { l.onload = r; l.href = l.href.split('?')[0] + '?v=' + Date.now(); }); }
  ```

## Review Focus

1. **Narrow phones (360px):** the peeking fox must not cover the label, input text or button, and the page must not scroll horizontally. Test in Task 5 Step 5 and Task 6 Step 4.
2. **Keyboard user following the closing CTA:** after activating *Haluan etuajo-oikeuden* (`#liity`), the next Tab must land on the email input, not back at the top of the page. Test in Task 6 Step 5.
3. **Browser autofill and long addresses:** an autofilled or 60-character email must stay readable inside the input and not push the button off-screen. Test in Task 3 Step 6.
4. **Production block ID:** patterns reference `wp:block {"ref":46}`. If 46 is not the Buttondown form on production, the hero has no form. Test in Task 7 Step 1.
5. **Launch tag outliving the launch:** the hidden `tag=avajaiset` is in the synced block, so every signup everywhere gets the tag until it is removed. Documented in Task 7 Step 4.

---

### Task 1: Style sample `fox-peek.svg` (user approval gate)

**Files:**
- Create: `themes/ketunkoti/assets/images/characters/fox-peek.svg`

**Interfaces:**
- Consumes: logo mark geometry from `themes/ketunkoti/assets/images/brand/ketunkoti-mark.svg` (100×100 viewBox).
- Produces: the visual language (head construction, palette use, paw shape) every later character copies. File path used by Task 5.

- [ ] **Step 1: Create the characters folder and the SVG**

The head is the logo mark, unchanged, scaled to 0.9 and centred. Two paws grip the bottom edge, as if the fox is holding the top of the form. The bottom of the viewBox is the "edge" the fox peeks over.

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" aria-hidden="true">
	<defs>
		<clipPath id="fp-right"><rect x="50" width="50" height="100"/></clipPath>
		<mask id="fp-head"><path fill="#fff" d="M14 46 Q8 24 14 4 Q30 12 42 30 H58 Q70 12 86 4 Q92 24 86 46 Q90 60 76 70 Q62 82 50 92 Q38 82 24 70 Q10 60 14 46 Z"/></mask>
	</defs>
	<g transform="translate(5 2) scale(0.9)">
		<path fill="#B58A63" fill-rule="evenodd" stroke="#B58A63" stroke-width="6" stroke-linejoin="round" d="M14 46 Q8 24 14 4 Q30 12 42 30 H58 Q70 12 86 4 Q92 24 86 46 Q90 60 76 70 Q62 82 50 92 Q38 82 24 70 Q10 60 14 46 Z M40.5 50 V39.5 L50 31 L59.5 39.5 V50 Z"/>
		<path fill="#A27A55" fill-rule="evenodd" stroke="#A27A55" stroke-width="6" stroke-linejoin="round" clip-path="url(#fp-right)" d="M14 46 Q8 24 14 4 Q30 12 42 30 H58 Q70 12 86 4 Q92 24 86 46 Q90 60 76 70 Q62 82 50 92 Q38 82 24 70 Q10 60 14 46 Z M40.5 50 V39.5 L50 31 L59.5 39.5 V50 Z"/>
		<path fill="#5C4632" d="M19 40 Q15 24 18 12 Q29 18 37 30 Z M81 40 Q85 24 82 12 Q71 18 63 30 Z"/>
		<path fill="#F5EFE2" mask="url(#fp-head)" d="M14 60 Q30 62 42 70 Q50 66 58 70 Q70 62 86 60 Q78 74 64 82 Q56 88 50 94 Q44 88 36 82 Q22 74 14 60 Z"/>
		<path fill="none" stroke="#2E2419" stroke-width="3.5" stroke-linecap="round" d="M27 54 Q33 48 39 54 M61 54 Q67 48 73 54"/>
		<path fill="#2E2419" d="M41 71 Q50 67 59 71 Q58 80 50 83 Q42 80 41 71 Z"/>
	</g>
	<!-- Paws gripping the edge. -->
	<path fill="#B58A63" d="M14 100 V90 Q14 83 22 83 H32 Q40 83 40 90 V100 Z"/>
	<path fill="#A27A55" d="M60 100 V90 Q60 83 68 83 H78 Q86 83 86 90 V100 Z"/>
	<path fill="none" stroke="#2E2419" stroke-width="2" stroke-linecap="round" d="M23 85 V91 M31 85 V91 M69 85 V91 M77 85 V91"/>
</svg>
```

Note: the head is not given `role="img"` / `aria-label` like the logo, because characters are decorative.

- [ ] **Step 2: Validate the file**

Run:
```bash
cd /Users/samikeijonen/projects/ketunkoti/themes/ketunkoti/assets/images/characters
xmllint --noout fox-peek.svg && echo XML-OK
wc -c fox-peek.svg
grep -oE '#[0-9A-Fa-f]{6}' fox-peek.svg | sort -u
```
Expected: `XML-OK`; size under 4000 bytes; colours only from the Global Constraints palette (plus `#fff` inside the mask, which is allowed because masks are not rendered colour).

- [ ] **Step 3: Render it for the user**

Open `http://localhost:8888/wp-content/themes/ketunkoti/assets/images/characters/fox-peek.svg` with Playwright, set the viewport to 400×400, and take a screenshot into `.playwright-mcp/fox-peek.png`. Read the image yourself first: the head must be recognisably the logo fox, with paws at the bottom edge.

- [ ] **Step 4: STOP: user approves the style**

Show the screenshot to the user and ask whether the style (head, paws, proportions) is right. Apply their changes to this file and re-render until they approve. **Do not start Task 2 before approval.** Record any agreed style rules (e.g. "paws darker", "eyes open") at the top of Task 2 before continuing.

- [ ] **Step 5: Hand off**

No commit. Tell the user the file is ready for their commit.

---

### Task 2: Remaining five characters

**Files:**
- Create: `themes/ketunkoti/assets/images/characters/fox-sit.svg`
- Create: `themes/ketunkoti/assets/images/characters/fox-key.svg`
- Create: `themes/ketunkoti/assets/images/characters/fox-shh.svg`
- Create: `themes/ketunkoti/assets/images/characters/fox-sleep.svg`
- Create: `themes/ketunkoti/assets/images/characters/fox-wait.svg`

**Interfaces:**
- Consumes: the approved head construction from Task 1 (the six head paths, used verbatim inside a `<g transform>`). Every file must use its own `clipPath` / `mask` ids (prefixes below) so ids never collide if a file is ever inlined.
- Produces: five files at the paths above, used by Task 5.

Shared body language for all five:
- Head: the Task 1 `<g>` block, verbatim, with a per-file `transform`.
- Body: a rounded pear shape, `#B58A63` left half and `#A27A55` right half (same clip trick as the head), with a `#F5EFE2` chest patch.
- Tail: a big curved teardrop in `#B58A63` with a `#F5EFE2` tip.
- Legs and paws: short rounded rectangles, `#5C4632` paws.
- Details (eyes, nose, toe lines, props' outlines): `#2E2419`, stroke width 2–3.5, round caps.
- Props (key, envelope, clock face): `#F5EFE2` with `#2E2419` lines.
- Flat fills only, no outline around the whole figure.

Per-file composition. The bottom of each viewBox is where the figure stands or sits:

| File | id prefix | viewBox | Composition |
|---|---|---|---|
| `fox-sit.svg` | `fs-` | `0 0 100 130` | Head at top (`translate(10 0) scale(0.8)`), body below, sitting on the bottom edge of the viewBox with both hind legs **hanging below the body to the bottom edge** (the edge is the top of the photo the fox sits on), tail hanging straight down beside the legs. |
| `fox-key.svg` | `fk-` | `0 0 140 110` | Running, side-ish view: body horizontal, two legs forward and two back, tail streaming behind (left). Head at the right (`translate(60 0) scale(0.75)`). A `#F5EFE2` key held horizontally across the muzzle, ring on one side, bit on the other. |
| `fox-shh.svg` | `fh-` | `0 0 100 130` | Standing front view, head on top (`translate(10 0) scale(0.8)`). One paw raised to in front of the mouth/nose. The right eye stays the closed arc; the left eye becomes a wink: a short straight line plus an arc. |
| `fox-sleep.svg` | `fz-` | `0 0 140 100` | Curled into a ball lying on a `#F5EFE2` envelope (rectangle with a V flap line). Head small at one side (`translate(20 30) scale(0.55)`), tail wrapped around the front covering the paws. Two "z" letters drawn as paths (not text) rising above the head, `#2E2419`. |
| `fox-wait.svg` | `fw-` | `0 0 100 130` | Sitting upright, head on top (`translate(10 0) scale(0.8)`), tail curled around the feet. Holding a round `#F5EFE2` clock with two hands (`#2E2419`) at about 10:10 in front of the chest. |

- [ ] **Step 1: Draw `fox-sit.svg`** following the table and the shared body language.
- [ ] **Step 2: Draw `fox-key.svg`.**
- [ ] **Step 3: Draw `fox-shh.svg`.**
- [ ] **Step 4: Draw `fox-sleep.svg`.**
- [ ] **Step 5: Draw `fox-wait.svg`.**

- [ ] **Step 6: Validate all five**

Run:
```bash
cd /Users/samikeijonen/projects/ketunkoti/themes/ketunkoti/assets/images/characters
for f in fox-*.svg; do xmllint --noout "$f" && printf '%s %s bytes\n' "$f" "$(wc -c < "$f")"; done
grep -ohE '#[0-9A-Fa-f]{6}' fox-*.svg | sort -u
grep -L 'aria-hidden="true"' fox-*.svg
grep -oh 'id="[a-z]*-' fox-*.svg | sort | uniq -c
```
Expected: six files listed with no xmllint errors, each under 4000 bytes; the colour list is only the five palette colours; the `grep -L` prints nothing; each id prefix appears in exactly one file.

- [ ] **Step 7: Visual check and user review**

Render each file in Playwright at 400×400, screenshot to `.playwright-mcp/<name>.png`, read each screenshot, and fix anything that does not read as its pose (e.g. the key not visible, the wink not readable). Show all five to the user and apply their feedback.

- [ ] **Step 8: Hand off** (no commit).

---

### Task 3: Signup form markup and styles

**Files:**
- Modify: reusable block 46 in the local database (via WP-CLI)
- Create: `docs/superpowers/plans/assets/signup-form-block-46.html` (the new block content, kept in the repo so production can be updated from it)
- Modify: `themes/ketunkoti/style.css` (append a new section after the overlay menu animation section)
- Modify: `themes/ketunkoti/style.min.css` (build output)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: form classes `ketunkoti-signup`, `ketunkoti-signup__label`, `ketunkoti-signup__row`, `ketunkoti-signup__input`, `ketunkoti-signup__submit`, `ketunkoti-signup__note`; wrapper modifier `.launch-signup.is-centered` (Task 5 uses it). Element ids `ketunkoti-signup-email` and `ketunkoti-signup-note`.

- [ ] **Step 1: Verify Buttondown's `tag` field**

Read Buttondown's documentation on embedded subscribe forms (WebFetch `https://docs.buttondown.com/` and search for "embed" / "tags"). Confirm the exact hidden field name that tags subscribers from an embed form (expected: `tag`). If tagging via embed is not supported, remove the hidden `tag` input in Step 3 and tell the user.

- [ ] **Step 2: Back up the current block 46**

Run:
```bash
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post get 46 --field=post_content > /private/tmp/claude-501/-Users-samikeijonen-projects-ketunkoti/34c108c6-6a4a-4a05-a632-1e543a04ad8d/scratchpad/block-46-backup.html
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post get 46 --field=post_type
```
Expected: the backup contains the current `embeddable-buttondown-form` form; post type `wp_block`.

- [ ] **Step 3: Write the new block content**

Create `docs/superpowers/plans/assets/signup-form-block-46.html`:

```html
<!-- wp:html -->
<form action="https://buttondown.com/api/emails/embed-subscribe/ketunkoti" method="post" class="ketunkoti-signup">
	<label class="ketunkoti-signup__label" for="ketunkoti-signup-email">Sähköpostiosoite</label>
	<div class="ketunkoti-signup__row">
		<input class="ketunkoti-signup__input" type="email" name="email" id="ketunkoti-signup-email" required autocomplete="email" aria-describedby="ketunkoti-signup-note">
		<input type="hidden" name="tag" value="avajaiset">
		<button type="submit" class="ketunkoti-signup__submit wp-element-button">Haluan etuajo-oikeuden</button>
	</div>
	<p class="ketunkoti-signup__note" id="ketunkoti-signup-note">Ei roskapostia. Kettu lupaa. Voit perua milloin vain. <a href="/tietosuojaselosta/">Tietosuojaseloste</a></p>
</form>
<!-- /wp:html -->
```

- [ ] **Step 4: Update block 46**

Run:
```bash
docker exec -i 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post update 46 - < /Users/samikeijonen/projects/ketunkoti/docs/superpowers/plans/assets/signup-form-block-46.html
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post get 46 --field=post_content | grep -c ketunkoti-signup
```
Expected: `Success: Updated post 46.` and a count of at least 1.

- [ ] **Step 5: Add the form styles**

Append to `themes/ketunkoti/style.css`, after the overlay menu animation block:

```css
/*
 * Signup form (synced block "Tilaa Ketun kodit", Buttondown embed).
 *
 * Label above, then the email field and button on one row once there is room
 * for both, stacked full width on narrow screens. The surrounding
 * `.launch-signup.is-centered` group centres the whole form (front page hero);
 * elsewhere it sits at the start of the content column.
 */
.ketunkoti-signup {
	max-width: 34rem;
}

.launch-signup.is-centered .ketunkoti-signup {
	margin-inline: auto;
}

.ketunkoti-signup__label {
	display: block;
	margin-bottom: var(--wp--preset--spacing--20);
	font-weight: 600;
}

.ketunkoti-signup__row {
	display: flex;
	flex-wrap: wrap;
	gap: var(--wp--preset--spacing--20);
}

/* `min-width: 0` lets a long autofilled address shrink instead of overflowing. */
.ketunkoti-signup__input {
	flex: 1 1 14rem;
	min-width: 0;
	padding: 0.75rem 1rem;
	border: 1px solid var(--wp--preset--color--contrast);
	border-radius: 0;
	background-color: var(--wp--preset--color--accent-5);
	color: var(--wp--preset--color--contrast);
	font: inherit;
}

.ketunkoti-signup__input:focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

.ketunkoti-signup__submit {
	flex: 1 0 auto;
	cursor: pointer;
}

.ketunkoti-signup__note {
	margin-top: var(--wp--preset--spacing--20);
	font-size: var(--wp--preset--font-size--small);
}
```

Then build:
```bash
cd /Users/samikeijonen/projects/ketunkoti && source ~/.nvm/nvm.sh && nvm use; npm run build:theme
grep -c 'ketunkoti-signup__row' themes/ketunkoti/style.min.css
```
Expected: build succeeds; count 1.

- [ ] **Step 6: Browser check on page 45 (Tilaa Ketun kodit)**

Navigate to `http://localhost:8888/tilaa-ketun-kodit/`, bust the CSS cache (Global Constraints snippet), then at widths 360, 900 and 1280 run:

```js
() => {
	const input = document.querySelector('#ketunkoti-signup-email');
	input.value = 'etunimi.erittain.pitka.sukunimi.testi@esimerkkiyritys-oy.fi';
	const row = document.querySelector('.ketunkoti-signup__row');
	const btn = document.querySelector('.ketunkoti-signup__submit');
	const r = row.getBoundingClientRect(), b = btn.getBoundingClientRect();
	return {
		vw: innerWidth,
		hScroll: document.documentElement.scrollWidth > innerWidth,
		buttonInsideRow: b.right <= r.right + 1,
		label: input.labels[0]?.textContent,
		describedBy: document.getElementById(input.getAttribute('aria-describedby'))?.textContent.slice(0, 20),
		sameRow: Math.abs(input.getBoundingClientRect().top - b.top) < 2,
	};
}
```
Expected at every width: `hScroll: false`, `buttonInsideRow: true`, `label: "Sähköpostiosoite"`, `describedBy: "Ei roskapostia. Kett"`. `sameRow` is `false` at 360 and `true` at 1280.

Then Tab into the input with the keyboard (Playwright `browser_press_key` Tab until `document.activeElement.id === 'ketunkoti-signup-email'`) and screenshot: the focus ring must be visible.

- [ ] **Step 7: Hand off** (no commit).

---

### Task 4: Launch page and character styles

**Files:**
- Modify: `themes/ketunkoti/style.css` (append after the signup form section)
- Modify: `themes/ketunkoti/style.min.css` (build output)

**Interfaces:**
- Consumes: nothing.
- Produces: classes used by Task 5 patterns: `launch-hero`, `launch-eyebrow`, `launch-signup` (+ `is-centered`), `launch-image`, `launch-card`, `launch-cta`, `launch-character`, `launch-character--peek`, `launch-character--sit`, `launch-character--card`, `launch-character--wait`.

Design note: the spec asked for the tail to wag on hover. Characters are `<img>` files, which page CSS cannot reach inside, so the whole card character gives a single wiggle instead. Tell the user about this in Step 3.

- [ ] **Step 1: Append the styles**

```css
/*
 * Launch front page (avajaiset).
 *
 * Fox characters are decorative SVG images placed with these classes. Sizes
 * use `clamp()` so they scale with the viewport without media queries.
 */
.launch-eyebrow {
	letter-spacing: 0.08em;
	text-transform: uppercase;
}

.launch-character {
	margin: 0;
	pointer-events: none;
}

.launch-character img {
	display: block;
	width: 100%;
	height: auto;
}

/*
 * Hero: the fox sits behind the form and peeks over its top edge on the
 * right, so the label on the left stays clear. The row and note sit above it.
 */
.launch-signup {
	position: relative;
}

.launch-character--peek {
	position: absolute;
	top: 0;
	right: 1rem;
	z-index: 0;
	width: clamp(4rem, 12vw, 6rem);
	aspect-ratio: 1;
	transform: translateY(-62%);
}

.launch-signup .ketunkoti-signup__row,
.launch-signup .ketunkoti-signup__note {
	position: relative;
	z-index: 1;
}

/* Image: the fox sits on the photo's top edge with its legs over the edge. */
.launch-image {
	position: relative;
}

.launch-character--sit {
	position: absolute;
	top: 0;
	right: 8%;
	z-index: 1;
	width: clamp(4.5rem, 12vw, 8rem);
	aspect-ratio: 100 / 130;
	transform: translateY(-80%);
}

/* Reason cards: the same surface as the home cards. */
.launch-card {
	padding: var(--wp--preset--spacing--40);
	border: 1px solid var(--wp--preset--color--accent-6);
	border-radius: 8px;
	background-color: var(--wp--preset--color--accent-5);
}

.launch-character--card {
	width: clamp(5rem, 10vw, 6.5rem);
}

.launch-character--wait {
	width: clamp(5rem, 12vw, 7rem);
	margin-inline: auto;
}

@media (prefers-reduced-motion: no-preference) {
	/* The hero fox rises from behind the form once, shortly after load. */
	@keyframes ketunkoti-peek-in {
		from {
			opacity: 0;
			transform: translateY(0);
		}

		to {
			opacity: 1;
			transform: translateY(-62%);
		}
	}

	.launch-character--peek {
		animation: ketunkoti-peek-in 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.4s both;
	}

	/* Card foxes tilt in as their card scrolls into view. */
	@supports (animation-timeline: view()) {
		@keyframes ketunkoti-tilt-in {
			from {
				opacity: 0;
				transform: translateY(0.75rem) rotate(-8deg);
			}

			to {
				opacity: 1;
				transform: none;
			}
		}

		.launch-character--card {
			animation: ketunkoti-tilt-in linear both;
			animation-timeline: view();
			animation-range: entry 10% cover 30%;
		}
	}

	/*
	 * A single wiggle when the card is hovered. On the inner image, so it does
	 * not fight the scroll animation on the figure.
	 */
	@keyframes ketunkoti-wiggle {
		0%,
		100% {
			transform: none;
		}

		30% {
			transform: rotate(-6deg);
		}

		60% {
			transform: rotate(4deg);
		}
	}

	.launch-card:hover .launch-character--card img {
		animation: ketunkoti-wiggle 0.5s ease-in-out;
	}
}
```

- [ ] **Step 2: Build**

```bash
cd /Users/samikeijonen/projects/ketunkoti && source ~/.nvm/nvm.sh && nvm use; npm run build:theme
grep -o 'ketunkoti-peek-in' themes/ketunkoti/style.min.css | head -1
```
Expected: build succeeds; prints `ketunkoti-peek-in`.

- [ ] **Step 3: Hand off** (no commit). Mention the tail-wag → whole-fox wiggle change to the user.

The classes are exercised in the browser in Task 6, once the patterns exist.

---

### Task 5: Launch patterns

**Files:**
- Create: `themes/ketunkoti/patterns/launch-hero.php`
- Create: `themes/ketunkoti/patterns/launch-image.php`
- Create: `themes/ketunkoti/patterns/launch-reasons.php`
- Create: `themes/ketunkoti/patterns/launch-faq.php`
- Create: `themes/ketunkoti/patterns/launch-cta.php`

**Interfaces:**
- Consumes: character files from Tasks 1–2; classes from Tasks 3–4; reusable block 46; image attachment 84 (`/wp-content/uploads/2026/09/009-1024x576.webp`, already in the media library); accordion attribute `ketunkotiFaqSchema` from `plugins/ketunkoti-features/includes/faq-schema.php`.
- Produces: pattern slugs `ketunkoti/launch-hero`, `ketunkoti/launch-image`, `ketunkoti/launch-reasons`, `ketunkoti/launch-faq`, `ketunkoti/launch-cta`; anchor `#liity` on the hero group.

All five use the header format of `patterns/homes-grid.php` and category `ketunkoti_page` (registered in `functions.php`).

- [ ] **Step 1: `launch-hero.php`**

```php
<?php
/**
 * Title: Avajaiset: yläosa ja tilauslomake
 * Slug: ketunkoti/launch-hero
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Centred launch hero with eyebrow, heading, lead, the signup form and a fox peeking over the form.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"anchor":"liity","align":"full","className":"launch-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div id="liity" class="wp-block-group alignfull launch-hero" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:paragraph {"className":"launch-eyebrow","style":{"typography":{"textAlign":"center"}},"fontSize":"small"} -->
	<p class="has-text-align-center launch-eyebrow has-small-font-size"><?php echo esc_html__( 'Ketun koti avaa ovensa', 'ketunkoti' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}}} -->
	<h1 class="wp-block-heading has-text-align-center"><?php echo wp_kses( __( 'Parhaat kodit eivät koskaan päädy <em>nettiin</em>', 'ketunkoti' ), array( 'em' => array() ) ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size"><?php echo esc_html__( 'Uudet sijoitusasuntomme myydään ensin Ketun kodit -listalle. Netistä niitä on turha etsiä – kettu kuiskaa ne vain listalaisille.', 'ketunkoti' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"launch-signup is-centered","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group launch-signup is-centered" style="margin-top:var(--wp--preset--spacing--60)">
		<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"launch-character launch-character--peek"} -->
		<figure class="wp-block-image size-full launch-character launch-character--peek"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/characters/fox-peek.svg' ) ); ?>" alt=""/></figure>
		<!-- /wp:image -->

		<!-- wp:block {"ref":46} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 2: `launch-image.php`**

```php
<?php
/**
 * Title: Avajaiset: kuva ja istuva kettu
 * Slug: ketunkoti/launch-image
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Wide interior photo with a fox sitting on its top edge.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"align":"wide","className":"launch-image","style":{"spacing":{"margin":{"top":"var:preset|spacing|70"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide launch-image" style="margin-top:var(--wp--preset--spacing--70)">
	<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"launch-character launch-character--sit"} -->
	<figure class="wp-block-image size-full launch-character launch-character--sit"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/characters/fox-sit.svg' ) ); ?>" alt=""/></figure>
	<!-- /wp:image -->

	<!-- wp:image {"id":84,"aspectRatio":"16/9","scale":"cover","sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large"><img src="<?php echo esc_url( content_url( 'uploads/2026/09/009-1024x576.webp' ) ); ?>" alt="" class="wp-image-84" style="aspect-ratio:16/9;object-fit:cover"/></figure>
	<!-- /wp:image -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 3: `launch-reasons.php`**

```php
<?php
/**
 * Title: Avajaiset: miksi listalle
 * Slug: ketunkoti/launch-reasons
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Heading and three reason cards, each with a fox character.
 *
 * @package Ketunkoti
 */

$ketunkoti_reasons = array(
	array(
		'image' => 'fox-key.svg',
		'title' => __( 'Ensimmäisenä apajilla', 'ketunkoti' ),
		'text'  => __( 'Uudet kohteet tulevat listalle ennen kuin kukaan muu kuulee niistä.', 'ketunkoti' ),
	),
	array(
		'image' => 'fox-shh.svg',
		'title' => __( 'Ei nettiin, ei hälinää', 'ketunkoti' ),
		'text'  => __( 'Ei hintakilpailuja eikä satojen katsojien näyttöjä, vain sinä ja hyvä kohde.', 'ketunkoti' ),
	),
	array(
		'image' => 'fox-sleep.svg',
		'title' => __( 'Harvoin mutta hyvää', 'ketunkoti' ),
		'text'  => __( 'Postia tulee vain, kun on oikeasti asiaa. Kettu ei jaarittele.', 'ketunkoti' ),
	),
);

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|80"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Miksi listalle?', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50","margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"grid","minimumColumnWidth":"16rem"}} -->
	<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60)">
		<?php foreach ( $ketunkoti_reasons as $ketunkoti_reason ) : ?>
		<!-- wp:group {"className":"launch-card","layout":{"type":"default"}} -->
		<div class="wp-block-group launch-card">
			<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"launch-character launch-character--card"} -->
			<figure class="wp-block-image size-full launch-character launch-character--card"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/characters/' . $ketunkoti_reason['image'] ) ); ?>" alt=""/></figure>
			<!-- /wp:image -->

			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading"><?php echo esc_html( $ketunkoti_reason['title'] ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php echo esc_html( $ketunkoti_reason['text'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 4: `launch-faq.php`**

The accordion markup copies the format already saved on the FAQ page (page 282), including `ketunkotiFaqSchema`.

```php
<?php
/**
 * Title: Avajaiset: usein kysytyt
 * Slug: ketunkoti/launch-faq
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Launch FAQ accordion with FAQ schema enabled.
 *
 * @package Ketunkoti
 */

$ketunkoti_faq = array(
	__( 'Maksaako listalle liittyminen?', 'ketunkoti' )  => __( 'Ei maksa mitään. Kettu ei edes ota tippiä.', 'ketunkoti' ),
	__( 'Sitoudunko ostamaan jotain?', 'ketunkoti' )     => __( 'Et. Katsot, kiinnostaako, ja jos ei, kettu ei loukkaannu (paljon).', 'ketunkoti' ),
	__( 'Kuinka usein postia tulee?', 'ketunkoti' )      => __( 'Vain kun uusi kohde on tulossa myyntiin.', 'ketunkoti' ),
	__( 'Miten perun tilauksen?', 'ketunkoti' )          => __( 'Jokaisen viestin lopussa on linkki. Yksi klikkaus, eikä kysellä perään.', 'ketunkoti' ),
);

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|80"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html__( 'Usein kysytyt', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:accordion {"ketunkotiFaqSchema":true} -->
	<div role="group" class="wp-block-accordion">
		<?php foreach ( $ketunkoti_faq as $ketunkoti_question => $ketunkoti_answer ) : ?>
		<!-- wp:accordion-item {"style":{"border":{"bottom":{"color":"var:preset|color|accent-6","width":"1px"}},"spacing":{"padding":{"bottom":"var:preset|spacing|xl-l"}}}} -->
		<div class="wp-block-accordion-item" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-width:1px;padding-bottom:var(--wp--preset--spacing--xl-l)"><!-- wp:accordion-heading {"fontSize":"large"} -->
		<h3 class="wp-block-accordion-heading has-icon has-icon-right has-large-font-size"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html( $ketunkoti_question ); ?></span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
		<!-- /wp:accordion-heading -->

		<!-- wp:accordion-panel -->
		<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
		<p><?php echo esc_html( $ketunkoti_answer ); ?></p>
		<!-- /wp:paragraph --></div>
		<!-- /wp:accordion-panel --></div>
		<!-- /wp:accordion-item -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:accordion -->
</div>
<!-- /wp:group -->
```

Before saving, open page 282's raw content (`$CLI wp post get 282 --field=post_content`) and compare one accordion item byte for byte with the item above. If core's saved markup differs (attributes, wrappers), copy core's version instead, so the block does not show "unexpected content" in the editor.

- [ ] **Step 5: `launch-cta.php`**

Includes the *Myytävät kodit* line at the top, so the page assembly in Task 6 stays only patterns.

```php
<?php
/**
 * Title: Avajaiset: loppukehotus
 * Slug: ketunkoti/launch-cta
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Link for home buyers, then a closing call to action that jumps back to the hero signup form.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--70)">
	<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
	<p class="has-text-align-center"><?php echo esc_html__( 'Etsitkö omaa kotia etkä sijoitusta?', 'ketunkoti' ); ?> <a href="<?php echo esc_url( home_url( '/myytavat-kodit/' ) ); ?>"><?php echo esc_html__( 'Katso myytävät kodit', 'ketunkoti' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"launch-cta","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|80"},"margin":{"top":"var:preset|spacing|70"}}},"backgroundColor":"accent-5","layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group alignfull launch-cta has-accent-5-background-color has-background" style="margin-top:var(--wp--preset--spacing--70);padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"launch-character launch-character--wait"} -->
	<figure class="wp-block-image size-full launch-character launch-character--wait"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/characters/fox-wait.svg' ) ); ?>" alt=""/></figure>
	<!-- /wp:image -->

	<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Vieläkö mietit? Kettu odottaa kärsivällisesti.', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#liity"><?php echo esc_html__( 'Haluan etuajo-oikeuden', 'ketunkoti' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
```

- [ ] **Step 6: Lint the pattern files and confirm registration**

Run:
```bash
for f in launch-hero launch-image launch-reasons launch-faq launch-cta; do
	docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-wordpress-1 php -l /var/www/html/wp-content/themes/ketunkoti/patterns/$f.php
done
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp eval 'foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $p ) { if ( str_starts_with( $p["name"], "ketunkoti/launch" ) ) { echo $p["name"], PHP_EOL; } }'
```
Expected: `No syntax errors detected` five times; the five slugs listed.

- [ ] **Step 7: Hand off** (no commit).

---

### Task 6: Assemble the front page and verify

**Files:**
- Modify: page 7 (*Kaunis on koti*, the static front page) in the local database
- Create: `docs/superpowers/plans/assets/build-front-page.php` (WP-CLI script that expands the launch patterns into page 7; reused on production)

**Interfaces:**
- Consumes: the five pattern slugs and `#liity` from Task 5.
- Produces: the finished local front page.

- [ ] **Step 1: Back up page 7**

```bash
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post get 7 --field=post_content > /private/tmp/claude-501/-Users-samikeijonen-projects-ketunkoti/34c108c6-6a4a-4a05-a632-1e543a04ad8d/scratchpad/page-7-backup.html
wc -c /private/tmp/claude-501/-Users-samikeijonen-projects-ketunkoti/34c108c6-6a4a-4a05-a632-1e543a04ad8d/scratchpad/page-7-backup.html
```
Expected: a non-empty file starting with `<!-- wp:cover`.

- [ ] **Step 2: Expand the launch patterns into page 7**

Page 7 gets the patterns' rendered block markup as regular, unsynced blocks (spec: copy lives in the database and stays editable), built by `docs/superpowers/plans/assets/build-front-page.php`:

```bash
docker exec -i 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp eval-file - < /Users/samikeijonen/projects/ketunkoti/docs/superpowers/plans/assets/build-front-page.php
docker exec 9710676a02bfb9b8e88ee5cba1e1c30f-cli-1 wp post get 7 --field=post_content | grep -c 'wp:pattern'
```
Expected: `Success: Front page 7 updated from 5 launch patterns.`; count `0`.

- [ ] **Step 3: Check the page template**

The front page uses `templates/page.html`. Open `http://localhost:8888/` and confirm the template does not print its own page title (`Kaunis on koti`) above the hero. If it does, tell the user and propose a `front-page.html` template without the title. Do not create it without approval.

- [ ] **Step 4: Layout check at 360, 400, 900 and 1280 px**

At each width, bust the CSS cache, then run:

```js
() => {
	const r = s => document.querySelector(s)?.getBoundingClientRect();
	const peek = r('.launch-character--peek'), label = r('.ketunkoti-signup__label'), btn = r('.ketunkoti-signup__submit');
	const overlaps = (a, b) => a && b && !(a.right <= b.left || a.left >= b.right || a.bottom <= b.top || a.top >= b.bottom);
	return {
		vw: innerWidth,
		hScroll: document.documentElement.scrollWidth > innerWidth,
		h1: document.querySelector('h1')?.textContent,
		forms: document.querySelectorAll('form.ketunkoti-signup').length,
		peekOverLabel: overlaps(peek, label),
		peekOverButton: overlaps(peek, btn),
		characters: [...document.querySelectorAll('.launch-character img')].map(i => i.naturalWidth > 0 && i.alt === ''),
		faqSchema: [...document.querySelectorAll('script[type="application/ld+json"]')].some(s => s.textContent.includes('FAQPage')),
	};
}
```
Expected at every width: `hScroll: false`, `h1: "Parhaat kodit eivät koskaan päädy nettiin"`, `forms: 1`, `peekOverLabel: false`, `peekOverButton: false`, `characters` six `true` values, `faqSchema: true`.

Take a full-page screenshot at 400 and 1280, read them, and check by eye: the fox peeks over the form, the sitting fox's legs hang over the photo edge, cards line up. Fix positioning values in `style.css` (Task 4 section) if not, rebuild and re-run.

- [ ] **Step 5: Keyboard and anchor check**

At 1280: scroll to the bottom, focus the *Haluan etuajo-oikeuden* link in the CTA, press Enter, then press Tab once and run:

```js
() => ({ hash: location.hash, active: document.activeElement.id, top: Math.round(document.querySelector('#liity').getBoundingClientRect().top) })
```
Expected: `hash: "#liity"`, `active: "ketunkoti-signup-email"`, `top` between 0 and about 120 (the scroll padding).

Also Tab through the whole page from the top and confirm every link, the input, the button and each accordion toggle shows a visible focus ring (spot-check screenshots).

- [ ] **Step 6: Reduced motion check**

Use `browser_emulate_media` with `reducedMotion: "reduce"`, reload, and run:

```js
() => [...document.querySelectorAll('.launch-character, .launch-character img')].map(e => getComputedStyle(e).animationName)
```
Expected: every entry is `"none"`. Then emulate `no-preference`, reload, and confirm `.launch-character--peek` reports `ketunkoti-peek-in`.

- [ ] **Step 7: Editor check**

Log in at `http://localhost:8888/wp-admin/` (credentials from the user; the database is a production copy), open page 7 in the editor, and confirm: the patterns expanded into blocks with no "This block contains unexpected or invalid content" warnings; the H1 and card texts are editable; the fox images can be selected and moved; the synced form shows as the *Tilaa Ketun kodit* synced pattern. Do not save unless the user asks.

- [ ] **Step 8: Hand off** (no commit). Show the user the 400 px and 1280 px screenshots.

---

### Task 7: Production rollout checklist (do not run without the user)

**Files:**
- Modify: `docs/superpowers/plans/2026-10-18-avajaiset-etusivu.md` (tick the boxes as the user completes them)

**Interfaces:**
- Consumes: `docs/superpowers/plans/assets/signup-form-block-46.html`, `docs/superpowers/plans/assets/build-front-page.php`.

Theme code ships by the normal deploy. The form and the front page live in the database, so they need separate steps on production. Present this list to the user; run nothing on production without their explicit go-ahead for each write.

- [ ] **Step 1: Confirm block 46 on production (read only)**

```bash
ssh ketunkoti 'cd public_html && wp post get 46 --field=post_type && wp post get 46 --field=post_content | head -3'
```
Expected: `wp_block` and the Buttondown form. If not, find the right ID (`wp post list --post_type=wp_block --fields=ID,post_title`) and update `"ref":46` in `launch-hero.php` before deploying.

- [ ] **Step 2: After the theme deploy, back up and update the two posts (user approval required)**

```bash
ssh ketunkoti 'cd public_html && wp post get 46 --field=post_content > ~/block-46-backup.html && wp post get 7 --field=post_content > ~/page-7-backup.html'
ssh ketunkoti 'cd public_html && wp post update 46 -' < docs/superpowers/plans/assets/signup-form-block-46.html
ssh ketunkoti 'cd public_html && wp eval "wp_get_theme()->delete_pattern_cache();" && wp eval-file -' < docs/superpowers/plans/assets/build-front-page.php
ssh ketunkoti 'cd public_html && wp eval "wp_get_theme()->delete_pattern_cache();" && wp cache flush'
```
WordPress caches the theme's pattern file list when development mode is off, and the theme version (1.0) does not change, so the new `launch-*` patterns are not registered until that cache is cleared. Without it the front page renders empty.
The site runs the Surge page cache; flush it as well (`wp surge flush` if the command exists, otherwise from the admin).

- [ ] **Step 3: Test signup**

The user (or the agent, with the user's permission and address) submits the form once on production. Expected: redirect to *Kiitos uutiskirjeen tilaamisesta*; the subscriber in Buttondown has the `avajaiset` tag; the confirmation email arrives and its link lands on *Kiitos uutiskirjeen vahvistamisesta*.

- [ ] **Step 4: After the launch**

Remove the hidden `tag=avajaiset` input from block 46 (in the editor or from the asset file), otherwise every later signup is tagged as a launch signup.
