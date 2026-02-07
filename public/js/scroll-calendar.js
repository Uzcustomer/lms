/**
 * ScrollCalendar — Windows 10 uslubidagi uzluksiz scroll kalendar
 *
 * Ishlatish:
 *   <link rel="stylesheet" href="/css/scroll-calendar.css">
 *   <script src="/js/scroll-calendar.js"></script>
 *
 *   <input type="text" id="my_date">
 *   <script> new ScrollCalendar('my_date'); </script>
 *
 * Xususiyatlari:
 *   - Hafta-hafta uzluksiz scroll (har notch = yarim qator)
 *   - Dushanba-Yakshanba tartibi, Yakshanba qizil
 *   - O'zbek tilidagi oy/kun nomlari
 *   - dd.mm.yyyy formatda ko'rsatish, Y-m-d serverga yuborish
 *   - Oy boshi (1-sana) da kichik oy label
 *   - Bugungi kun ajratilgan
 *   - x tugmasi bilan tozalash
 *   - Nav tugmalari bilan 4 hafta sakrash
 *   - Escape / tashqariga bosish bilan yopiladi
 */
(function() {
    'use strict';

    var MONTHS = ["Yanvar","Fevral","Mart","Aprel","May","Iyun","Iyul","Avgust","Sentabr","Oktabr","Noyabr","Dekabr"];
    var DAYS = ["Du","Se","Cho","Pa","Ju","Sha","Ya"];
    var ROW_H = 34;
    var SCROLL_STEP = 17;
    var VISIBLE_ROWS = 6;

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toYmd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function toDmy(d) { return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear(); }
    function sameDay(a, b) { return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    function getMonday(d) {
        var dt = new Date(d);
        dt.setHours(0, 0, 0, 0);
        var day = dt.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        dt.setDate(dt.getDate() + diff);
        return dt;
    }

    function ScrollCalendar(inputId, options) {
        this.input = document.getElementById(inputId);
        if (!this.input) return;
        this.options = options || {};
        this.selected = null;
        this.today = new Date();
        this.today.setHours(0, 0, 0, 0);
        this.weeks = [];
        this.isOpen = false;
        this._build();
        this._gen();
        this._render();
        this._bind();
    }

    var P = ScrollCalendar.prototype;

    P._build = function() {
        var wrap = document.createElement('div');
        wrap.className = 'sc-wrap';
        this.input.parentNode.insertBefore(wrap, this.input);

        this.display = document.createElement('input');
        this.display.type = 'text';
        this.display.className = 'date-input';
        this.display.placeholder = this.options.placeholder || 'kk.oo.yyyy';
        this.display.readOnly = true;
        this.display.style.cursor = 'pointer';
        wrap.appendChild(this.display);

        this.clearBtn = document.createElement('span');
        this.clearBtn.className = 'sc-clear';
        this.clearBtn.innerHTML = '&times;';
        this.clearBtn.style.display = 'none';
        wrap.appendChild(this.clearBtn);

        this.input.type = 'hidden';
        wrap.appendChild(this.input);

        this.dd = document.createElement('div');
        this.dd.className = 'sc-dropdown';

        // Header
        var hdr = document.createElement('div');
        hdr.className = 'sc-header';
        this.prevBtn = document.createElement('span');
        this.prevBtn.className = 'sc-nav';
        this.prevBtn.innerHTML = '&#9650;';
        this.monthLabel = document.createElement('span');
        this.monthLabel.className = 'sc-month';
        this.nextBtn = document.createElement('span');
        this.nextBtn.className = 'sc-nav';
        this.nextBtn.innerHTML = '&#9660;';
        var navBox = document.createElement('span');
        navBox.className = 'sc-nav-box';
        navBox.appendChild(this.prevBtn);
        navBox.appendChild(this.nextBtn);
        hdr.appendChild(this.monthLabel);
        hdr.appendChild(navBox);

        // Weekday bar
        var wbar = document.createElement('div');
        wbar.className = 'sc-wdays';
        for (var i = 0; i < 7; i++) {
            var s = document.createElement('span');
            s.textContent = DAYS[i];
            if (i === 6) s.classList.add('sc-sun');
            wbar.appendChild(s);
        }

        // Scrollable body
        this.body = document.createElement('div');
        this.body.className = 'sc-body';

        this.dd.appendChild(hdr);
        this.dd.appendChild(wbar);
        this.dd.appendChild(this.body);
        wrap.appendChild(this.dd);
        this.wrap = wrap;
    };

    P._gen = function() {
        var monthsBefore = this.options.monthsBefore || 6;
        var monthsAfter = this.options.monthsAfter || 18;
        var start = new Date(this.today.getFullYear(), this.today.getMonth() - monthsBefore, 1);
        var end = new Date(this.today.getFullYear(), this.today.getMonth() + monthsAfter, 0);
        var mon = getMonday(start);
        this.weeks = [];
        while (mon <= end) {
            var wk = [];
            for (var i = 0; i < 7; i++) {
                var d = new Date(mon);
                d.setDate(d.getDate() + i);
                wk.push(d);
            }
            this.weeks.push(wk);
            mon = new Date(mon);
            mon.setDate(mon.getDate() + 7);
        }
    };

    P._render = function() {
        this.body.innerHTML = '';
        for (var w = 0; w < this.weeks.length; w++) {
            var row = document.createElement('div');
            row.className = 'sc-week';
            for (var d = 0; d < 7; d++) {
                var date = this.weeks[w][d];
                var cell = document.createElement('span');
                cell.className = 'sc-day';
                cell.textContent = date.getDate();
                cell._date = date;
                if (d === 6) cell.classList.add('sc-sun');
                if (sameDay(date, this.today)) cell.classList.add('sc-today');
                if (sameDay(date, this.selected)) cell.classList.add('sc-selected');
                if (date.getDate() === 1) {
                    cell.setAttribute('data-month', MONTHS[date.getMonth()].substring(0, 3));
                    cell.classList.add('sc-month-start');
                }
                row.appendChild(cell);
            }
            this.body.appendChild(row);
        }
    };

    P._bind = function() {
        var self = this;

        this.display.addEventListener('click', function(e) {
            e.stopPropagation();
            if (self.isOpen) self.close(); else self.open();
        });

        this.clearBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            self.clear();
        });

        this.prevBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            self.body.scrollTop -= ROW_H * 4;
        });

        this.nextBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            self.body.scrollTop += ROW_H * 4;
        });

        this.body.addEventListener('wheel', function(e) {
            e.preventDefault();
            self.body.scrollTop += (e.deltaY > 0 ? 1 : -1) * SCROLL_STEP;
        }, { passive: false });

        this.body.addEventListener('scroll', function() {
            self._updateHeader();
        });

        this.body.addEventListener('click', function(e) {
            var t = e.target;
            if (t.classList.contains('sc-day') && t._date) self._select(t._date);
        });

        document.addEventListener('click', function(e) {
            if (self.isOpen && !self.wrap.contains(e.target)) self.close();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.isOpen) self.close();
        });
    };

    P.open = function() {
        // Barcha boshqa ochiq kalendarlarni yopish
        document.querySelectorAll('.sc-dropdown').forEach(function(el) {
            el.style.display = 'none';
        });
        this.dd.style.display = 'block';
        this.isOpen = true;
        this._scrollToDate(this.selected || this.today);
        this._updateHeader();
    };

    P.close = function() {
        this.dd.style.display = 'none';
        this.isOpen = false;
    };

    P.clear = function() {
        this.selected = null;
        this.input.value = '';
        this.display.value = '';
        this.clearBtn.style.display = 'none';
        this._refreshCells();
        if (this.options.onChange) this.options.onChange(null);
    };

    P._select = function(d) {
        this.selected = d;
        this.input.value = toYmd(d);
        this.display.value = toDmy(d);
        this.clearBtn.style.display = 'block';
        this._refreshCells();
        this.close();
        if (this.options.onChange) this.options.onChange(d);
    };

    P._refreshCells = function() {
        var cells = this.body.querySelectorAll('.sc-day');
        var sel = this.selected;
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.toggle('sc-selected', sameDay(cells[i]._date, sel));
        }
    };

    P._scrollToDate = function(d) {
        for (var w = 0; w < this.weeks.length; w++) {
            if (this.weeks[w][0] <= d && this.weeks[w][6] >= d) {
                this.body.scrollTop = w * ROW_H - ROW_H * 2;
                return;
            }
        }
    };

    P._updateHeader = function() {
        var midY = this.body.scrollTop + (VISIBLE_ROWS * ROW_H) / 2;
        var idx = Math.max(0, Math.min(Math.floor(midY / ROW_H), this.weeks.length - 1));
        var thu = this.weeks[idx][3];
        this.monthLabel.textContent = MONTHS[thu.getMonth()] + ' ' + thu.getFullYear();
    };

    /** Dasturiy ravishda sanani o'rnatish */
    P.setValue = function(dateStr) {
        if (!dateStr) { this.clear(); return; }
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            d.setHours(0, 0, 0, 0);
            this.selected = d;
            this.input.value = toYmd(d);
            this.display.value = toDmy(d);
            this.clearBtn.style.display = 'block';
            this._refreshCells();
        }
    };

    /** Tanlangan sanani Y-m-d formatda olish */
    P.getValue = function() {
        return this.input.value || null;
    };

    // Global ga chiqarish
    window.ScrollCalendar = ScrollCalendar;
})();
