# HEMIS'ga dars jadvalini yozish (registrator ofisi)

1. LMS → Dars jadvali tuzish → qamrovni tanlang → **HEMIS solishtirish**.
   Oynada «Qaysi haftagacha» ni tanlang (sukut — joriy hafta oxirigacha), so'ng **JSON** ni yuklab oling.
   JSON'da faqat shu haftagacha bo'lgan amallar bo'ladi.
2. Bir marta o'rnatish (shu papkada): `npm install` va `npx playwright install chromium`.
3. Avval ko'rish (hech narsa yozilmaydi):
   `node hemis-write.mjs --json hemis_amallar_2026-10-23.json`
4. HEMIS formalarini tekshirish (yozmaydi, brauzer ochiladi, HEMIS'ga o'zingiz kirasiz):
   `node hemis-write.mjs --json ... --check`
5. Yozish (har guruhdan oldin tasdiq so'raydi):
   `node hemis-write.mjs --json ... --apply`

Qo'shimcha chegaralar (JSON ichidan yana toraytiradi):
`--current-week`, `--until-week 7`, `--until 2026-10-31`, `--group p/st26-01a,p/st26-01b`, `--only create,update`.

Bugun va undan oldingi kunlarga hech qachon yozilmaydi. To'siqli amallar (o'qituvchi yoki xona topilmagan) o'tkazib yuboriladi.
Har ishga tushirishdan keyin `hemis-write-log-*.json` jurnali qoladi. Yozib bo'lgach LMS'da HEMIS jadval importini
yangilab, solishtirishni qayta bosing — farq qolmagan bo'lishi kerak.
