# Iter6-B: sandbox CSS/JS changes

All changes confined to `work/sandbox/index.html`.

- **pageBreak node renderer**: added `pageBreak` case in `NODE_RENDERERS` that emits `<div class="page-break"><span>— فاصل الصفحة —</span></div>`. Matching `.page-break` CSS (dashed top border, muted, centered, 11px).
- **TOC layout**: `.toc-leader` now `flex: 1` (was `flex: 1 1 auto`); `.toc-page` now `flex-shrink: 0; min-width: 2em; text-align: left` (was `flex: 0 0 auto; min-width: 1.5em; text-align: right`). Page number now pinned at far-left edge regardless of entry length in the RTL flex row.
- **TOC dot string**: `.toc-leader::before` content extended to ~210 chars so it always fills available leader space.
- **Body line-height**: added `#page p[data-type="paragraph"] { line-height: 1.7; }`. Paragraph renderer now emits `data-type="paragraph"` so the rule matches; inline `line-height` from JSON attrs still overrides it via specificity (inline style wins).
- **Underline mark**: verified — `MARK_RENDERERS.underline` already maps to `<u>` (line ~340, unchanged).
