#!/usr/bin/env python3
"""
Author the SUNU architecture diagrams as hand-built SVG.

One source, two destinations: the SVG is inlined into the web dossier (where
`currentColor` makes it theme-aware) and rasterised to PNG for the Word
deliverables. Coordinates are computed rather than typed so the grid stays true.
"""

import os

HERE = os.path.dirname(os.path.abspath(__file__))

# Meaning-bearing hues, chosen to read on both a white page and a dark screen.
TEAL = "#0E8B80"      # reused as-is
AMBER = "#B8801C"     # extended
RUST = "#BE5548"      # new build

FT, FL, FS = 13, 11, 9.5      # title / label / small type sizes


def esc(t):
    return (t.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;"))


def head(w, h, label):
    return [
        '<svg viewBox="0 0 %d %d" role="img" aria-label="%s" '
        'xmlns="http://www.w3.org/2000/svg" style="max-width:100%%;height:auto">' % (w, h, esc(label)),
        '<defs>',
        '<marker id="ah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" '
        'orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="currentColor"/></marker>',
        '<marker id="ahT" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" '
        'orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="%s"/></marker>' % TEAL,
        '<marker id="ahs" viewBox="0 0 10 10" refX="1" refY="5" markerWidth="7" markerHeight="7" '
        'orient="auto-start-reverse"><path d="M10,0 L0,5 L10,10 z" fill="currentColor"/></marker>',
        '</defs>',
    ]


def box(x, y, w, h, title, sub=None, color=None, dash=False, fill=0.05, tsize=FT, radius=6):
    stroke = color or "currentColor"
    d = ' stroke-dasharray="5 4"' if dash else ""
    out = ['<rect x="%g" y="%g" width="%g" height="%g" rx="%g" fill="%s" fill-opacity="%g" '
           'stroke="%s" stroke-opacity="0.85" stroke-width="1.4"%s/>'
           % (x, y, w, h, radius, stroke, fill, stroke, d)]
    cx = x + w / 2
    if sub:
        out.append(text(cx, y + h / 2 - 3, title, tsize, weight=600, anchor="middle"))
        out.append(text(cx, y + h / 2 + 13, sub, FS, anchor="middle", opacity=0.72))
    else:
        out.append(text(cx, y + h / 2 + 4, title, tsize, weight=600, anchor="middle"))
    return out


def container(x, y, w, h, title, color=None, fill=0.05, note=None):
    """A frame whose title sits at the top edge, leaving the body free for content."""
    stroke = color or "currentColor"
    out = ['<rect x="%g" y="%g" width="%g" height="%g" rx="8" fill="%s" fill-opacity="%g" '
           'stroke="%s" stroke-opacity="0.85" stroke-width="1.6"/>'
           % (x, y, w, h, stroke, fill, stroke)]
    out.append(text(x + w / 2, y + 22, title, FT, weight=700, anchor="middle"))
    if note:
        out.append(text(x + w / 2, y + 38, note, FS, anchor="middle", opacity=0.72))
    return out


def text(x, y, s, size=FL, weight=400, anchor="start", opacity=1.0, color=None, mono=False):
    fam = ("ui-monospace,'DejaVu Sans Mono',Menlo,Consolas,monospace" if mono
           else "'Helvetica Neue',Helvetica,Arial,sans-serif")
    fill = color or "currentColor"
    return ('<text x="%g" y="%g" font-size="%g" font-weight="%d" text-anchor="%s" '
            'font-family="%s" fill="%s" fill-opacity="%g">%s</text>'
            % (x, y, size, weight, anchor, fam, fill, opacity, esc(s)))


def lines(x, y, rows, size=FL, anchor="start", opacity=0.8, gap=13, color=None, weight=400):
    return [text(x, y + i * gap, r, size, weight, anchor, opacity, color) for i, r in enumerate(rows)]


def arrow(x1, y1, x2, y2, dash=False, both=False, color=None, width=1.4, op=0.85):
    stroke = color or "currentColor"
    mk = "ahT" if color == TEAL else "ah"
    d = ' stroke-dasharray="6 5"' if dash else ""
    start = ' marker-start="url(#ahs)"' if both else ""
    return ['<line x1="%g" y1="%g" x2="%g" y2="%g" stroke="%s" stroke-opacity="%g" stroke-width="%g" '
            'marker-end="url(#%s)"%s%s/>' % (x1, y1, x2, y2, stroke, op, width, mk, start, d)]


def elbow(pts, dash=False, color=None, op=0.85):
    stroke = color or "currentColor"
    d = ' stroke-dasharray="6 5"' if dash else ""
    p = " ".join("%g,%g" % xy for xy in pts)
    return ['<polyline points="%s" fill="none" stroke="%s" stroke-opacity="%g" stroke-width="1.4" '
            'marker-end="url(#ah)"%s/>' % (p, stroke, op, d)]


def swatch(x, y, color, label):
    return ['<rect x="%g" y="%g" width="12" height="12" rx="3" fill="%s" fill-opacity="0.18" '
            'stroke="%s" stroke-width="1.3"/>' % (x, y, color, color),
            text(x + 18, y + 10, label, FS, opacity=0.8)]


def band(x, y, w, h, label, color):
    return ['<rect x="%g" y="%g" width="%g" height="%g" rx="8" fill="%s" fill-opacity="0.05" '
            'stroke="%s" stroke-opacity="0.45" stroke-width="1.2" stroke-dasharray="4 4"/>' % (x, y, w, h, color, color),
            text(x + 10, y + 16, label, FS, weight=600, color=color, opacity=0.95)]


# ---------------------------------------------------------------- 1. system context

def d1_context():
    W, H = 820, 560
    s = head(W, H, "System context: the POS terminal and back office carry field collection; "
                   "SUNULIFE, Mobile Money, SMS and the card station are non-blocking dependencies.")

    s += box(16, 118, 122, 60, "Insured", "prospect / policyholder")
    s += box(16, 212, 122, 60, "Collection agent", "assigned to one terminal", tsize=12)
    s += container(228, 92, 164, 214, "POS TERMINAL", color=TEAL, fill=0.07, note="15 units")
    s += lines(244, 158, ["• subscription capture",
                          "• premium collection",
                          "• card read / QR scan",
                          "• receipt printing",
                          "• camera · GPS",
                          "• offline queue"], FS, opacity=0.8, gap=15)
    s += container(470, 72, 334, 196, "BACK-OFFICE PLATFORM", color=TEAL, fill=0.07)
    s += lines(492, 140, ["• insured & policy repository",
                          "• collections, receipts, exports",
                          "• collector caps & pay-ins"], FS, opacity=0.8, gap=15)
    s += lines(660, 140, ["• profiles & permissions",
                          "• card issuance",
                          "• integration hub"], FS, opacity=0.8, gap=15)
    s += [text(637, 250, "system of record for COLLECTION", FS, weight=600, anchor="middle",
               color=TEAL, opacity=0.95)]

    s += box(228, 372, 164, 66, "SUNU back office", "users (5 profiles)")

    # external systems, evenly spaced beneath the platform
    ext = [("SUNULIFE", "core business", ["customer &", "policy sync"], 0),
           ("Mobile Money", "Orange · Moov", ["collect", "premium"], 1),
           ("SMS gateway", None, ["receipt", "confirmation"], 0),
           ("Card station", "personalisation", ["card data"], 1)]
    x0, bw, gap = 470, 76, 10
    for i, (name, sub, lbl, stagger) in enumerate(ext):
        bx = x0 + i * (bw + gap)
        s += box(bx, 400, bw, 72, name, sub, tsize=10.5)
        cx = bx + bw / 2
        s += arrow(cx, 268, cx, 400, dash=True)
        ly = 306 + stagger * 30
        s += lines(cx + 7, ly, lbl, FS, opacity=0.75, gap=12)

    # required paths
    s += arrow(138, 148, 228, 148)
    s += [text(183, 140, "pays premium", FS, anchor="middle", opacity=0.8)]
    s += arrow(138, 242, 228, 242)
    s += [text(183, 234, "operates", FS, anchor="middle", opacity=0.8)]
    s += arrow(392, 170, 470, 170, both=True, color=TEAL)
    s += [text(431, 162, "HTTPS · JSON", FS, anchor="middle", opacity=0.85, color=TEAL),
          text(431, 190, "queued offline", FS, anchor="middle", opacity=0.75)]
    s += elbow([(392, 400), (431, 400), (431, 230), (470, 230)])
    s += [text(437, 392, "browser · HTTPS", FS, opacity=0.75)]

    # legend
    s += ['<line x1="16" y1="500" x2="52" y2="500" stroke="currentColor" stroke-width="1.6" '
          'marker-end="url(#ah)"/>',
          text(60, 504, "required for field collection", FS, opacity=0.85),
          '<line x1="270" y1="500" x2="306" y2="500" stroke="currentColor" stroke-width="1.6" '
          'stroke-dasharray="6 5" marker-end="url(#ah)"/>',
          text(314, 504, "non-blocking — if unavailable that channel stops, "
                         "cash collection continues", FS, opacity=0.85)]
    s.append("</svg>")
    return "\n".join(s), W, H


# ------------------------------------------------------- 2. component reuse map

def d2_components():
    pos = [("Login & session", "R"), ("Home & navigation", "E"),
           ("Proposal & CNIB capture", "E"), ("Geolocation", "E"),
           ("Card read / write (NFC)", "E"), ("Receipt printing", "E"),
           ("Offline queue & sync", "E"), ("Agent day reports", "E"),
           ("QR scan & verify", "N"), ("Policy selection", "N"),
           ("Mobile Money capture", "N"), ("Cap & pay-in screen", "N")]
    bo = [("Authentication", "R"), ("Business units", "R"),
          ("Roles & permissions", "R"), ("Terminal registry", "R"),
          ("Transaction spine", "R"), ("Audit trail", "R"),
          ("API request logging", "R"), ("SMS notifications", "R"),
          ("Bilingual FR / EN", "R"), ("Collection agents", "E"),
          ("Insured repository", "E"), ("Proposal (subscription)", "E"),
          ("Premium collection", "E"), ("Card management", "E"),
          ("Reporting & Excel export", "E"), ("Dashboards", "E"),
          ("Product & policy model", "N"), ("Cap & pay-in reconciliation", "N"),
          ("Virtual card (QR)", "N"), ("Mobile Money gateway", "N"),
          ("SUNULIFE integration hub", "N")]

    colors = {"R": TEAL, "E": AMBER, "N": RUST}
    cols, cw, ch, cgap = 2, 176, 30, 8
    def height(items):
        return ((len(items) + 1) // 2) * (ch + cgap) + 44

    h_pos, h_bo = height(pos), height(bo)
    panel_h = max(h_pos, h_bo)
    W = 820
    H = 96 + panel_h + 96

    s = head(W, H, "Component map: most of the platform is reused or extended; new build is "
                   "concentrated in the insurance model, caps and pay-ins, QR, Mobile Money "
                   "and the SUNULIFE hub.")

    def panel(px, title, items, count_note, ph):
        out = box(px, 62, 380, ph, "", color=None, fill=0.03, radius=8)
        out = [out[0]]  # keep the frame, drop the centred title
        out += [text(px + 14, 84, title, FT, weight=700)]
        out += [text(px + 366, 84, count_note, FS, anchor="end", opacity=0.65)]
        for i, (name, kind) in enumerate(items):
            cx = px + 12 + (i % cols) * (cw + 8)
            cy = 98 + (i // cols) * (ch + cgap)
            c = colors[kind]
            out += ['<rect x="%g" y="%g" width="%g" height="%g" rx="5" fill="%s" fill-opacity="0.12" '
                    'stroke="%s" stroke-opacity="0.75" stroke-width="1.2"/>' % (cx, cy, cw, ch, c, c)]
            out += ['<rect x="%g" y="%g" width="4" height="%g" rx="2" fill="%s"/>' % (cx, cy, ch, c)]
            out += [text(cx + 12, cy + 19, name, 10, opacity=0.92)]
        return out

    r = sum(1 for _, k in pos if k == "R"), sum(1 for _, k in bo if k == "R")
    e = sum(1 for _, k in pos if k == "E"), sum(1 for _, k in bo if k == "E")
    n = sum(1 for _, k in pos if k == "N"), sum(1 for _, k in bo if k == "N")

    s += panel(16, "POS APPLICATION  (Android)", pos, "%d components" % len(pos), h_pos)
    s += panel(424, "BACK-OFFICE PLATFORM  (web + API)", bo, "%d components" % len(bo), h_bo)

    # Use the space the shorter panel leaves free to state the claim the colours make.
    cy = 62 + h_pos + 30
    s += [text(30, cy, "Why the September date is credible", FL, weight=700, opacity=0.95),
          text(30, cy + 20, "Nothing in the teal or amber columns is being invented.", FS, opacity=0.82),
          text(30, cy + 34, "The platform already runs a voluntary-insurance", FS, opacity=0.82),
          text(30, cy + 48, "enrolment and periodic-contribution module whose data", FS, opacity=0.82),
          text(30, cy + 62, "model matches proposal \u2192 policy \u2192 premium.", FS, opacity=0.82),
          text(30, cy + 86, "The pilot is a domain adaptation of proven code,", FS, weight=600, opacity=0.9),
          text(30, cy + 100, "not new construction.", FS, weight=600, opacity=0.9)]

    ly = 62 + panel_h + 26
    s += swatch(16, ly - 10, TEAL, "reused as-is — %d" % (r[0] + r[1]))
    s += swatch(200, ly - 10, AMBER, "extended — %d" % (e[0] + e[1]))
    s += swatch(360, ly - 10, RUST, "new build — %d" % (n[0] + n[1]))
    s += [text(16, ly + 26,
               "New build is %d of %d components, and it is concentrated in five places: the insurance "
               "product/policy model, collector" % (n[0] + n[1], len(pos) + len(bo)), FS, opacity=0.85),
          text(16, ly + 40,
               "caps and pay-ins, the QR virtual card, Mobile Money and the SUNULIFE hub. "
               "Everything else is adaptation of code already in production.", FS, opacity=0.85)]
    s.append("</svg>")
    return "\n".join(s), W, H


# --------------------------------------------------- 3. SUNULIFE integration

def d3_sunulife():
    W, H = 820, 590
    s = head(W, H, "SUNULIFE integration: the pilot runs on a file extract and Excel export, "
                   "while the API phases in afterwards without changing the rest of the design.")

    s += box(300, 16, 220, 62, "SUNULIFE", "master of customers and contracts")

    # Phase 0 — the path that carries the September pilot
    s += band(16, 108, 232, 300, "PHASE 0 — pilot, September", TEAL)
    s += box(32, 140, 200, 56, "CSV / XLSX extract", "one-off + periodic", color=TEAL)
    s += arrow(300, 62, 132, 62, color=TEAL)
    s += [text(216, 54, "manual extract", FS, anchor="middle", opacity=0.8, color=TEAL)]
    s += arrow(132, 62, 132, 140, color=TEAL)
    s += box(32, 244, 200, 56, "Bulk import", "existing customers visible", color=TEAL)
    s += arrow(132, 196, 132, 244, color=TEAL)
    s += box(32, 330, 200, 56, "Excel export", "back office keys into SUNULIFE", color=TEAL)
    s += [text(132, 400, "no API required", FS, anchor="middle", weight=600, color=TEAL, opacity=0.95)]

    # Phase 1 / 2 — the API path
    s += band(286, 108, 518, 300, "PHASE 1 (read) · PHASE 2 (write)", AMBER)
    s += box(306, 140, 200, 60, "Scheduled sync workers",
             "customer + policy delta, nightly", color=AMBER)
    s += box(susx := 584, 140, 200, 60, "Outbound event queue",
             "collection posted · proposal", color=AMBER)

    s += arrow(406, 78, 406, 140, dash=True, color=AMBER)
    s += [text(398, 98, "PULL", FS, anchor="end", weight=600, opacity=0.9, color=AMBER)]
    s += arrow(684, 140, 684, 78, dash=True, color=AMBER)
    s += [text(692, 98, "PUSH", FS, weight=600, opacity=0.9, color=AMBER)]

    s += box(306, 236, 478, 66, "SUNULIFE ADAPTER",
             "auth strategy · field mapping · idempotency keys · circuit breaker · full request/response log")
    s += arrow(406, 200, 406, 236)
    s += arrow(684, 236, 684, 200)

    s += box(306, 336, 478, 52, "Local mirror", "client + policy, mirrored — never authored here")
    s += arrow(545, 302, 545, 336, both=True)

    s += ['<rect x="306" y="418" width="478" height="1" fill="currentColor" fill-opacity="0.2"/>']
    s += [text(306, 440, "Retry on failure: 1m · 5m · 15m · 1h · 6h · 24h, then a manual review queue.",
               FS, opacity=0.8),
          text(306, 456, "A rejected push never reverses money already collected — it raises an exception.",
               FS, opacity=0.8)]

    s += box(16, 430, 232, 52, "Collection continues", "SUNULIFE downtime is not a stoppage", color=TEAL)

    s += [text(16, 520, "The pilot date does not depend on the SUNULIFE API.", FL, weight=700, opacity=0.95),
          text(16, 538, "Phase 0 meets SUNU's requirement that field agents see customers who already "
                        "exist, using a periodic extract. If the API turns out", FS, opacity=0.82),
          text(16, 552, "not to exist, the same design runs on a scheduled SFTP file exchange — only the "
                        "adapter changes, nothing else in the architecture.", FS, opacity=0.82)]
    s.append("</svg>")
    return "\n".join(s), W, H


# ------------------------------------------------------ 4. deployment topology

def d4_deployment():
    W, H = 820, 590
    s = head(W, H, "Deployment topology: the hosted environment behind one TLS and rate-limiting "
                   "boundary, with the database reachable only from inside it.")

    # trust boundary
    s += ['<rect x="188" y="60" width="616" height="360" rx="10" fill="currentColor" '
          'fill-opacity="0.03" stroke="currentColor" stroke-opacity="0.45" stroke-width="1.4" '
          'stroke-dasharray="7 5"/>',
          text(200, 80, "HOSTED ENVIRONMENT — trust boundary", FS, weight=700, opacity=0.7)]

    # outside
    s += box(16, 96, 150, 62, "POS fleet", "15 terminals · 4G")
    s += box(16, 190, 150, 62, "SUNU office", "back-office users")
    s += box(16, 300, 150, 62, "MoMo callbacks", "signed · IP allow-listed")

    s += container(210, 96, 152, 266, "TLS TERMINATION", color=TEAL, fill=0.07)
    s += lines(226, 152, ["HTTPS only",
                          "TLS 1.2+ · HSTS",
                          "certificate pinning",
                          "WAF",
                          "rate limiting",
                          "per key · agent · IP"], FS, opacity=0.8, gap=16)

    s += arrow(166, 127, 210, 127)
    s += arrow(166, 221, 210, 221)
    s += arrow(166, 331, 210, 331)

    # app tier
    s += box(400, 96, 124, 74, "Web / API", "Nginx + PHP-FPM")
    s += box(400, 186, 124, 74, "Queue workers", "sync · SMS · exports")
    s += box(400, 276, 124, 74, "Scheduler", "delta sync · recon · backup")
    s += arrow(362, 133, 400, 133)
    s += arrow(362, 223, 400, 223)
    s += arrow(362, 313, 400, 313)

    # data tier
    s += box(576, 108, 148, 74, "MySQL 8", "no public route", color=TEAL)
    s += box(576, 214, 148, 74, "Object store", "proposal & ID images", color=TEAL)

    # One orthogonal data bus rather than three crossing diagonals.
    for y in (133, 223, 313):
        s += ['<line x1="524" y1="%g" x2="550" y2="%g" stroke="currentColor" stroke-opacity="0.7" '
              'stroke-width="1.4"/>' % (y, y)]
    s += ['<line x1="550" y1="133" x2="550" y2="313" stroke="currentColor" stroke-opacity="0.7" '
          'stroke-width="1.4"/>']
    s += arrow(550, 145, 576, 145, both=True, op=0.7)
    s += arrow(550, 251, 576, 251, both=True, op=0.7)
    s += [text(556, 202, "reads / writes", FS, opacity=0.7)]

    s += box(576, 320, 148, 56, "Backup", "daily + binlog · PITR", color=TEAL)
    s += arrow(650, 288, 650, 320)
    s += [text(658, 308, "RPO 15 min", FS, opacity=0.75),
          text(650, 394, "encrypted at rest", FS, anchor="middle", weight=600,
               opacity=0.9, color=TEAL)]

    # egress
    s += box(210, 442, 594, 56, "Outbound, allow-listed: SUNULIFE · Mobile Money providers · SMS gateway")
    s += arrow(462, 350, 462, 442, dash=True)
    s += [text(470, 400, "queued, retried, logged", FS, opacity=0.75)]

    s += [text(16, 442, "Inbound", FS, weight=700, opacity=0.8),
          text(16, 458, "allow-listed:", FS, opacity=0.75),
          text(16, 474, "POS fleet,", FS, opacity=0.75),
          text(16, 488, "office network,", FS, opacity=0.75),
          text(16, 502, "MoMo callbacks", FS, opacity=0.75)]

    s += [text(210, 542, "Pilot sizing: one application server (4 vCPU / 8 GB) and one database server "
                         "(4 vCPU / 16 GB). The tier scales", FS, opacity=0.82),
          text(210, 558, "by adding application and worker nodes behind the same boundary — "
                         "no architectural change.", FS, opacity=0.82)]
    s.append("</svg>")
    return "\n".join(s), W, H


DIAGRAMS = [
    ("01-system-context", d1_context, "System context — who talks to what, and what may fail"),
    ("02-component-map", d2_components, "Component map — reused, extended and newly built"),
    ("03-sunulife-integration", d3_sunulife, "SUNULIFE integration and its phasing"),
    ("04-deployment-topology", d4_deployment, "Deployment topology and trust boundary"),
]


def main():
    for name, fn, caption in DIAGRAMS:
        svg, w, h = fn()
        path = os.path.join(HERE, name + ".svg")
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(svg)
        print("%-28s %4dx%-4d  %s" % (name + ".svg", w, h, caption))


if __name__ == "__main__":
    main()
