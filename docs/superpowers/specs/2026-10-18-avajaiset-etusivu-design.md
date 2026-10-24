# Avajaiset: etusivu sähköpostilistan keräämiseen

Date: 2026-10-18
Status: Design approved in conversation. Awaiting spec review.

## Goal

Ketunkoti.fi avajaiset. Get as many people as possible onto the Ketun kodit
email list (Buttondown, list `ketunkoti`).

Success: subscriptions, measured as views of the thank-you page *Kiitos
uutiskirjeen tilaamisesta* (page 174) in Koko Analytics, and the ratio of
front page views to thank-you page views.

## The hook

Etuajo-oikeus: new investment apartments (sijoitusasunnot) are offered to the
list first, and at least initially they are **not published on the website at
all**. The list is the only way to see them. The front page says this plainly.

## Tone of voice

Playful and humorous. Competitors are serious; Ketun koti is not. The fox is a
character with opinions. Copy is short, warm and a little cheeky, never
sarcastic toward the reader.

## Decisions

- **Scope:** a light launch page focused only on signups. Current front page
  sections (sijoitusasunnot pitch, homes grid) are removed from the front page.
  A small link to *Myytävät kodit* stays for buyers.
- **Build approach:** theme patterns assembled onto the front page (page 7), so
  copy stays editable in the editor. No new plugins, no new blocks.
- **Form:** keep the existing synced block (ref 46, Buttondown embed) and
  improve its HTML. It stays in use everywhere it is today.
- **Character:** one fox, inline SVG inside the signup form, based on the logo
  mark (`assets/images/brand/ketunkoti-mark.svg`) and animated in CSS. A
  separate "Koti" house character is out of scope.

## Page structure

Order on the front page. All copy below is a draft, to be refined in the editor.

### 1. Hero: centred text and form

Light `base` background, no cover image.

- No eyebrow.
- H1: *Uudet sijoitusasuntomme myydään **ensin** Ketun kodit -listalle* (italic
  emphasis on *ensin*)
- Lead: *Etsitkö uudiskohteita positiivisella kassavirralla, noin 5 % tuotolla
  ja hinnoilla alkaen 26 000 € Lahden keskustan kupeesta? Laitappa Ketun kodit
  tilaukseen, niin ilmoitellaan, kun kohteet tulevat myyntiin.*
- Revised 2026-10-18: the first copy (*Parhaat kodit eivät koskaan päädy
  nettiin*) framed the list by what visitors cannot get and sounded secretive.
  The headline now states the benefit and the lead gives investors the facts;
  the playful tone comes from the fox and the rest of the copy.
- Signup form (synced block 46), centred. The hero group has the anchor
  `liity`, the target of the closing call to action.

### 2. Image

Wide interior photo from the existing media library (attachment 12, chosen by the user in the editor).

### 2b. Millaisia kohteita kettu tuo? (added 2026-10-18)

Pattern `ketunkoti/launch-properties`. Two columns (text and attachment 152):
heading *Millaisia kohteita kettu tuo?*, *Etsitkö sijoitusasunnoksi
uudiskohdetta, jossa on* and a checkmark list (positiivinen kassavirta, noin
5 % tuotto, kilpailukykyinen taloyhtiölaina, myyntihinnat alkaen 26 000 €,
loistava sijainti Lahden keskustan kupeessa), then a *Tilaa Ketun kodit* button
to `#liity`. These are the original front page's arguments; the hero lead
summarises them. The reason cards below cover the list's benefits only.

### 3. Miksi listalle?

Heading plus three cards.

| Card title | Text |
|---|---|
| Ensimmäisenä apajilla | Uudet kohteet tulevat listalle ennen kuin kukaan muu kuulee niistä. |
| Ei nettiin, ei hälinää | Ei hintakilpailuja eikä satojen katsojien näyttöjä, vain sinä ja hyvä kohde. |
| Harvoin mutta hyvää | Postia tulee vain, kun on oikeasti asiaa. Kettu ei jaarittele. |

Cards reuse the existing `home-card` look: light surface (`accent-5`), thin
border, rounded corners.

### 4. Usein kysytyt

Core accordion with the existing accordion styles and FAQ schema
(`ketunkoti-features`).

- *Maksaako listalle liittyminen?* Ei maksa mitään. Kettu ei edes ota tippiä.
- *Sitoudunko ostamaan jotain?* Et. Katsot, kiinnostaako, ja jos ei, kettu ei
  loukkaannu (paljon).
- *Missä kohteet sijaitsevat?* Lahden keskustan kupeessa, hyvien kulkuyhteyksien
  päässä.
- *Kuinka usein postia tulee?* Vain kun uusi kohde on tulossa myyntiin.
- *Miten perun tilauksen?* Jokaisen viestin lopussa on linkki. Yksi klikkaus,
  eikä kysellä perään.

### 5. Myytävät kodit link

