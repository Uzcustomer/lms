"""
Face Comparison + Photo Quality Service
---------------------------------------
- POST /compare        compare two face images, return similarity %
- POST /quality-check  inspect a photo against student-photo standards
                       (centering, framing, white coat, lighting, size)

Used by the LMS admin student-photo review page. Runs bound to
127.0.0.1:5005 and the Docker bridge IP; do not expose publicly.
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from deepface import DeepFace
import logging
import os
import io
import requests
from urllib.parse import urlsplit, urlunsplit, quote
import numpy as np
import cv2
from PIL import Image

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

app = FastAPI(title="LMS Face Tools")
logger = logging.getLogger("uvicorn.error")

MODEL_NAME = "ArcFace"
DETECTOR_BACKEND = "opencv"


class CompareRequest(BaseModel):
    image1: str  # URL or absolute local path
    image2: str


class QualityRequest(BaseModel):
    image: str  # URL or absolute local path


@app.on_event("startup")
def warmup():
    try:
        DeepFace.build_model(MODEL_NAME)
        logger.info(f"{MODEL_NAME} model warmed up")
    except Exception as e:
        logger.error(f"warmup failed: {e}")


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME}


# ───────────────────────────── /compare ──────────────────────────────

@app.post("/compare")
def compare(req: CompareRequest):
    try:
        result = DeepFace.verify(
            img1_path=req.image1,
            img2_path=req.image2,
            model_name=MODEL_NAME,
            detector_backend=DETECTOR_BACKEND,
            enforce_detection=False,
            align=True,
        )
    except Exception as e:
        logger.exception("verify failed")
        raise HTTPException(status_code=500, detail=str(e))

    distance = float(result["distance"])
    threshold = float(result["threshold"])
    verified = bool(result["verified"])

    if distance <= threshold:
        percent = 60.0 + (1.0 - distance / threshold) * 40.0
    else:
        percent = max(0.0, 60.0 - ((distance - threshold) / threshold) * 60.0)
    percent = max(0.0, min(100.0, percent))

    return {
        "similarity_percent": round(percent, 2),
        "distance": round(distance, 4),
        "threshold": round(threshold, 4),
        "match": verified,
        "model": MODEL_NAME,
    }


# ──────────────────────────── /quality-check ─────────────────────────

def _load_image_bgr(src: str) -> np.ndarray:
    """Load an image from a URL or local path into a BGR OpenCV array.

    Percent-encodes non-ASCII URL paths (O'G'LI-style apostrophes,
    Cyrillic filenames) before handing off to requests, because the
    underlying http client refuses to build requests from raw unicode.
    """
    if src.startswith(("http://", "https://")):
        parts = urlsplit(src)
        safe_path = quote(parts.path, safe="/%")
        safe_query = quote(parts.query, safe="=&%")
        url = urlunsplit((parts.scheme, parts.netloc, safe_path, safe_query, parts.fragment))
        resp = requests.get(
            url,
            headers={"User-Agent": "lms-quality/1.0"},
            timeout=30,
            verify=False,
        )
        resp.raise_for_status()
        pil = Image.open(io.BytesIO(resp.content)).convert("RGB")
    else:
        pil = Image.open(src).convert("RGB")
    rgb = np.array(pil)
    return cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)


def _detect_face_bbox(img_bgr: np.ndarray):
    """Use DeepFace to find the largest face. Returns (x, y, w, h) or None."""
    try:
        faces = DeepFace.extract_faces(
            img_path=img_bgr,
            detector_backend="opencv",
            enforce_detection=False,
            align=False,
        )
    except Exception as e:
        logger.warning(f"face detect failed: {e}")
        return None

    if not faces:
        return None

    best = None
    best_area = 0
    for f in faces:
        area = f.get("facial_area", {}) or {}
        w = int(area.get("w", 0))
        h = int(area.get("h", 0))
        if w * h > best_area:
            best_area = w * h
            best = (int(area.get("x", 0)), int(area.get("y", 0)), w, h)
    return best


def _check_centering(bbox, img_w, img_h):
    if not bbox:
        return None
    x, y, w, h = bbox
    face_cx = x + w / 2
    face_cy = y + h / 2
    dx = (face_cx - img_w / 2) / img_w
    dy = (face_cy - img_h / 3) / img_h  # yuzning ideal markazi yuqorida, 1/3 da
    return {"dx": dx, "dy": dy}


def _check_face_size(bbox, img_w, img_h):
    if not bbox:
        return None
    _, _, w, h = bbox
    return {
        "face_h_ratio": h / img_h,
        "face_w_ratio": w / img_w,
    }


def _check_white_coat(img_bgr, face_bbox):
    """Sample the torso area below the face and check for low-saturation high-value (white)."""
    h, w = img_bgr.shape[:2]
    if face_bbox:
        fx, fy, fw, fh = face_bbox
        top = min(h - 1, fy + fh + int(fh * 0.3))
    else:
        top = int(h * 0.55)
    bottom = int(h * 0.95)
    left = int(w * 0.3)
    right = int(w * 0.7)
    if bottom <= top or right <= left:
        return None

    region = img_bgr[top:bottom, left:right]
    hsv = cv2.cvtColor(region, cv2.COLOR_BGR2HSV)
    s = hsv[:, :, 1]
    v = hsv[:, :, 2]
    white_mask = (s < 50) & (v > 170)
    white_ratio = float(white_mask.mean())
    return {
        "white_ratio": white_ratio,
        "mean_saturation": float(s.mean()),
        "mean_value": float(v.mean()),
    }


def _check_background_shadow(img_bgr, face_bbox):
    """Sample left and right edges of the background. Large diff = shadow."""
    h, w = img_bgr.shape[:2]
    top_y = 0
    bottom_y = int(h * 0.35)
    strip_w = max(10, int(w * 0.08))
    if face_bbox:
        bottom_y = min(bottom_y, face_bbox[1])  # yuz ustidan olmayapmiz
    if bottom_y <= top_y + 5:
        bottom_y = top_y + max(10, int(h * 0.15))

    left = img_bgr[top_y:bottom_y, 0:strip_w]
    right = img_bgr[top_y:bottom_y, w - strip_w:w]
    left_gray = cv2.cvtColor(left, cv2.COLOR_BGR2GRAY).mean() if left.size else 0
    right_gray = cv2.cvtColor(right, cv2.COLOR_BGR2GRAY).mean() if right.size else 0
    diff = abs(float(left_gray) - float(right_gray))
    return {
        "left_brightness": float(left_gray),
        "right_brightness": float(right_gray),
        "diff": diff,
    }


def _check_overall_brightness(img_bgr):
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    return {
        "mean": float(gray.mean()),
        "std": float(gray.std()),
    }


def _check_sharpness(img_bgr):
    """Laplacian variance — low = blurry."""
    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
    return float(cv2.Laplacian(gray, cv2.CV_64F).var())


@app.post("/quality-check")
def quality_check(req: QualityRequest):
    try:
        img_bgr = _load_image_bgr(req.image)
    except Exception as e:
        logger.exception("image load failed")
        raise HTTPException(status_code=400, detail=f"Rasmni yuklab bo'lmadi: {e}")

    h, w = img_bgr.shape[:2]
    bbox = _detect_face_bbox(img_bgr)

    issues = []
    ok = []
    points = 100.0

    # ─── O'lcham
    if w < 400 or h < 500:
        issues.append(f"O'lcham juda kichik ({w}×{h}px, min 400×500 kerak)")
        points -= 20
    else:
        ok.append(f"O'lcham mos: {w}×{h}px")

    # ─── Aspect ratio (portrait yaqin)
    ratio = w / h
    if ratio > 0.95 or ratio < 0.55:
        issues.append(f"Aspect ratio mos emas ({ratio:.2f}, 3:4 ~ 0.75 kerak)")
        points -= 10
    else:
        ok.append("Portret yo'nalishi mos")

    # ─── Yuz topilganmi
    if not bbox:
        issues.append("Yuz aniqlanmadi — rasmda yuz topilmadi yoki yomon ko'rinadi")
        points -= 40
    else:
        ok.append("Yuz aniqlandi")

        # Markazga tushish
        cen = _check_centering(bbox, w, h)
        if cen:
            dx_pct = abs(cen["dx"]) * 100
            if dx_pct > 12:
                side = "chap" if cen["dx"] < 0 else "o'ng"
                issues.append(f"Yuz markazga tushmagan ({side} tomonga {dx_pct:.0f}% surilgan)")
                points -= 15
            else:
                ok.append(f"Yuz markazda ({dx_pct:.0f}% og'ish)")

        # Yuz hajmi
        fsz = _check_face_size(bbox, w, h)
        if fsz:
            fh = fsz["face_h_ratio"]
            if fh < 0.15:
                issues.append(f"Yuz juda kichik (balandlikning {fh*100:.0f}% ni egallaydi)")
                points -= 12
            elif fh > 0.55:
                issues.append(f"Yuz juda yaqin (balandlikning {fh*100:.0f}% ni egallaydi)")
                points -= 10
            else:
                ok.append(f"Yuz hajmi mos ({fh*100:.0f}%)")

    # ─── Oq xalat
    wc = _check_white_coat(img_bgr, bbox)
    if wc:
        if wc["white_ratio"] >= 0.35:
            ok.append(f"Oq xalat: topildi ({wc['white_ratio']*100:.0f}%)")
        elif wc["white_ratio"] >= 0.15:
            issues.append(f"Oq xalat qisman ko'rinadi ({wc['white_ratio']*100:.0f}%) — to'liq xalatda bo'lmay")
            points -= 8
        else:
            issues.append(f"Oq xalat aniqlanmadi (pastki qismida oq rang {wc['white_ratio']*100:.0f}%)")
            points -= 15

    # ─── Fon soyasi
    sh = _check_background_shadow(img_bgr, bbox)
    if sh and sh["diff"] > 40:
        issues.append(f"Fonda soya bor (chap/o'ng farq: {sh['diff']:.0f})")
        points -= 10
    elif sh:
        ok.append("Fon tekis yoritilgan")

    # ─── Umumiy yorug'lik
    br = _check_overall_brightness(img_bgr)
    if br["mean"] < 80:
        issues.append(f"Rasm juda qorong'i (o'rtacha yorug'lik {br['mean']:.0f}/255)")
        points -= 12
    elif br["mean"] > 230:
        issues.append(f"Rasm haddan tashqari yorqin (o'rtacha yorug'lik {br['mean']:.0f}/255)")
        points -= 8

    # ─── Blur / keskinlik
    sharp = _check_sharpness(img_bgr)
    if sharp < 60:
        issues.append(f"Rasm xiralashgan (keskinlik {sharp:.0f}, &gt;100 kerak)")
        points -= 12
    elif sharp > 2000:
        # juda yuqori keskinlik — skan bo'lishi mumkin (paper texture)
        pass

    points = max(0.0, min(100.0, points))
    passed = points >= 70 and bbox is not None

    return {
        "quality_score": round(points, 2),
        "passed": passed,
        "issues": issues,
        "ok": ok,
        "metrics": {
            "width": w,
            "height": h,
            "aspect_ratio": round(ratio, 3),
            "face_bbox": list(bbox) if bbox else None,
            "sharpness": round(sharp, 1),
            "brightness_mean": round(br["mean"], 1),
            "white_coat_ratio": round(wc["white_ratio"], 3) if wc else None,
            "bg_shadow_diff": round(sh["diff"], 1) if sh else None,
        },
    }
