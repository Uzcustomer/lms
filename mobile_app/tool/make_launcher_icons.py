"""Launcher icons from the designer's glass sheet (two icons on a black board).

Boxes are read off the sheet by hand - the icons sit in a wide glow that
defeats automatic edge detection. Change them here if the sheet changes.

  iOS     - rounded square; its interior is re-rendered as a full square
            (iOS masks the corners itself, so a bare square must not carry
            the designer's own rounded edge or the board behind it)
  Android - circle; its blue becomes the adaptive background and the white
            emblem the adaptive foreground, so every launcher shape works
"""
import os
import sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

APP = r"C:\Users\or7\Desktop\LMS\mobile_app"
OUT = os.path.join(APP, "assets", "launcher")
STORE = os.path.join(APP, "store")
SRC = sys.argv[1] if len(sys.argv) > 1 else os.path.join(OUT, "source.png")
os.makedirs(OUT, exist_ok=True)
os.makedirs(STORE, exist_ok=True)
S = 1024

# Hand-measured on the 1536x1024 sheet.
IOS_BOX = (105, 200, 730, 840)
AND_BOX = (845, 192, 1467, 816)

sheet = Image.open(SRC).convert("RGB")
print("sheet", sheet.size)


def square(box):
    x0, y0, x1, y1 = box
    s = max(x1 - x0, y1 - y0)
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    return (round(cx - s / 2), round(cy - s / 2), round(cx + s / 2), round(cy + s / 2))


ios_sq, and_sq = square(IOS_BOX), square(AND_BOX)
ios_crop = sheet.crop(ios_sq).resize((S, S), Image.LANCZOS)
and_crop = sheet.crop(and_sq).resize((S, S), Image.LANCZOS)
print("ios", ios_sq, "android", and_sq)

# ── iOS: grow the interior over the rounded corners ────────────────
# Sample a ring just inside the shape and mirror it outward, so the dark
# board never shows in a corner once iOS applies its own mask.
ic = np.array(ios_crop).astype(np.float32)
yy, xx = np.mgrid[0:S, 0:S]
r_out = S * 0.50          # the designer's square nearly fills the crop
inside = (np.abs(xx - S / 2) < r_out * 0.92) & (np.abs(yy - S / 2) < r_out * 0.92)
corner = ~inside

cur = ic.copy()
m_in = inside.copy()
for _ in range(18):
    blurred = np.array(Image.fromarray(cur.astype(np.uint8)).filter(ImageFilter.GaussianBlur(14))).astype(np.float32)
    grow = np.array(Image.fromarray((m_in * 255).astype(np.uint8)).filter(ImageFilter.MaxFilter(9))) > 127
    newly = grow & ~m_in
    cur[newly] = blurred[newly]
    m_in = grow
    if m_in.all():
        break
ios_img = Image.fromarray(np.clip(np.where(inside[..., None], ic, cur), 0, 255).astype(np.uint8), "RGB")
ios_img.save(os.path.join(OUT, "ios.png"))
ios_img.save(os.path.join(STORE, "icon-1024.png"))
ios_img.resize((512, 512), Image.LANCZOS).save(os.path.join(STORE, "icon-512.png"))

# ── Android background: the circle's blue, spread to the corners ───
ac = np.array(and_crop).astype(np.float32)
cx = cy = S / 2
r = S * 0.49
rr = np.sqrt((xx - cx) ** 2 + (yy - cy) ** 2)
core = rr < r * 0.88        # inside the circle, clear of its rim highlight

px, py = xx[core] / S, yy[core] / S


def feats(x, y):
    return np.stack([np.ones_like(x), x, y, x * x, x * y, y * y,
                     x ** 3, y ** 3, x * x * y, x * y * y], axis=-1)


A = feats(px, py)
coef = [np.linalg.lstsq(A, ac[:, :, c][core], rcond=None)[0] for c in range(3)]
F = feats(xx.reshape(-1) / S, yy.reshape(-1) / S)
fit = np.stack([F @ coef[c] for c in range(3)], axis=-1).reshape(S, S, 3)
# keep the real artwork inside the circle, the fitted blue outside it
bg_img = Image.fromarray(np.clip(fit, 0, 255).astype(np.uint8), "RGB").filter(ImageFilter.GaussianBlur(3))
bg_img.save(os.path.join(OUT, "android-bg.png"))

