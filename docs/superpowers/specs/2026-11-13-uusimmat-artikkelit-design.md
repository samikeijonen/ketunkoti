# Latest articles on the front page (uusimmat artikkelit)

Date: 2026-11-13
Status: Approved 2026-11-13.

A "Uusimmat artikkelit" section on the front page (page 7) listing the four newest
posts with only the publish date and title. Small change.

## Why

Articles are now being published (six so far). The front page should show that the
site is alive and lead readers to them. Cards are reserved for homes, so articles get a
lighter, text-only treatment. "Aluksi" (to begin with) only title and date; category
or excerpt may come later.

## Goals

- Four newest posts, newest first: date and title per row.
- Look: option **B** from the brainstorm mockups ("hakemisto"). The date sits in its
  own left column, the title is in Cormorant Garamond, and a small arrow sits on the
  right. The whole row is clickable.
- Section heading "Uusimmat artikkelit" on the left, "Kaikki artikkelit" link on the
  right of the same row, pointing to `/lopinat/`.
- Placed right after "Kuka on Ketun kodin takana?" and before "Hyvä tietää ennen
  tilaamista" (UKK).
- Editable in the editor like the rest of the front page: heading, link and post count.

## Non-goals

- No images, excerpts, categories or authors.
- Löpinät (page 722) is **not** set as the posts page. Sami will build that page,
  possibly with other blocks next to a Query Loop. This change only links to it.
- No production deploy or production content change. That happens separately.

## Approaches

1. **Theme pattern + core Query Loop (chosen).** Matches the existing `homes-grid`
   pattern and needs no build step. Once inserted, the section is plain, editable blocks.
2. A custom dynamic block in `ketunkoti-blocks`. Gives full control over the markup and
   hides itself when empty. Rejected: more code, less editable, and copy changes need
   code changes.
3. Core Latest Posts block + block style. Fastest, but the markup is fixed: the date
   comes after the title and an empty list shows "Ei artikkeleita". Rejected.

## Design

- **Pattern** `themes/ketunkoti/patterns/latest-posts.php` (slug
  `ketunkoti/latest-posts`, category `query, posts`). One `core/query` block is the
  section wrapper, with class `latest-posts`, `align: wide` (no custom widths, as Sami
  decided), `perPage: 4`, `postType: post`, newest first and `inherit: false`. Inside
  it:
  - a header row (flex, space-between): `h2` "Uusimmat artikkelit" and a paragraph
    link "Kaikki artikkelit" to `/lopinat/`;
  - `core/post-template` (list layout), where each item has `core/post-date` (format
    `j.n.Y`) and `core/post-title` (`h3`, `isLink`, Cormorant Garamond, large size).
- **Styles** go in the theme `style.css` (rebuilt into `style.min.css`, which is also
  the editor style, so the editor matches). Use theme CSS custom properties:
  - Each row is a grid: a date column, the title, and the arrow (`::after`, accent-2).
    The rows sit between hairlines; the top line is in the contrast colour.
  - A stretched title link (`a::after { position: absolute; inset: 0 }`) makes the
    whole row clickable while keeping a single link per row. Hover shows the title in
    accent-2 and nudges the arrow.
  - Keyboard focus is visible on the row (`:focus-within` / `:focus-visible`). The arrow
    movement is disabled under `prefers-reduced-motion`.
  - On narrow screens the date moves above the title.
- **Empty state:** no special handling. Sami decided it isn't needed, since posts
  already exist. No `core/query-no-results` block either.
- **Front page content:** insert the pattern's markup into page 7 after the "Kuka on
  Ketun kodin takana?" section, with WP-CLI. First check the latest revisions for
  editor edits. The "Kuka on" section exists only in the database, not in any
  pattern, so `build-front-page.php` is not updated (my call).

## Left to the builder

- Exact spacing values and the date column width (about 120px in the mockup), using
  theme spacing presets where they fit.
- Whether the arrow is decorative CSS or a text glyph. It must be hidden from screen
  readers either way.
