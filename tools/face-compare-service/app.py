"""
Face Comparison Microservice
----------------------------
Compares two face images using DeepFace (ArcFace model) and returns a
similarity percent plus a match/mismatch decision.

Used by the LMS admin student-photo review page to help the reviewer
decide whether an uploaded photo matches the student's HEMIS profile.

Runs bound to 127.0.0.1 only — do not expose publicly.
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from deepface import DeepFace
import logging
import os

# Silence TensorFlow verbose logs
os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

app = FastAPI(title="LMS Face Compare")
logger = logging.getLogger("uvicorn.error")

MODEL_NAME = "ArcFace"
DETECTOR_BACKEND = "opencv"


class CompareRequest(BaseModel):
    image1: str  # URL or absolute local path
    image2: str  # URL or absolute local path


@app.on_event("startup")
def warmup():
    # Force model download + initialization on startup so the first user
    # request is not delayed by ~30s of weight download.
    try:
        DeepFace.build_model(MODEL_NAME)
        logger.info(f"{MODEL_NAME} model warmed up")
    except Exception as e:
        logger.error(f"warmup failed: {e}")


@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_NAME}


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

    # Map distance → user-friendly percent.
    # distance 0     → 100%
    # distance thr   → 60% (boundary)
    # distance 2*thr → 0%
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
