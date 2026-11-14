# Latest Articles on the Front Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Uusimmat artikkelit" section on the front page (page 7) that lists the four newest posts with only the date and title, in the "hakemisto" (B) style.

**Architecture:** A theme block pattern (`ketunkoti/latest-posts`) built from a core Query Loop provides the section. Its look lives in the theme `style.css`, which is also the editor style. The pattern's markup is inserted once into page 7 with WP-CLI, after which it is plain, editable blocks.

**Tech Stack:** WordPress 6.9 block theme (`themes/ketunkoti`), core blocks (`query`, `post-template`, `post-date`, `post-title`), PostCSS build (`npm run build:theme`), wp-env (`npx wp-env run cli …`).

**Spec:** `docs/superpowers/specs/2026-11-13-uusimmat-artikkelit-design.md`

## Global Constraints

- The four newest posts, newest first: `perPage: 4`, `postType: post`, `orderBy: date`, `order: desc`, `inherit: false`.
- Date format `j.n.Y` (e.g. 13.11.2026). The title is `h3`, linked, Cormorant Garamond, `large` size.
- Section heading "Uusimmat artikkelit" (`h2`) on the left. The link "Kaikki artikkelit" sits on the same row on the right and points to `/lopinat/`.
- Placed after the "Kuka on Ketun kodin takana?" group and before the "Hyvä tietää ennen tilaamista" group.
- Width: `align: wide` on the `core/query` wrapper. No custom widths (no `contentSize`, no `max-width` in CSS).
- No empty-state handling and no `core/query-no-results`.
- Löpinät (page 722) is not touched, and the posts page setting is not changed.
- No images, excerpts, categories or authors.
- Use theme CSS custom properties (`--wp--preset--color--*`, `--wp--preset--spacing--*`, `--wp--preset--font-size--*`).
- Run `nvm use` before npm commands. The user commits; the plan has no commit steps.

## Review Focus

1. **The editor must stay usable.** The stretched link overlay (`::after`) must not cover the editor canvas, or clicking a row cannot select blocks. Scope the overlay to the front end: `body:not(.editor-styles-wrapper)`.
2. **Keyboard:** each row has exactly one tab stop (the title link). Focus must be visible around the whole row, not just the clipped text.
3. **Long titles wrap to two lines.** The date and arrow should stay aligned to the first line (`align-items: baseline` or `start`), and on mobile the date sits above the title.
4. **Screen readers must not announce the arrow.** Use a CSS pseudo-element; with a glyph, use `aria-hidden`. The date is read before the title.
5. **Reduced motion:** the arrow's hover nudge is off under `prefers-reduced-motion: reduce`.

---

### Task 1: Pattern and styles

**Files:**
- Create: `themes/ketunkoti/patterns/latest-posts.php`
- Modify: `themes/ketunkoti/style.css`: add a "Latest posts" section after the "Home cards" section
- Build: `themes/ketunkoti/style.min.css` (generated)

**Interfaces:**
- Produces: pattern slug `ketunkoti/latest-posts`. The wrapper is `core/query` with `className: latest-posts`. Inner class names for CSS: `latest-posts__header`, `latest-posts__more`, `latest-posts__list` (post-template), `latest-posts__date`, `latest-posts__title`.

- [ ] **Step 1: Check that the pattern is missing (failing check)**

Run: `npx wp-env run cli wp eval 'echo WP_Block_Patterns_Registry::get_instance()->is_registered("ketunkoti/latest-posts") ? "yes" : "no";'`
Expected: `no`

- [ ] **Step 2: Create the pattern**

Use the header format from `patterns/homes-grid.php`: Title "Uusimmat artikkelit", Slug `ketunkoti/latest-posts`, Categories `query, posts`, Block Types `core/query`. The markup is one `core/query`, with the query args from Global Constraints, `className: latest-posts`, `align: wide`, layout `default` and margin-top `var:preset|spacing|80` like its neighbours. It contains:
  - a `core/group` `latest-posts__header` (flex, `justifyContent: space-between`, `verticalAlignment: bottom`, wrap) with `core/heading` level 2 "Uusimmat artikkelit" and `core/paragraph` `latest-posts__more` containing `<a href="/lopinat/">Kaikki artikkelit</a>`;
  - `core/post-template` `latest-posts__list` (default list layout, `blockGap: 0`), containing a `core/group` row with `core/post-date` (`format: "j.n.Y"`, `className: latest-posts__date`, `fontSize: small`, `textColor: accent-4`) and `core/post-title` (`level: 3`, `isLink: true`, `className: latest-posts__title`, `fontFamily: cormorant-garamond`, `fontSize: large`).

