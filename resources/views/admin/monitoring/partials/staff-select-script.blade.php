@once
@push('scripts')
    <script>
        (function () {
            function initStaffSelects() {
                if (!window.jQuery || !jQuery.fn.select2) { return; }
                jQuery('.mon-staff-select').each(function () {
                    var $el = jQuery(this);
                    if ($el.data('select2')) { return; }
                    $el.select2({
                        width: '100%',
                        allowClear: true,
                        placeholder: $el.data('placeholder') || 'Xodim qidirish (kamida 2 harf)',
                        minimumInputLength: 2,
                        ajax: {
                            url: @json(route('admin.monitoring.staff-search')),
                            dataType: 'json',
                            delay: 300,
                            data: function (params) { return { q: params.term }; },
                            processResults: function (data) { return data; }
                        }
                    });
                });
            }
            function load(src, cb) {
                var s = document.createElement('script');
                s.src = src; s.onload = cb; document.head.appendChild(s);
            }
            function boot() {
                if (!window.jQuery) {
                    load('https://code.jquery.com/jquery-3.6.0.min.js', boot);
                } else if (!jQuery.fn.select2) {
                    load('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', initStaffSelects);
                } else {
                    initStaffSelects();
                }
            }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
        })();
    </script>
@endpush
@endonce
