/**
 * "Fanlar farqi" sahifasining o'z CSS'i (resources/views/admin/subject-differences/_styles.blade.php).
 *
 * Serverda ./public konteynerga tashqaridan ulangan va `git pull`dan keyin `npm run build`
 * qilinmaydi — yangi Tailwind klasslari asosiy CSS'ga tushmaydi. Shuning uchun sahifa o'zi uchun
 * kerakli utility'larni inline <style> bilan olib keladi; hammasi `.sd-page` ichiga cheklangan,
 * preflight o'chiq (asosiy CSS'da bor).
 *
 * Sahifa klasslari o'zgarsa, loyiha ildizidan qayta yarating:
 *   npx tailwindcss@3 -c tools/subject-differences/tailwind.config.cjs \
 *     -i tools/subject-differences/input.css -o /tmp/sd.css --minify \
 *   && php tools/subject-differences/build-styles.php /tmp/sd.css
 */
module.exports = {
    content: ['./resources/views/admin/subject-differences/*.blade.php'],
    important: '.sd-page',
    corePlugins: { preflight: false },
    theme: { extend: {} },
    plugins: [],
};
