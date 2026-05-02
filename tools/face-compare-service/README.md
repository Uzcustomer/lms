# Face Compare Service

Local FastAPI microservice that compares two face images using DeepFace
(ArcFace model). Used by the admin student-photo review page.

## Install

```bash
# 1. Create directory and copy files
sudo mkdir -p /var/www/face-compare-service
sudo cp app.py requirements.txt /var/www/face-compare-service/
sudo chown -R www-data:www-data /var/www/face-compare-service

# 2. Create venv + install deps (as www-data so model weights land in
#    the service-owned HOME)
cd /var/www/face-compare-service
sudo -u www-data python3 -m venv venv
sudo -u www-data ./venv/bin/pip install --upgrade pip
sudo -u www-data ./venv/bin/pip install -r requirements.txt

# 3. Install and start systemd service
sudo cp face-compare.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now face-compare
sudo systemctl status face-compare
```

The service binds to `127.0.0.1:5005` only. First startup downloads the
ArcFace weights (~130MB) — takes 30–60s. Check the logs:

```bash
sudo journalctl -u face-compare -f
```

## Test

```bash
curl -s http://127.0.0.1:5005/health
# → {"status":"ok","model":"ArcFace"}

curl -s -X POST http://127.0.0.1:5005/compare \
    -H 'Content-Type: application/json' \
    -d '{"image1":"https://example.com/a.jpg","image2":"/var/www/lmsttatf/public/uploads/student-photos/2026-04/xxx.jpg"}'
```

Response:
```json
{
  "similarity_percent": 87.32,
  "distance": 0.2415,
  "threshold": 0.68,
  "match": true,
  "model": "ArcFace"
}
```

## Laravel integration

Laravel reads the service URL from `config/services.php` → `face_compare.url`,
default `http://127.0.0.1:5005`. Override with `FACE_COMPARE_URL` env var.
