<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maxfiylik siyosati — TDTU LMS</title>
    <meta name="description" content="TDTU LMS mobil ilovasi va veb-saytining maxfiylik siyosati.">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #475569;
            --line: #e2e8f0;
            --accent: #0d9488;
            --bg: #f8fafc;
            --surface: #ffffff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #e2e8f0;
                --muted: #94a3b8;
                --line: #1e293b;
                --accent: #2dd4bf;
                --bg: #0f172a;
                --surface: #111c33;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0 16px 64px;
            background: var(--bg);
            color: var(--ink);
            font: 16px/1.65 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
        }
        .wrap { max-width: 760px; margin: 0 auto; }
        header {
            padding: 40px 0 24px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 32px;
        }
        h1 { font-size: 28px; line-height: 1.25; margin: 0 0 8px; }
        h2 {
            font-size: 19px;
            margin: 40px 0 12px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }
        h2:first-of-type { border-top: 0; padding-top: 0; }
        h3 { font-size: 16px; margin: 24px 0 8px; }
        .lead { color: var(--muted); margin: 0; }
        .updated {
            display: inline-block;
            margin-top: 14px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--line);
            font-size: 13px;
            color: var(--muted);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14.5px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            text-align: left;
            padding: 11px 13px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }
        th { background: rgba(13,148,136,.08); font-weight: 700; }
        tr:last-child td { border-bottom: 0; }
        ul { padding-left: 22px; }
        li { margin: 6px 0; }
        .note {
            background: var(--surface);
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 10px;
            padding: 14px 16px;
            margin: 20px 0;
        }
        a { color: var(--accent); }
        footer {
            margin-top: 48px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 14px;
        }
        @media (max-width: 560px) {
            h1 { font-size: 23px; }
            table { font-size: 13.5px; }
            th, td { padding: 9px 10px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Maxfiylik siyosati</h1>
        <p class="lead">
            Toshkent Davlat Tibbiyot Universiteti Termiz filiali — <strong>TDTU LMS</strong>
            mobil ilovasi va <a href="https://mark.tashmedunitf.uz">mark.tashmedunitf.uz</a> veb-sayti.
        </p>
        <span class="updated">Oxirgi yangilanish: {{ $updatedAt ?? '23.09.2026' }}</span>
    </header>

    <h2>1. Umumiy ma'lumot</h2>
    <p>
        TDTU LMS — Toshkent Davlat Tibbiyot Universiteti Termiz filialining ta'lim boshqaruv tizimi.
        Ilovadan faqat universitetning ro'yxatdan o'tgan talabalari va xodimlari foydalanadi.
        Ilova ochiq ro'yxatdan o'tishni taklif qilmaydi: hisob yozuvlari universitet tomonidan yaratiladi.
    </p>
    <p>
        Ushbu hujjat qanday ma'lumotlar yig'ilishini, ular nima uchun kerakligini va
        ular bilan nima qilinishini tushuntiradi.
    </p>

    <h2>2. Yig'iladigan ma'lumotlar</h2>

    <h3>2.1. Hisob va ta'lim ma'lumotlari</h3>
    <p>
        Bu ma'lumotlar universitetning HEMIS tizimidan olinadi, ilova ularni sizdan so'ramaydi:
    </p>
    <ul>
        <li>Ism-sharif, talaba ID raqami yoki xodim identifikatori</li>
        <li>Guruh, kurs, fakultet, yo'nalish, semestr</li>
        <li>Dars jadvali, baholar, davomat, imtihon natijalari</li>
        <li>Aloqa ma'lumotlari (telefon, elektron pochta), agar profilda ko'rsatilgan bo'lsa</li>
    </ul>

    <h3>2.2. Yuz tasviri (biometrik ma'lumot)</h3>
    <div class="note">
        <strong>Yuz tasviri biometrik ma'lumot hisoblanadi va alohida himoyalanadi.</strong>
        U faqat quyidagi ikki holatda olinadi va har safar siz kamerani ochishga rozilik berasiz.
    </div>
    <ul>
        <li>
            <strong>Face ID orqali kirish</strong> — parol o'rniga yuzingiz bilan tizimga kirganingizda.
            Bu ixtiyoriy: har doim parol bilan kirish mumkin.
        </li>
        <li>
            <strong>Davomatni tasdiqlash</strong> — o'qituvchi yuz tekshiruvini yoqqan darslarda,
            davomatni tasdiqlaganingizda.
        </li>
    </ul>
    <p>
        Olingan surat universitet serveriga yuboriladi va u yerda sizning oldindan tasdiqlangan
        rasmingiz bilan solishtiriladi. Solishtirish universitetning o'z serverida bajariladi,
        hech qanday tashqi yoki xorijiy xizmatga yuborilmaydi.
    </p>
    <p>
        Tekshiruv natijasi (mos keldi yoki yo'q, o'xshashlik foizi, sana va vaqt) xavfsizlik
        jurnalida saqlanadi. Surat ushbu jurnalda faqat shubhali hollarda — masalan, boshqa
        talabaning hisobiga kirishga urinilganda — dalil sifatida saqlanadi.
    </p>

    <h3>2.3. Bluetooth va xonadagi mavjudlik</h3>
    <p>
        Ma'ruza xonalarida davomatni avtomatlashtirish uchun ilova xonaga o'rnatilgan
        Bluetooth mayoqlarining (beacon) signalini eshitadi. Ilova quyidagilarni yozib oladi:
    </p>
    <ul>
        <li>Mayoq identifikatori (qaysi xona)</li>
        <li>Signal kuchi (dBm) va eshitilgan vaqt</li>
    </ul>
    <div class="note">
        <strong>Ilova sizning geografik joylashuvingizni aniqlamaydi va yig'maydi.</strong>
        GPS ishlatilmaydi. Ilova faqat "bu telefon shu ma'ruza xonasida" degan xulosani chiqaradi.
        Android 12 va undan yuqori versiyalarda ilova joylashuv ruxsatini umuman so'ramaydi
        (<code>neverForLocation</code> bayrog'i). Android 11 va undan eski versiyalarda
        tizimning o'z talabi sababli joylashuv ruxsati so'raladi, lekin u yana faqat
        Bluetooth signalini eshitish uchun ishlatiladi.
    </div>
    <p>
        Skanerlash faqat ilova ochiq turganda ishlaydi. Ilovani yopsangiz yoki telefonni
        qulflasangiz, skanerlash to'xtaydi. Ilova fon rejimida sizni kuzatmaydi.
    </p>

    <h3>2.4. Bildirishnomalar</h3>
    <p>
        Push bildirishnomalar yuborish uchun qurilmangizning Firebase Cloud Messaging
        tokeni saqlanadi. Bu token qurilmani aniqlaydi, lekin uning egasi haqida
        boshqa ma'lumot bermaydi.
    </p>

    <h3>2.5. Texnik ma'lumotlar</h3>
    <p>
        Xavfsizlik va nosozliklarni bartaraf etish uchun kirish vaqti, IP manzil va
        qurilma turi haqidagi yozuvlar saqlanadi.
    </p>

    <h2>3. Ma'lumotlar nima uchun ishlatiladi</h2>
    <table>
        <tr>
            <th>Ma'lumot</th>
            <th>Maqsad</th>
        </tr>
        <tr>
            <td>Ism, ID, guruh</td>
            <td>Hisobga kirish, ta'lim jarayonini yuritish</td>
        </tr>
        <tr>
            <td>Baho, davomat, jadval</td>
            <td>O'quv jarayonini ko'rsatish va hisobga olish</td>
        </tr>
        <tr>
            <td>Yuz tasviri</td>
            <td>Shaxsni tasdiqlash: tizimga kirish va davomatni chetdan tasdiqlashning oldini olish</td>
        </tr>
        <tr>
            <td>Bluetooth mayoq signali</td>
            <td>Talaba ma'ruza xonasida ekanini aniqlash</td>
        </tr>
        <tr>
            <td>Qurilma tokeni</td>
            <td>Davomat va boshqa bildirishnomalarni yuborish</td>
        </tr>
        <tr>
            <td>IP, kirish jurnali</td>
            <td>Xavfsizlik, suiiste'molning oldini olish</td>
        </tr>
    </table>
    <p>
        Ma'lumotlar reklama uchun ishlatilmaydi. Ilovada reklama yo'q va foydalanuvchi
        xatti-harakatini kuzatuvchi uchinchi tomon tahlil xizmatlari ishlatilmaydi.
    </p>

    <h2>4. Ma'lumotlarni uchinchi shaxslarga berish</h2>
    <p>
        Universitet sizning shaxsiy ma'lumotlaringizni <strong>sotmaydi va ijaraga bermaydi</strong>.
        Ma'lumotlar faqat quyidagi hollarda uzatiladi:
    </p>
    <ul>
        <li>
            <strong>Google Firebase</strong> — faqat push bildirishnomalarni yetkazish uchun.
            Bildirishnoma matni va qurilma tokeni uzatiladi. Yuz tasviri, baholar yoki
            shaxsiy ma'lumotlar Firebase orqali uzatilmaydi.
        </li>
        <li>
            <strong>HEMIS</strong> — O'zbekiston Respublikasi Oliy ta'lim vazirligining
            yagona axborot tizimi; qonunchilikka muvofiq.
        </li>
        <li>
            <strong>Qonuniy talab</strong> — vakolatli davlat organining qonuniy so'rovi asosida.
        </li>
    </ul>
    <p>
        Yuz tasvirini solishtirish universitetning o'z serverida bajariladi.
        Bu ma'lumot hech qanday tashqi xizmatga yuborilmaydi.
    </p>

    <h2>5. Saqlash muddati</h2>
    <ul>
        <li><strong>Ta'lim ma'lumotlari</strong> — talaba universitetda o'qigan davr va undan keyin arxiv sifatida, qonunchilikda belgilangan muddat davomida.</li>
        <li><strong>Tasdiqlangan yuz rasmi</strong> — hisob faol bo'lgan davr mobaynida.</li>
        <li><strong>Davomat uchun olingan suratlar</strong> — solishtirish tugagach saqlanmaydi; faqat natija yoziladi.</li>
        <li><strong>Mayoq signali yozuvlari</strong> — davomat hisobotlari uchun o'quv yili davomida.</li>
        <li><strong>Qurilma tokeni</strong> — tizimdan chiqqaningizda yoki ilovani o'chirganingizda amal qilishdan to'xtaydi.</li>
    </ul>

    <h2>6. Xavfsizlik</h2>
    <ul>
        <li>Barcha aloqa HTTPS shifrlangan kanal orqali amalga oshiriladi.</li>
        <li>Kirish tokeni telefonning himoyalangan xotirasida saqlanadi (Android Keystore / iOS Keychain).</li>
        <li>Parollar qaytarib bo'lmaydigan usulda shifrlanadi.</li>
        <li>Yuz tekshiruvining har bir urinishi jurnalga yoziladi.</li>
    </ul>

    <h2>7. Sizning huquqlaringiz</h2>
    <ul>
        <li><strong>Ko'rish</strong> — o'zingiz haqingizdagi ma'lumotlarni ilovada yoki saytda ko'rishingiz mumkin.</li>
        <li><strong>Tuzatish</strong> — noto'g'ri ma'lumotni tuzatish uchun dekanat yoki tyutorga murojaat qiling.</li>
        <li><strong>Face ID dan voz kechish</strong> — yuz bilan kirishni o'chirib, faqat parol bilan foydalanishingiz mumkin.</li>
        <li><strong>Bluetooth ruxsatini bermaslik</strong> — bu holda avtomatik davomat ishlamaydi va o'qituvchi davomatni qo'lda belgilaydi.</li>
        <li><strong>Kamera ruxsatini bermaslik</strong> — ilovaning qolgan qismi ishlayveradi.</li>
        <li><strong>O'chirish</strong> — hisobni o'chirish talabi uchun quyidagi manzilga murojaat qiling. Ta'lim to'g'risidagi ma'lumotlar qonunchilik talab qilgan muddat davomida saqlanishi mumkin.</li>
    </ul>

    <h2>8. Voyaga yetmaganlar</h2>
    <p>
        Ilova universitet talabalari uchun mo'ljallangan. 18 yoshga to'lmagan talabalar
        uchun ma'lumotlarni qayta ishlash ularning qonuniy vakillari xabardorligida,
        ta'lim jarayonini yuritish maqsadida amalga oshiriladi.
    </p>

    <h2>9. O'zgartirishlar</h2>
    <p>
        Ushbu siyosat yangilanishi mumkin. Muhim o'zgarishlar haqida ilova orqali
        xabar beriladi. Yangilangan sana hujjatning yuqorisida ko'rsatiladi.
    </p>

    <h2>10. Aloqa</h2>
    <p>
        Maxfiylik bo'yicha savollar, ma'lumotlarni tuzatish yoki o'chirish talablari uchun:
    </p>
    <table>
        <tr>
            <th>Tashkilot</th>
            <td>Toshkent Davlat Tibbiyot Universiteti Termiz filiali</td>
        </tr>
        <tr>
            <th>Manzil</th>
            <td>{{ $address ?? "Termiz shahri, Surxondaryo viloyati, O'zbekiston" }}</td>
        </tr>
        <tr>
            <th>Elektron pochta</th>
            <td><a href="mailto:{{ $email ?? 'info@tashmedunitf.uz' }}">{{ $email ?? 'info@tashmedunitf.uz' }}</a></td>
        </tr>
        <tr>
            <th>Telefon</th>
            <td>{{ $phone ?? '—' }}</td>
        </tr>
    </table>

    <footer>
        © {{ date('Y') }} Toshkent Davlat Tibbiyot Universiteti Termiz filiali.
    </footer>
</div>
</body>
</html>
