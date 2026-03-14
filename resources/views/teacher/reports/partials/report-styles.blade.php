<style>
    .report-container { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden; }
    .report-filter { padding: 16px 20px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
    .report-filter .filter-item { min-width: 160px; }
    .report-filter .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
    .report-filter .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .report-filter select, .report-filter input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 13px; font-weight: 500; color: #1e293b; box-sizing: border-box; }
    .report-filter .btn-filter { display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; height: 36px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .report-filter .btn-filter:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); }

    .report-header { padding: 10px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
    .report-badge { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; padding: 6px 14px; font-size: 13px; border-radius: 8px; font-weight: 600; }
    .report-badge-danger { background: linear-gradient(135deg, #dc2626, #ef4444); }
    .report-badge-warning { background: linear-gradient(135deg, #d97706, #f59e0b); }
    .report-badge-success { background: linear-gradient(135deg, #059669, #10b981); }

    .report-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .report-table thead { position: sticky; top: 0; z-index: 10; }
    .report-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
    .report-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
    .report-table tbody tr { transition: all 0.15s; }
    .report-table tbody tr:nth-child(even) { background: #f8fafc; }
    .report-table tbody tr:nth-child(odd) { background: #fff; }
    .report-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
    .report-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }
    .report-table .empty-row td { padding: 40px; text-align: center; color: #94a3b8; font-size: 14px; }

    .student-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; flex-shrink: 0; }
    .student-avatar-placeholder { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #cbd5e1, #94a3b8); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 12px; font-weight: 700; color: #fff; }
    .student-name-cell { display: flex; align-items: center; gap: 8px; }
    .student-name-cell a { color: #1e40af; font-weight: 700; text-decoration: none; }
    .student-name-cell a:hover { color: #2b5ea7; text-decoration: underline; }

    .badge-sm { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .badge-yellow { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
    .badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; }
</style>
