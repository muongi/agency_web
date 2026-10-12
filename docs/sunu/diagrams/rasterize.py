#!/usr/bin/env python3
"""Rasterise the diagram SVGs to PNG via headless Chrome, for the Word deliverables."""
import glob, os, re, subprocess, sys, tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
SCALE = 3            # 3x so the PNG stays crisp at print resolution
INK = "#16191A"      # light-theme foreground: currentColor resolves to this


def render(svg_path, scale=SCALE):
    svg = open(svg_path, encoding="utf-8").read()
    m = re.search(r'viewBox="0 0 (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)"', svg)
    w, h = int(float(m.group(1))), int(float(m.group(2)))
    html = (
        '<!doctype html><meta charset="utf-8">'
        '<style>html,body{margin:0;padding:0;background:#fff;color:%s}'
        'svg{display:block;width:%dpx;height:%dpx}</style>%s'
        % (INK, w * scale, h * scale, svg)
    )
    tmp = tempfile.NamedTemporaryFile("w", suffix=".html", delete=False, encoding="utf-8")
    tmp.write(html); tmp.close()
    out = svg_path[:-4] + ".png"
    cmd = ["google-chrome", "--headless=new", "--disable-gpu", "--no-sandbox",
           "--hide-scrollbars", "--force-device-scale-factor=1",
           "--virtual-time-budget=4000",
           "--screenshot=" + out,
           "--window-size=%d,%d" % (w * scale, h * scale),
           "file://" + tmp.name]
    r = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    os.unlink(tmp.name)
    if not os.path.exists(out):
        print("FAILED", svg_path, r.stderr[-600:], file=sys.stderr)
        return None
    return out, w, h


if __name__ == "__main__":
    for svg in sorted(glob.glob(os.path.join(HERE, "*.svg"))):
        res = render(svg)
        if res:
            out, w, h = res
            print("%-32s %8.1f KB  (%dx%d @%dx)" % (
                os.path.basename(out), os.path.getsize(out) / 1024, w, h, SCALE))
