"""
Face Comparison + Photo Quality Service
---------------------------------------
- POST /compare        compare two face images, return similarity %
- POST /quality-check  inspect a photo against student-photo standards
                       (centering, framing, white coat, lighting, size)
- POST /embed          extract a 512-dim ArcFace embedding for one image
- POST /identify       1:N identification — find best match in cached embeddings
- POST /refresh-cache  replace the in-memory student embedding cache
- POST /reload-from-db reload cache from MySQL (student_photos.face_embedding)
- GET  /cache-info     report cache size and sample IDs

On startup, the service reads embeddings directly from the MySQL DB
(student_photos.face_embedding for status='approved' rows). This means
the cache survives service restarts without external action.

Used by the LMS admin student-photo review page and student Face ID login.
Runs bound to 127.0.0.1:5005 and the Docker bridge IP; do not expose
publicly.
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from deepface import DeepFace
import json
import logging
import os
import io
import threading
import requests
from urllib.parse import urlsplit, urlunsplit, quote
import numpy as np
import cv2
from PIL import Image

# DB ulanishlari (PyMySQL — sof Python, oddiy)
import pymysql

# .env faylni o'qish uchun (agar mavjud bo'lsa)
try:
    from dotenv import load_dotenv
    # Laravel .env odatda /var/www/lmsttatf/.env yo'lida; service uni
    # EnvironmentFile yoki LARAVEL_ENV_PATH orqali topadi
    _env_path = os.environ.get("LARAVEL_ENV_PATH")
    if _env_path and os.path.isfile(_env_path):
        load_dotenv(_env_path)
except Exception:
    pass

os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

app = FastAPI(title="LMS Face Tools")
logger = logging.getLogger("uvicorn.error")

MODEL_NAME = "ArcFace"
DETECTOR_BACKEND = "opencv"

# ─────────────────────── In-memory identification cache ───────────────────────
# {student_id_number: np.ndarray (512,)}
_identify_cache: dict[str, np.ndarray] = {}
_identify_meta: dict[str, dict] = {}
_cache_lock = threading.Lock()


class CompareRequest(BaseModel):
    image1: str  # URL or absolute local path
    image2: str


class QualityRequest(BaseModel):
    image: str  # URL or absolute local path


class EmbedRequest(BaseModel):
    image: str  # URL or absolute local path


class IdentifyRequest(BaseModel):
    image: str  # URL or absolute local path
    top_k: int = 1


class CacheItem(BaseModel):
    student_id_number: str
    embedding: list[float] | None = None
    image: str | None = None  # URL/path — server o'zi embedding hisoblaydi
    full_name: str | None = None


class RefreshCacheRequest(BaseModel):
    items: list[CacheItem]
    replace: bool = True   # True bo'lsa eski cache butunlay yangilanadi


# ───────────────────────── DB cache loader ────────────────────────────

def _db_connect():
    """
    MySQL ulanish — DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
    environment variable'lari (Laravel .env'dan keladi).
    """
    host = os.environ.get("DB_HOST", "127.0.0.1")
    port = int(os.environ.get("DB_PORT", "3306"))
    database = os.environ.get("DB_DATABASE")
    user = os.environ.get("DB_USERNAME")
    password = os.environ.get("DB_PASSWORD", "")
    if not (database and user):
        raise RuntimeError("DB_DATABASE va DB_USERNAME atrof-muhit o'zgaruvchilari kerak")
    return pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10,
    )


def _load_cache_from_db() -> dict:
    """
    student_photos jadvalidan tasdiqlangan va embedding mavjud yozuvlarni o'qib
    cache'ni qayta tuzadi. Yangi cache lug'ati va meta lug'atini qaytaradi
    (eski cache'ni almashtirish uchun atomic swap).
    """
    new_cache: dict[str, np.ndarray] = {}
    new_meta: dict[str, dict] = {}
    failed = 0

    conn = _db_connect()
    try:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT student_id_number, full_name, face_embedding
                FROM student_photos
                WHERE status = 'approved'
                  AND face_embedding IS NOT NULL
            """)
            for row in cur:
                sid = (row.get("student_id_number") or "").strip()
                if not sid:
                    continue
                emb_raw = row.get("face_embedding")
                if isinstance(emb_raw, (bytes, bytearray)):
                    emb_raw = emb_raw.decode("utf-8", errors="ignore")
                try:
                    if isinstance(emb_raw, str):
                        arr_list = json.loads(emb_raw)
                    elif isinstance(emb_raw, list):
                        arr_list = emb_raw
                    else:
                        failed += 1
                        continue
                    arr = np.asarray(arr_list, dtype=np.float32)
                    norm = np.linalg.norm(arr)
                    if norm < 1e-6:
                        failed += 1
                        continue
                    new_cache[sid] = arr / norm
                    new_meta[sid] = {"full_name": row.get("full_name") or ""}
                except Exception:
                    failed += 1
    finally:
        conn.close()

    logger.info(f"DB cache yuklandi: {len(new_cache)} ta, xato {failed}")
    return {"cache": new_cache, "meta": new_meta, "failed": failed}