One line: *Etsitkö omaa kotia etkä sijoitusta?* linking to `/myytavat-kodit/`.

### 6. Closing call to action

- Heading: *Vieläkö mietit? Kettu odottaa kärsivällisesti.*
- Button *Tilaa Ketun kodit* linking to `#liity` (the hero form). The form
  appears only once on the page. The existing smooth scrolling and
  `scroll-padding-top` in `style.css` handle the jump.

## Character: the peeking fox

Revised 2026-10-18: the first version placed six fox SVG files on the page as
image blocks (hero, photo edge, cards, closing CTA). They did not work
visually, and an `<img>` cannot be animated part by part, so they were removed
together with their CSS and files.

There is now one fox, drawn as inline SVG inside the signup form (block 46),
peeking over the top right edge of the field/button row:

- Same geometric style as the logo mark: pointed ears with dark insides, light
  muzzle, closed happy eyes, house-shaped cutout on the forehead, two paws
  gripping the edge. Palette only: `#B58A63`, `#A27A55`, `#5C4632`, `#F5EFE2`,
  `#2E2419`.
- Ears, head, eyes (closed and open variants) are separate groups so they can
  be animated; the head is clipped at the edge so it can duck behind it.
- Decorative: `aria-hidden="true"`, `focusable="false"`, `pointer-events:
  none`.
- Above the button when field and button share a row; above the field when
  they are stacked on narrow screens.

Behaviour (CSS only, nothing loops):

1. Page load: the head pops up from behind the edge once.
2. Email field focused, or button hovered or focused: ears perk up and eyes
   open (listening).
3. Valid address typed (`:has(input:valid)`): the head hops once and the eyes
   return to happy arcs, also while the button is hovered.
4. Button hovered or focused: the head also rises a little.

With `prefers-reduced-motion: reduce` the state changes (eyes, ears, raised
head) still apply, without animation. Because the fox lives in the synced
form, it appears wherever the form is used (pages 7, 45 and 186).

## Signup form

Synced block 46 (`wp:html`), improved markup:

- Class `ketunkoti-signup` on the `<form>`.
- Visible label *Sähköpostiosoite*.
- `<input type="email" name="email" required autocomplete="email"
  aria-describedby="...">` pointing to the microcopy.
- Hidden `tag` field with value `avajaiset`, so launch signups are
  distinguishable in Buttondown. **Verify against Buttondown's embed form
  documentation before relying on it**; drop the field if unsupported.
- Submit button: *Tilaa Ketun kodit* (same wording as the header button).
- Microcopy: *Ei roskapostia. Kettu lupaa. Voit perua milloin vain.* plus a
  link to *Tietosuojaseloste* (`/tietosuojaselosta/`).

Behaviour is unchanged: the form posts to Buttondown, which redirects to the
existing thank-you and confirmation pages (174, 179). Invalid input is caught
by native browser validation.

Because the block is synced, the new button text and microcopy also appear on
*Tilaa Ketun kodit* (page 45) and anywhere else the block is used. Intended.

The form appears at most once per page (the front page closing CTA links back
up instead of repeating it), so fixed `id`s for `for`/`id` and
`aria-describedby` are safe. Do not place the synced block twice on one page.

## Styles

All in `themes/ketunkoti/style.css` (rebuilt into `style.min.css`), using theme
CSS custom properties.

- Form: input and button on one row from a comfortable width up, max width
  about 34rem; stacked and full width on narrow screens.
- Placement is controlled by the surrounding group: a centred variant for the
  hero, left-aligned elsewhere (e.g. page 45).
- Input: clear border, visible focus ring, WCAG AA contrast.
- The peeking fox's position, states and motion live in the signup form
  section of `style.css` (`.ketunkoti-signup__fox`, `.fox__*`).
- Editor parity: `style.css` is already loaded in the editor via
  `add_editor_style()`, so the same rules apply there.

## Patterns

New files in `themes/ketunkoti/patterns/`, category `ketunkoti-launch` or the
existing equivalent:

- `launch-hero.php`
- `launch-image.php`
- `launch-reasons.php`
- `launch-faq.php`
- `launch-cta.php`

The *Myytävät kodit* line is a plain paragraph on the page. Page 7 content is
replaced with these patterns (inserted as regular, unsynced blocks) after the
current content is backed up.

## Out of scope

- A custom signup block or AJAX submission.
- A "Koti" house character.
- Changes to other pages beyond what the synced form changes.
- Event tracking beyond thank-you page views.

## Testing

- Browser at 360, 400, 900 and 1280 px: layout, fox placement, no horizontal scroll.
- Keyboard: focus order and visible focus states.
- `prefers-reduced-motion: reduce`: no animation; fox states still change.
- Editor: copy editable.
- One real test signup to Buttondown with the user's address (redirect to the
  thank-you page, tag present), done by the user or with their permission.

## Implementation order

1. Patterns.
4. Form markup and styles.
5. Front page content (back up current page 7 content first).
6. Testing.
