# Fox Ventures theme

Child theme of [Ketun koti](../ketunkoti/README.md). Templates, patterns, spacing and styles come from the parent. This theme only changes the color palette and fonts in `theme.json`.

## Colors

The palette keeps the parent's slugs, so parent styles, patterns and section styles pick up the new colors automatically.

| Slug | Name | Color | Used for |
|---|---|---|---|
| `base` | Black | `#111111` | Page background |
| `contrast` | White | `#FFFFFF` | Body text, button text |
| `accent-1` | Red | `#E4231B` | Brand red, button background |
| `accent-2` | Dark red | `#9E1810` | Button hover |
| `accent-3` | Light grey | `#D4D4D4` | Light surfaces |
| `accent-4` | Grey | `#A3A3A3` | Meta text (dates, comment authors), focus outline |
| `accent-5` | Charcoal | `#1E1E1E` | Dark surfaces (inputs, code, cards) |
| `accent-6` | Line | 20% of text color | Borders and separators |

Red on black is 4.1:1, which passes WCAG AA only for large text (24px, or 18.66px bold) and UI elements. Don't use red for body text or links. White text on red is 4.61:1 and passes.

## Fonts

- **Tirra** (Google Fonts, SIL Open Font License, see `assets/fonts/tirra/OFL.txt`) for body text and headings. Weights 400–700, latin and latin-ext subsets, served locally. Tirra has no italics, so browsers fake the italic style.
- **Fira Code** for code. The file is loaded from the parent theme.