def _swap_cache(new_cache: dict, new_meta: dict) -> int:
    with _cache_lock:
        _identify_cache.clear()
        _identify_meta.clear()
        _identify_cache.update(new_cache)
        _identify_meta.update(new_meta)
        return len(_identify_cache)


@app.on_event("startup")
def warmup():
    try:
        DeepFace.build_model(MODEL_NAME)
        logger.info(f"{MODEL_NAME} model warmed up")
    except Exception as e:
        logger.error(f"warmup failed: {e}")

    # Cache'ni DB'dan yuklash (xato bo'lsa service ishlayveradi, faqat cache bo'sh qoladi)
    try:
        loaded = _load_cache_from_db()
        size = _swap_cache(loaded["cache"], loaded["meta"])
        logger.info(f"identify cache restored from DB: cache_size={size}")
    except Exception as e:
        logger.error(f"DB cache load failed: {e}")


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME, "cache_size": len(_identify_cache)}


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


# ─────────────────────────── /embed ───────────────────────────────────

def _extract_embedding(src: str) -> np.ndarray | None:
    """ArcFace 512-dim embedding chiqarish. None — yuz topilmadi yoki xato."""
    try:
        reps = DeepFace.represent(
            img_path=src,
            model_name=MODEL_NAME,
            detector_backend=DETECTOR_BACKEND,
            enforce_detection=False,
            align=True,
        )
    except Exception as e:
        logger.warning(f"represent failed: {e}")
        return None

    if not reps:
        return None
    emb = reps[0].get("embedding") if isinstance(reps, list) else None
    if not emb:
        return None
    arr = np.asarray(emb, dtype=np.float32)
    norm = np.linalg.norm(arr)
    if norm < 1e-6:
        return None
    return arr / norm  # L2-normalized → cosine similarity = dot product


@app.post("/embed")
def embed(req: EmbedRequest):
    arr = _extract_embedding(req.image)
    if arr is None:
        raise HTTPException(status_code=422, detail="Yuz aniqlanmadi yoki embedding hisoblab bo'lmadi")
    return {
        "embedding": arr.tolist(),
        "dim": int(arr.shape[0]),
        "model": MODEL_NAME,
    }


# ──────────────────────── /refresh-cache ──────────────────────────────

@app.post("/refresh-cache")
def refresh_cache(req: RefreshCacheRequest):
    """
    Cache'ga embedding'larni yuklash. Har item uchun:
      - embedding berilgan bo'lsa: shu darhol saqlanadi
      - image berilgan bo'lsa: bu yerda hisoblanadi va saqlanadi
    `replace=True` — eski cache butunlay almashtiriladi (item dagilarsiz oldingilar yo'q bo'ladi).
    """
    new_entries: dict[str, np.ndarray] = {}
    new_meta: dict[str, dict] = {}
    failed: list[dict] = []

    for it in req.items:
        sid = (it.student_id_number or '').strip()
        if not sid:
            continue
        if it.embedding:
            arr = np.asarray(it.embedding, dtype=np.float32)
            norm = np.linalg.norm(arr)
            if norm > 1e-6:
                arr = arr / norm
        elif it.image:
            arr = _extract_embedding(it.image)
            if arr is None:
                failed.append({"student_id_number": sid, "reason": "embedding_not_extracted"})
                continue
        else:
            failed.append({"student_id_number": sid, "reason": "neither_embedding_nor_image"})
            continue

        new_entries[sid] = arr
        new_meta[sid] = {"full_name": it.full_name or ""}

    with _cache_lock:
        if req.replace:
            _identify_cache.clear()
            _identify_meta.clear()
        _identify_cache.update(new_entries)
        _identify_meta.update(new_meta)
        size = len(_identify_cache)

    return {
        "ok": True,
        "added_or_updated": len(new_entries),
        "cache_size": size,
        "failed": failed,
    }


