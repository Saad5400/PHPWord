# Iter7-B: sandbox header/footer + page-break upgrade

All changes confined to `work/sandbox/index.html`.

## CSS

- **`.page-break` upgraded** from a small dashed line to a prominent band: `background: #eef0f3`, top+bottom 3px `#b0b8c8` borders, negative side margin (`margin: 48px -24px`) so it bleeds to the page edges, 10px letter-spaced label.
- **`.doc-header` band** added — CSS grid `1fr auto`, RTL direction, light grey background (`#f8f8f8`), 2px bottom border, with two cells:
  - `.logo-cell` — dark `#222` background, white text, 12px padding (right side visually in RTL).
  - `.header-lines` — flex column, right-aligned, 12px font (left side visually in RTL).
- **`.doc-footer` band** added — light grey background, 2px top border, muted `#666` 11px text, RTL direction.

## JS walker

- Added `groupHeaderFooter(content)` preprocessor invoked from `renderDocToHtml`. It rewrites the top-level content array (rendering-only, doc.json untouched):
  - **Header detection**: if `content[0]` is a paragraph with `attrs.textAlign === 'left'` containing the literal text `شعار الجهة`, splices the first 4 nodes into a synthetic `{type: '__doc_header__', content: [...]}` wrapper at index 0.
  - **Footer detection**: if the last node is a paragraph containing `رقم العقد` and the node 4 positions before it is a `horizontalRule`, splices the trailing HR + 4 paragraphs into a synthetic `{type: '__doc_footer__', content: [...]}` wrapper.
- Added `__doc_header__` renderer that emits `<div class="doc-header">` with the header-lines column rendered first (so RTL grid puts logo on the right) and the `.logo-cell` containing the first paragraph.
- Added `__doc_footer__` renderer that emits `<div class="doc-footer">` containing the HR + 4 paragraphs unchanged (renders each via the standard NODE_RENDERERS).
- Added `extractText(node)` helper that wraps the existing `flattenText` to return a flat string for header/footer content matching.

## Verification

Sandbox render at http://127.0.0.1:8765/:
- `.doc-header` present, 3 header-lines + logo-cell, render status `ok`.
- `.doc-footer` present, 5 children (HR + 4 paragraphs).
- 3 `.page-break` separators rendered with upgraded styling (computed bg `#eef0f3`, 3px borders).
- No JS errors.
