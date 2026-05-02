# Iter9-B — Sandbox marker suppression + hanging indent

Scope: `work/sandbox/index.html` only. No PHP touched.

## 1. `markerStyle: "none"` → suppress browser counter
When Iter9-A's PHP emits `attrs.markerStyle = 'none'` on an `orderedList`
(because the Arabic letter prefix "ا.", "ب.", "ج." is already baked into
each `listItem`'s text), the walker now emits `data-marker="none"` on the
`<ol>`. CSS suppresses both the default counter and the inline padding:

```css
#page ol[data-marker="none"] {
  list-style: none;
  padding-inline-end: 0;
  padding-inline-start: 0;
  margin: 0;
}
#page ol[data-marker="none"] li { list-style: none; }
```

Walker change (`orderedList` renderer):

```js
orderedList: (n, ctx) => {
  const extra = {};
  if (n.attrs && n.attrs.markerStyle === 'none') extra['data-marker'] = 'none';
  return `<ol${attrString(ctx._blockAttrs(n, extra))}>${ctx._renderChildren(n.content)}</ol>`;
},
```

Without this we'd get doubled prefixes like `1. ا. text`.

## 2. Paragraph `marginRight` / `marginLeft` — already wired
`_styleFromBlockAttrs()` emits `margin-inline-end` / `margin-inline-start`
for any node carrying those attrs. The paragraph renderer (`(n, ctx) =>
<p${attrString(ctx._blockAttrs(n, { 'data-type': 'paragraph' }))}>...`)
calls `_blockAttrs` → `_styleFromBlockAttrs`, so paragraph indentation from
Iter8-B is preserved. No change needed.

## 3. Hanging indent on listItems
`marginLeft` flows through generically, but `text-indent` (negative value
needed to pull the first line back to the marker column) was not in the
generic style builder. Added a targeted path in the `listItem` renderer:

```js
listItem: (n, ctx) => {
  const attrs = ctx._blockAttrs(n);
  const a = n.attrs || {};
  if (a.textIndent != null && a.textIndent !== '') {
    const v = typeof a.textIndent === 'number' ? `${a.textIndent}px` : String(a.textIndent);
    const prev = attrs.style ? attrs.style.replace(/;\s*$/, '') : '';
    attrs.style = (prev ? prev + '; ' : '') + `text-indent: ${v}`;
  }
  return `<li${attrString(attrs)}>${ctx._renderChildren(n.content)}</li>`;
},
```

`marginLeft` continues to come from `_styleFromBlockAttrs`. With both set
(e.g. `marginLeft: 36, textIndent: -36`), the marker glyph hangs left of
the wrapped text — the standard Word-style "letter . body" layout.

## Files changed
- `work/sandbox/index.html` — CSS rule for `[data-marker="none"]`, walker
  changes for `orderedList` (emit `data-marker`) and `listItem` (emit
  `text-indent`).
