# Ketunkoti theme

## Spacing

Spacing sizes in `theme.json` (`settings.spacing.spacingSizes`) are fluid values generated with the [Utopia space calculator](https://utopia.fyi/space/calculator/?c=360,8,1.125,1420,16,1.333,5,2,&s=0.75|0.5|0.25,1.5|2|3|4|8|12,l-2xl|xl-l&g=s,l,xl,12).

Calculator settings:

- **Min viewport:** 360px, base size 8px
- **Max viewport:** 1420px, base size 16px
- **Custom pair:** L–2XL (used for the global side padding)

If you need to change the spacing scale, update the values in the calculator and copy the generated `clamp()` values into `theme.json`.