@app.get("/cache-info")
def cache_info():
    with _cache_lock:
        size = len(_identify_cache)
        sample = list(_identify_cache.keys())[:10]
    return {"cache_size": size, "sample_ids": sample, "model": MODEL_NAME}


@app.post("/reload-from-db")
def reload_from_db():
    """
    Cache'ni MySQL student_photos jadvalidan qayta yuklash.
    Manual chaqirilganda ishlatiladi (masalan Laravel approve hookida).
    """
    try:
        loaded = _load_cache_from_db()
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"DB load xato: {e}")

    size = _swap_cache(loaded["cache"], loaded["meta"])
    return {
        "ok": True,
        "cache_size": size,
        "failed": loaded["failed"],
        "model": MODEL_NAME,
    }


# ─────────────────────────── /identify ────────────────────────────────

@app.post("/identify")
def identify(req: IdentifyRequest):
    """
    1:N identifikatsiya — kelayotgan rasm uchun cache'dan eng yaqin
    talabani topadi. Cosine similarity (embedding'lar L2-normalized,
    shuning uchun matmul yetarli).
    """
    with _cache_lock:
        if not _identify_cache:
            raise HTTPException(status_code=503, detail="Cache bo'sh — avval /refresh-cache chaqiring")
        ids = list(_identify_cache.keys())
        matrix = np.stack([_identify_cache[i] for i in ids], axis=0)  # (N, D)
        meta_snapshot = {i: _identify_meta.get(i, {}) for i in ids}

    query = _extract_embedding(req.image)
    if query is None:
        raise HTTPException(status_code=422, detail="Yuz aniqlanmadi")

    sims = matrix @ query                    # cosine similarity (-1..1)
    sims_norm = (sims + 1.0) / 2.0           # 0..1 range
    percents = sims_norm * 100.0             # 0..100

    top_k = max(1, min(int(req.top_k or 1), 5))
    top_idx = np.argsort(-percents)[:top_k]

    matches = []
    for i in top_idx:
        sid = ids[int(i)]
        matches.append({
            "student_id_number": sid,
            "similarity_percent": round(float(percents[int(i)]), 2),
            "cosine": round(float(sims[int(i)]), 4),
            "full_name": meta_snapshot.get(sid, {}).get("full_name", ""),
        })

    return {"matches": matches, "cache_size": len(ids), "model": MODEL_NAME}


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
            if fh < 0.22:
                issues.append(f"Yuz juda kichik — rasm tanani ortiqcha ko'rsatadi (yuz balandlikning {fh*100:.0f}%ni egallaydi; to'g'ri portretda 35–55% kerak)")
                points -= 18
            elif fh < 0.32:
                issues.append(f"Framing keng — yelkalargacha qisqartiring (yuz balandlikning {fh*100:.0f}%ni egallaydi)")
                points -= 10
            elif fh > 0.60:
                issues.append(f"Yuz juda yaqin (balandlikning {fh*100:.0f}%ni egallaydi)")
                points -= 10
            else:
                ok.append(f"Yuz hajmi mos ({fh*100:.0f}%)")

        # Ramka pastki chegarasi — yuz ostida qancha joy bor?
        fx, fy, fw, fh_px = bbox
        face_bottom = fy + fh_px
        face_bottom_ratio = face_bottom / h
        if face_bottom_ratio < 0.50:
            issues.append(f"Rasm tirsakdan pastgacha tushgan — yuz ostida tananing {(1-face_bottom_ratio)*100:.0f}%i qolgan; tepa qismidan (yelkadan yuqori) olib qisqartiring")
            points -= 20
        elif face_bottom_ratio < 0.60:
            issues.append(f"Rasm biroz uzunroq — yuz ostida ortiqcha gavda ({(1-face_bottom_ratio)*100:.0f}%); yelkalarga qadar qisqartirish tavsiya etiladi")
            points -= 10

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
