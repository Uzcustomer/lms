#!/bin/sh
# LMS Face Compare watchdog: /health 10 soniyada javob bermasa — jarayon
# "active" bo'lsa ham qotib qolgan (2026-10-18 da shunday bo'ldi: uvicorn tirik,
# CPU 100%, bir kun davomida bitta so'rov ham o'tmadi). Shunda xizmat qayta
# ishga tushiriladi. Model yuklanayotgan dastlabki 90 soniyada tekshirilmaydi.
SERVICE=face-compare
URL=http://127.0.0.1:5005/health
GRACE_SEC=90

if ! systemctl is-active --quiet "$SERVICE"; then
    # O'chiq bo'lsa systemd ning Restart=always o'zi ko'taradi
    exit 0
fi

started_us=$(systemctl show -p ActiveEnterTimestampMonotonic --value "$SERVICE" 2>/dev/null)
now_us=$(awk '{printf "%d", $1 * 1000000}' /proc/uptime)
if [ -n "$started_us" ] && [ "$started_us" -gt 0 ] 2>/dev/null; then
    age_us=$((now_us - started_us))
    if [ "$age_us" -lt $((GRACE_SEC * 1000000)) ]; then
        exit 0
    fi
fi

if curl -fsS -m 10 "$URL" >/dev/null 2>&1; then
    exit 0
fi

echo "face-compare /health javob bermadi — qayta ishga tushirilmoqda"
systemctl restart "$SERVICE"
