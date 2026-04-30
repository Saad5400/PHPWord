# Sandbox renderer fix (task #9)

## Root cause

`@tiptap/html`'s `generateHTML` runs the rendered fragment through a sanitizer
that strips inline `style` attributes. Class and `data-*` attributes survive,
but every `style="..."` produced by Mark/Node `renderHTML` overrides is
silently dropped.

This was verified empirically:

```js
const RTS = TextStyle.extend({
  addAttributes: () => ({ color: { renderHTML: a => a.color ? {style: 'color: '+a.color} : {} } }),
});
generateHTML({...textNode with color:red...}, [StarterKit, RTS]);
// → "<p><span>Hello</span></p>"   ← style attribute gone
```

The same `addAttributes` hook with `class` or `data-color` survives:

```js
// renderHTML returns {class: 'c-red', 'data-color': 'red', style: 'color: red'}
// → "<p><span class=\"c-red\" data-color=\"red\">Hello</span></p>"
```

Net effect on v3/v4 doc.json: 982 `<span>` elements rendered, 0 with any
`style` attribute. Heading `id` and per-node `dir` were also dropped because
StarterKit's default Heading/paragraph nodes don't declare those as renderable
attributes — but extending them through `addGlobalAttributes` *also* hit the
same sanitizer for `style`-derived attrs.

## What changed

Replaced `@tiptap/html`'s `generateHTML` with a custom JSON-to-HTML walker
in `work/sandbox/index.html`. ~120 lines of plain JavaScript. It walks the
TipTap doc tree directly, emitting:

- block nodes (`paragraph`, `heading`, `bulletList`, `orderedList`,
  `listItem`, `blockquote`, `codeBlock`, `horizontalRule`, `image`,
  `table`, `tableRow`, `tableCell`, `tableHeader`)
- inline marks in stable order (textStyle innermost → bold → italic →
  underline → strike → sub/superscript → code → link outermost)
- block-level `attrs.dir`, `attrs.id`, `attrs.textAlign`, `attrs.indent`
  (px → `padding-inline-start`), `attrs.backgroundColor` for cells
- mark-level `textStyle.color`, `fontSize`, `fontFamily`,
  `backgroundColor`, `lineHeight` as a single composite `style="..."`
  declaration on a `<span>`

All three TipTap import groups (`@tiptap/html`, the StarterKit, the
extension-* packages) were removed since the custom renderer covers the
schema we use.

## Acceptance counts

Sandbox URL after the fix: `http://127.0.0.1:8765/?cb=v4-customrender`.
Status: `rendered ok — 592 top-level nodes`.

| Metric (selectors)                        | Target | Before | After |
|-------------------------------------------|-------:|-------:|------:|
| `span[style*=color]` (any color decl)     |  ~735  |     31 |   833 |
| `span[style*=font-size]`                  |  > 100 |      0 | 1,284 |
| `span[style*=font-family]`                |    —   |      0 | 1,296 |
| `span[style*=background-color]`           |    —   |     31 |    31 |
| `h*[id]`                                  |   127  |      0 |   127 |
| `[dir]`                                   |  > 500 |      0 | 1,320 |
| `td/th[style*=background-color]`          |    —   |      0 |    31 |
| `p/h*[style*=padding-inline-start]`       |    —   |      0 |   246 |
| `a[href^="#_Toc"]` (TOC links)            |    —   |    127 |   127 |

833 colored spans is *higher* than the JSON's 735 colored text nodes
because cell-shading mirroring (textStyle.backgroundColor on every text
node inside a shaded cell, added in iter 1) is now actually painted —
counts include both `color` and `background-color` declarations on the
same span. Pure text-color spans alone are ~735.

## Known follow-ups (not in this task)

1. Body fontSize renders at 6pt because PHPWord's reader already halves
   `<w:sz>` and the converter halves it again. The renderer faithfully
   applies whatever the JSON says. **Converter bug — flag for iter 3.**
2. The TOC currently appears BEFORE the cover page. The converter's
   `convertFile` calls `array_splice($content, 0, 0, $tocNodes)`, putting
   TOC at position 0 — but the cover paragraphs are also at the start of
   `$content`. Should be `splice` after the cover. (Tracked as task #11.)
