{{--
    Asosiy jurnal ko'rinishidagi jadval uslublari (journal-table, badge, text-cell).
    admin/journal/index.blade.php dagi uslublardan ajratib olingan — qayta o'qish
    jurnali va boshqa ro'yxatlar bir xil ko'rinishda bo'lishi uchun.
--}}
@once
    <style>
        .journal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .journal-table thead tr {
            background: linear-gradient(135deg, #e8edf5 0%, #dbe4ef 50%, #d1d9e6 100%);
        }
        .journal-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 2px solid #cbd5e1;
        }
        .journal-table th.th-num { padding: 14px 12px 14px 16px; width: 44px; }
        .journal-table tbody tr {
            cursor: pointer;
            transition: all 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .journal-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .journal-table tbody tr:hover {
            background-color: #eff6ff !important;
            box-shadow: inset 4px 0 0 #2b5ea7;
        }
        .journal-table td { padding: 10px 12px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.4;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-amber { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-green { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; white-space: nowrap; }
        .badge-purple { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; white-space: nowrap; }
        .badge-red { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; white-space: nowrap; }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
            white-space: nowrap;
        }
        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-subject {
            color: #0f172a;
            font-weight: 700;
            font-size: 12.5px;
            max-width: 280px;
            white-space: normal;
            word-break: break-word;
        }
        .journal-row-link { color: #2b5ea7; font-weight: 600; text-decoration: none; }
        .journal-row-link:hover { text-decoration: underline; }
    </style>
@endonce