Write the copy with `esc_html_e( '…', 'ketunkoti' )` and the link with `esc_url( home_url( '/lopinat/' ) )`, so the URL works locally and in production.

- [ ] **Step 3: Verify that it registers and renders four rows**

Run: `npx wp-env run cli wp eval '$p = WP_Block_Patterns_Registry::get_instance()->get_registered("ketunkoti/latest-posts"); $h = do_blocks($p["content"]); echo substr_count($h, "latest-posts__title"), " ", preg_match("/\d{1,2}\.\d{1,2}\.\d{4}/", $h), " ", substr_count($h, "/lopinat/");'`
Expected: `4 1 1`

- [ ] **Step 4: Add the styles to `style.css`**

Add a commented section "Latest posts", matching the comment style of the existing sections:
  - the list has a top border in contrast and each row a bottom hairline (`accent-6`), with vertical padding of about `spacing|40`;
  - each row is a grid `7.5rem 1fr auto`, with the gap from a spacing preset and `align-items: baseline`. The arrow is `::after` on the row (`content: "→"`, colour `accent-2`). Reset the margins of the date and title inside the row;
  - the stretched link is scoped to `body:not(.editor-styles-wrapper)`: the row gets `position: relative`, and `.latest-posts__title a::after` gets `position: absolute; inset: 0`;
  - hover and focus: the title turns `accent-2` and the arrow moves `translateX(4px)`. `.latest-posts__list li:has(a:focus-visible)` gets an outline that follows the existing focus style (see `/* Focus styles */`). Remove the outline from the link itself only inside that rule;
  - under `prefers-reduced-motion: reduce`, no transform transition;
  - below about 600px the grid becomes `1fr auto`, and the date spans the full row above the title.

- [ ] **Step 5: Build**

Run: `nvm use && npm run build:theme`
Expected: exits 0, and `style.min.css` contains `latest-posts__list`. Check with `grep -c latest-posts__list themes/ketunkoti/style.min.css` (≥1).

---

### Task 2: Front page insertion and visual check

**Files:**
- Content only: page 7 (`page_on_front`), via WP-CLI. No repo file changes.

**Interfaces:**
- Consumes: pattern `ketunkoti/latest-posts` from Task 1.

- [ ] **Step 1: Check page 7 for editor edits (separate step, read before writing)**

Run: `npx wp-env run cli wp post list --post_type=revision --post_parent=7 --fields=ID,post_author,post_modified --orderby=ID --order=DESC --posts_per_page=3`
Read the output. The insert in Step 2 is string-based and keeps all other content. Still, if the newest revision is from the user and recent, tell them before writing.

- [ ] **Step 2: Insert the pattern before the UKK group**

Use a temporary `wp eval-file` script placed inside a mounted plugin folder (e.g. `plugins/ketunkoti-features/zz-insert-latest-posts-tmp.php`), as done for post 696. It:
  - reads page 7's `post_content`;
  - uses as anchor the UKK group's opening comment, `<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"constrained","contentSize":"44rem"}} -->`;
  - aborts unless the anchor occurs exactly once and `latest-posts` does not occur yet;
  - inserts the registered pattern's `content`, plus a blank line, before the anchor;
  - saves with `wp_update_post` (`wp_slash`) and prints `updated`. Support `DRY=1`, which prints the counts only.

Run DRY first and expect `anchor: 1, existing: 0`. Then run it for real, expect `updated`, and delete the temp file. `git status` must show no leftovers.

- [ ] **Step 3: Verify on the front end**

Run: `curl -s http://localhost:8888/ | grep -o 'latest-posts__title' | wc -l`
Expected: `4`
Run: `curl -s http://localhost:8888/ | grep -o 'Kuka on Ketun kodin takana\|Uusimmat artikkelit\|Hyvä tietää ennen tilaamista'`
Expected, in this order: Kuka on…, Uusimmat artikkelit, Hyvä tietää…

- [ ] **Step 4: Visual and keyboard check (Playwright MCP)**

At 1280px and 390px wide, screenshot the section. Then go through Review Focus 2–4: press Tab through the rows (one stop per row, visible outline around the row) and confirm a two-line title still aligns. In the editor (`/wp-admin/post.php?post=7&action=edit`), click a row and confirm that it selects the block and does not navigate (Review Focus 1). Report the screenshots to the user.
