#!/bin/bash
# Face-api.js modellarini yuklab olish
# Ishlatish: bash scripts/download_face_models.sh

set -e

MODELS_DIR="public/face-models"
BASE_URL="https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights"

# Zarur model fayllar ro'yxati
MODELS=(
    # Tiny Face Detector (yuz topish)
    "tiny_face_detector_model-weights_manifest.json"
    "tiny_face_detector_model-shard1"
    # Face Landmark 68 (ko'z, burun, lab nuqtalari)
    "face_landmark_68_model-weights_manifest.json"
    "face_landmark_68_model-shard1"
    # Face Recognition (128-dim descriptor)
    "face_recognition_model-weights_manifest.json"
    "face_recognition_model-shard1"
    "face_recognition_model-shard2"
)

echo "📁 Papka: $MODELS_DIR"
mkdir -p "$MODELS_DIR"

echo "⬇️  Modellar yuklanmoqda..."
for MODEL in "${MODELS[@]}"; do
    FILE="$MODELS_DIR/$MODEL"
    if [ -f "$FILE" ]; then
        echo "   ✅ Mavjud: $MODEL"
    else
        echo "   ⬇️  Yuklanmoqda: $MODEL"
        curl -fsSL "$BASE_URL/$MODEL" -o "$FILE"
        echo "   ✅ Yuklandi: $MODEL ($(du -sh "$FILE" | cut -f1))"
    fi
done

echo ""
echo "✅ Barcha modellar yuklandi!"
echo "📊 Jami hajm: $(du -sh $MODELS_DIR | cut -f1)"
echo ""
echo "ℹ️  Endi Face ID login sahifasi ishlaydi: /student/face-id/login"
