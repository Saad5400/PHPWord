# Iter11 — Arabic-Indic ordered-list counters

Scope: `work/sandbox/index.html` only. One CSS rule.

## Change

Added a single declaration to the base `#page ol` rule so all ordered lists
default to Arabic-Indic digit counters (١. ٢. ٣.) instead of Latin numerals
(1. 2. 3.):

```css
#page ol { list-style-type: arabic-indic; }
```

Placed immediately after the existing `#page ul, #page ol { padding-inline-start: 2em; margin: 0.4em 0; }`
shared rule so it applies before more specific rules (RTL flip, marker
suppression) are evaluated.

## Why this is enough

- **Numeric lists** — every ordered list without a baked marker now renders
  with Arabic-Indic digits because the CSS counter is the only marker source.
- **Lettered lists (page 10)** — these already have the Iter9-B
  `data-marker="none"` attribute on their `<ol>`, and the
  `#page ol[data-marker="none"] { list-style: none; ... }` rule (defined
  later in the stylesheet, equal-or-higher specificity, later cascade) wins.
  The baked Arabic-letter prefix in each `<li>`'s text (ا. ب. ج.) is the
  visible marker.

## What did NOT change

- No PHP, no JSON.
- No renderer JS (the `data-marker="none"` plumbing from Iter9-B is reused).
- No other CSS rules.

## Files touched

- `work/sandbox/index.html` (one line added inside the existing stylesheet).
