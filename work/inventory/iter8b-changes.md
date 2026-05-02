# Iter8-B — Sandbox CSS/JS changes

Scope: `work/sandbox/index.html` only. No PHP touched.

## 1. Logo direction fix
The Arabic placeholder text "شعار الجهة" inside the dark `.logo-cell` was
rendering LTR because no direction was scoped to the cell. Added `direction: rtl`
and `text-align: right` to `.doc-header .logo-cell`, and propagated
`text-align: right` to its child `<p>`.

## 2. Lettered RTL list rendering
The orderedList around "وثائق العقد" (JSON line ~9008) carries `dir="rtl"` on
each `listItem`. The previous CSS only set `padding-inline-start: 2em`, which
in RTL flow pushes markers to the *trailing* edge — but on this list the
markers were sitting against the wrong edge with no hanging indent.

Added a scoped rule that targets RTL lists (`[dir="rtl"]` directly on the
list, or — via `:has()` — on any child `<li>`). For those, swap the padding
to `padding-inline-end: 2.5em` and force `direction: rtl; text-align: right`
on the items. Non-RTL lists are unchanged.

## 3. Section-divider spacing
After a `.page-break` div immediately preceding an `h1`/`h2`, add
`margin-top: 64px; padding-top: 24px` so a major section feels like it
restarts on a new page. Implemented as the adjacent-sibling combinator
`.page-break + h1, .page-break + h2`.

## 4. marginRight / marginLeft pass-through (prep for Iter8-A)
The walker's `styleFromBlockAttrs()` previously handled `textAlign`, `indent`,
`backgroundColor`, and (heading-only) `color`. It silently dropped any
`marginRight`/`marginLeft` that Iter8-A would emit on paragraph attrs.

Added explicit emission as `margin-inline-end` / `margin-inline-start` so
numeric values are suffixed `px` and string values pass through. Logical
properties keep RTL behavior consistent with the rest of the renderer.

## Files changed
- `work/sandbox/index.html` — CSS additions + walker `styleFromBlockAttrs()` extension.
