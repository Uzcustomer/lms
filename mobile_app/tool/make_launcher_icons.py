"""Build launcher icons for Android (adaptive + legacy), iOS and the store
from the designer's two-up PNG (circle | rounded square), then run
`dart run flutter_launcher_icons` to fan them out.

    python tool/make_launcher_icons.py            # uses assets/launcher/source.png
    python tool/make_launcher_icons.py path.png   # a new design

Needs: pip install pillow numpy
"""
import os
import sys
import numpy as np
from PIL import Image, ImageDraw

APP = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(APP, "assets", "launcher")   # not in pubspec assets -> not bundled
STORE = os.path.join(APP, "store")
SRC = sys.argv[1] if len(sys.argv) > 1 else os.path.join(OUT, "source.png")
PREVIEW = os.path.join(STORE, "icon-preview.png")
os.makedirs(OUT, exist_ok=True)
os.makedirs(STORE, exist_ok=True)

im = Image.open(SRC).convert("RGBA")
a = np.array(im).astype(np.float32)
alpha = a[:, :, 3]


def bbox(mask):
    ys, xs = np.where(mask)
    return xs.min(), ys.min(), xs.max() + 1, ys.max() + 1


# ── split the two icons on the transparent gap ──────────────────────
colmax = alpha.max(axis=0)
opaque_cols = np.where(colmax > 10)[0]
gap = np.where(colmax <= 10)[0]
gap = gap[(gap > opaque_cols.min()) & (gap < opaque_cols.max())]
split = int((gap.min() + gap.max()) // 2)

circle = im.crop(bbox(alpha[:, :split] > 10))
sq_box = bbox(alpha[:, split:] > 10)
square = im.crop((sq_box[0] + split, sq_box[1], sq_box[2] + split, sq_box[3]))
print("circle", circle.size, "square", square.size)

# ── emblem: everything that is not the blue background ─────────────
sq = np.array(square).astype(np.float32)
R = sq[:, :, 0]
t = np.clip((R - 80.0) / 70.0, 0.0, 1.0)          # bg R≤65 → 0, pages R≈182 → 1
t *= (sq[:, :, 3] / 255.0)                          # respect real transparency
fg = sq.copy()
fg[:, :, 3] = t * 255.0
fg_img = Image.fromarray(fg.astype(np.uint8), "RGBA")
eb = bbox(t > 0.5)
emblem = fg_img.crop(eb)
print("emblem bbox in square:", eb, "emblem size", emblem.size)

# ── background: fit the blue gradient from the icon's outer ring ──
W, H = square.size
yy, xx = np.mgrid[0:H, 0:W]
ring = 0.13
is_ring = (xx < W * ring) | (xx > W * (1 - ring)) | (yy < H * ring) | (yy > H * (1 - ring))
is_bg = (t < 0.02) & (sq[:, :, 3] > 250) & is_ring
xs = xx[is_bg] / W
ys = yy[is_bg] / H


def feats(x, y):
    return np.stack([np.ones_like(x), x, y, x * x, x * y, y * y, x ** 3, y ** 3, x * x * y, x * y * y], axis=-1)


A = feats(xs, ys)
coef = [np.linalg.lstsq(A, sq[:, :, c][is_bg], rcond=None)[0] for c in range(3)]


def gradient(size):
    yy, xx = np.mgrid[0:size, 0:size]
    F = feats(xx.reshape(-1) / size, yy.reshape(-1) / size)
    rgb = np.stack([F @ coef[c] for c in range(3)], axis=-1).reshape(size, size, 3)
    return Image.fromarray(np.clip(rgb, 0, 255).astype(np.uint8), "RGB")


# ── compositions ─────────────────────────────────────────────────
def fit(img, longest):
    w, h = img.size
    s = longest / max(w, h)
    return img.resize((max(1, round(w * s)), max(1, round(h * s))), Image.LANCZOS)


def place(canvas, sprite, cx, cy):
    w, h = sprite.size
    canvas.alpha_composite(sprite, (round(cx - w / 2), round(cy - h / 2)))


# how the designer placed the emblem inside the square (size and centre)
em_ratio = max(emblem.size) / max(W, H)
em_cx = ((eb[0] + eb[2]) / 2) / W
em_cy = ((eb[1] + eb[3]) / 2) / H
print(f"emblem/icon = {em_ratio:.3f}, centre = ({em_cx:.3f}, {em_cy:.3f})")

S = 1024
# iOS / store: full-bleed gradient, emblem exactly as designed, no alpha
ios = gradient(S).convert("RGBA")
place(ios, fit(emblem, em_ratio * S), em_cx * S, em_cy * S)
ios_rgb = ios.convert("RGB")
ios_rgb.save(os.path.join(OUT, "ios.png"))
ios_rgb.save(os.path.join(STORE, "icon-1024.png"))
ios_rgb.resize((512, 512), Image.LANCZOS).save(os.path.join(STORE, "icon-512.png"))

# Android adaptive: 108dp canvas, launcher shows the middle 72dp. The
# designer's emblem fills em_ratio of the visible icon, so on the full
# canvas it must end up em_ratio * 72/108. flutter_launcher_icons wraps
# the foreground in <inset 16%> (scale 0.68), so draw it 1/0.68 larger
# than that to land on the intended size.
INSET = 0.68
gradient(S).save(os.path.join(OUT, "android-bg.png"))
fgc = Image.new("RGBA", (S, S), (0, 0, 0, 0))
place(fgc, fit(emblem, em_ratio * (72 / 108) / INSET * S),
      S / 2, S / 2 + (em_cy - 0.5) * (72 / 108) / INSET * S)
fgc.save(os.path.join(OUT, "android-fg.png"))
mono = np.array(fgc)
mono[:, :, :3] = 255
Image.fromarray(mono, "RGBA").save(os.path.join(OUT, "android-mono.png"))

# Android legacy (< 8.0): the designer's circle as is
fit(circle, S).save(os.path.join(OUT, "android-legacy.png"))

# ── preview: what launchers will actually show ───────────────────
def masked(bg, fg, mask_draw, size=256):
    vis = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    # visible 72dp window out of the 108dp canvas
    big = round(size * 108 / 72)
    layer = bg.resize((big, big), Image.LANCZOS).convert("RGBA")
    # the generator's <inset 16%>, exactly as the launcher will see it
    small = round(big * INSET)
    layer.alpha_composite(fg.resize((small, small), Image.LANCZOS), ((big - small) // 2, (big - small) // 2))
    off = (big - size) // 2
    layer = layer.crop((off, off, off + size, off + size))
    m = Image.new("L", (size, size), 0)
    mask_draw(ImageDraw.Draw(m), size)
    vis.paste(layer, (0, 0), m)
    return vis


bg_img = Image.open(os.path.join(OUT, "android-bg.png"))
fg_img2 = Image.open(os.path.join(OUT, "android-fg.png"))
tiles = [
    masked(bg_img, fg_img2, lambda d, s: d.ellipse((0, 0, s - 1, s - 1), fill=255)),
    masked(bg_img, fg_img2, lambda d, s: d.rounded_rectangle((0, 0, s - 1, s - 1), radius=s * 0.42, fill=255)),
    masked(bg_img, fg_img2, lambda d, s: d.rounded_rectangle((0, 0, s - 1, s - 1), radius=s * 0.18, fill=255)),
]
ios_prev = Image.new("RGBA", (256, 256), (0, 0, 0, 0))
m = Image.new("L", (256, 256), 0)
ImageDraw.Draw(m).rounded_rectangle((0, 0, 255, 255), radius=256 * 0.2237, fill=255)
ios_prev.paste(ios.resize((256, 256), Image.LANCZOS), (0, 0), m)
tiles.append(ios_prev)
tiles.append(Image.open(os.path.join(OUT, "android-legacy.png")).resize((256, 256), Image.LANCZOS))

sheet = Image.new("RGB", (5 * 296 + 40, 336), (245, 246, 250))
for i, tl in enumerate(tiles):
    sheet.paste(tl, (40 + i * 296, 40), tl)
sheet.save(PREVIEW)
print("preview:", PREVIEW)
