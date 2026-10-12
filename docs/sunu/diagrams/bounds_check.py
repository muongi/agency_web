#!/usr/bin/env python3
"""Flag SVG text that would render outside the viewBox — the usual French-overflow failure."""
import glob, os, re, sys

# Rough advance widths for the Helvetica-ish stack, per font-size unit.
def width(s, size, weight):
    w = 0.545 if weight >= 600 else 0.508
    narrow = sum(1 for c in s if c in "iljt.,':;| ")
    wide = sum(1 for c in s if c in "MWmw@")
    return (len(s) - narrow - wide) * size * w + narrow * size * 0.28 + wide * size * 0.82

bad = 0
for f in sorted(glob.glob(os.path.join(os.path.dirname(os.path.abspath(__file__)), '*.svg'))):
    vb = re.search(r'viewBox="0 0 (\d+) (\d+)"', open(f, encoding='utf-8').read())
    W = int(vb.group(1))
    for m in re.finditer(
            r'<text x="([\d.]+)" y="([\d.]+)" font-size="([\d.]+)" font-weight="(\d+)" '
            r'text-anchor="(\w+)"[^>]*>(.*?)</text>',
            open(f, encoding='utf-8').read(), re.S):
        x, y, size, weight, anchor, body = (float(m.group(1)), float(m.group(2)), float(m.group(3)),
                                            int(m.group(4)), m.group(5), m.group(6))
        body = body.replace('&amp;', '&')
        w = width(body, size, weight)
        left = x - w / 2 if anchor == 'middle' else (x - w if anchor == 'end' else x)
        right = left + w
        if left < -2 or right > W + 2:
            bad += 1
            print('%-34s y=%-5g  overflow (%.0f..%.0f of %d): %r'
                  % (os.path.basename(f), y, left, right, W, body[:58]))
print('\n%s' % ('clean — no text leaves the canvas' if not bad else '%d overflowing labels' % bad))
sys.exit(1 if bad else 0)