# ── Android foreground: the whole disc, scaled into the safe zone ──
# Lifting just the emblem off the blue left a ghost and mis-sized it, so
# the foreground is the circle artwork itself; the background is the
# fitted blue behind it. Every launcher mask then works.
disc = Image.new("RGBA", (S, S), (0, 0, 0, 0))
cmask = Image.new("L", (S, S), 0)
ImageDraw.Draw(cmask).ellipse((round(cx - r), round(cy - r), round(cx + r), round(cy + r)), fill=255)
cmask = cmask.filter(ImageFilter.GaussianBlur(1.5))
disc.paste(and_crop, (0, 0), cmask)

INSET = 0.68           # flutter_launcher_icons wraps the foreground in <inset 16%>
SAFE = 66 / 108        # adaptive-icon safe-zone diameter on the full canvas
target = round(S * SAFE / INSET)
fgc = Image.new("RGBA", (S, S), (0, 0, 0, 0))
fgc.alpha_composite(disc.resize((target, target), Image.LANCZOS), ((S - target) // 2,) * 2)
fgc.save(os.path.join(OUT, "android-fg.png"))

# Monochrome (Android 13 themed icons): the white emblem only.
mono = np.array(fgc)
alpha = mono[:, :, 3].astype(np.float32) / 255
lum = mono[:, :, :3].astype(np.float32).sum(axis=2) / 3
mono[:, :, :3] = 255
mono[:, :, 3] = ((lum > 150) * alpha * 255).astype(np.uint8)
Image.fromarray(mono, "RGBA").save(os.path.join(OUT, "android-mono.png"))

# Legacy (Android 7 and older): the designer's circle as drawn.
legacy = Image.new("RGBA", (S, S), (0, 0, 0, 0))
legacy.paste(and_crop, (0, 0), cmask)
legacy.save(os.path.join(OUT, "android-legacy.png"))

# ── preview: what launchers actually show ──────────────────────────
def masked(bgi, fgi, draw_mask, size=256):
    big = round(size * 108 / 72)
    layer = bgi.resize((big, big), Image.LANCZOS).convert("RGBA")
    small = round(big * INSET)
    layer.alpha_composite(fgi.resize((small, small), Image.LANCZOS), ((big - small) // 2,) * 2)
    off = (big - size) // 2
    layer = layer.crop((off, off, off + size, off + size))
    o = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    mk = Image.new("L", (size, size), 0)
    draw_mask(ImageDraw.Draw(mk), size)
    o.paste(layer, (0, 0), mk)
    return o


tiles = [
    masked(bg_img, fgc, lambda d, s: d.ellipse((0, 0, s - 1, s - 1), fill=255)),
    masked(bg_img, fgc, lambda d, s: d.rounded_rectangle((0, 0, s - 1, s - 1), radius=s * 0.42, fill=255)),
    masked(bg_img, fgc, lambda d, s: d.rounded_rectangle((0, 0, s - 1, s - 1), radius=s * 0.18, fill=255)),
]
iosp = Image.new("RGBA", (256, 256), (0, 0, 0, 0))
mk = Image.new("L", (256, 256), 0)
ImageDraw.Draw(mk).rounded_rectangle((0, 0, 255, 255), radius=256 * 0.2237, fill=255)
iosp.paste(ios_img.resize((256, 256), Image.LANCZOS), (0, 0), mk)
tiles.append(iosp)
tiles.append(Image.open(os.path.join(OUT, "android-legacy.png")).resize((256, 256), Image.LANCZOS))

board = Image.new("RGB", (5 * 296 + 40, 336), (245, 246, 250))
for i, tl in enumerate(tiles):
    board.paste(tl, (40 + i * 296, 40), tl)
board.save(os.path.join(STORE, "icon-preview.png"))
print("preview ->", os.path.join(STORE, "icon-preview.png"))
