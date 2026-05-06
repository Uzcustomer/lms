{{-- TomSelect — qidiriladigan dropdown. Class .tom-select bo'lgan har bir
     <select> elementi avtomatik aylantiriladi (kvitansiya, fakultet, kurs,
     yo'nalish, o'qituvchi va h.k.). --}}
@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
        <style>
            .ts-control { font-size: 0.75rem; padding: 4px 8px; min-height: 32px; }
            .ts-dropdown { font-size: 0.75rem; }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
        <script>
            (function () {
                function initTomSelects(root) {
                    (root || document).querySelectorAll('select.tom-select:not([data-tom-init])')
                        .forEach(function (el) {
                            el.dataset.tomInit = '1';
                            new TomSelect(el, {
                                allowEmptyOption: true,
                                plugins: el.multiple ? ['remove_button'] : ['clear_button'],
                                persist: false,
                                create: false,
                                maxOptions: 1000,
                                searchField: ['text'],
                                render: {
                                    no_results: function () {
                                        return '<div class="p-2 text-xs text-gray-500">{{ __("Natijalar topilmadi") }}</div>';
                                    },
                                },
                            });
                        });
                }
                document.addEventListener('DOMContentLoaded', function () { initTomSelects(); });
                // Alpine modal'lari ochilganda yangi qo'shilgan select'larni ham aylantirish
                document.addEventListener('alpine:initialized', function () { initTomSelects(); });
                // Globalga qo'shamiz — boshqa skriptlar chaqirishi mumkin
                window.initTomSelects = initTomSelects;
            })();
        </script>
    @endpush
@endonce
