#!/usr/bin/env python3
"""
Vedomost birlashtirish — tkinter ko'ruvchi.

MySQL/MariaDB bazasiga ulanib, guruh/fan nomlari formatini va o'zak guruh × o'zak
fan birlashtirish ko'rinishini jadvalda ko'rsatadi. Hech narsani o'zgartirmaydi —
faqat SELECT (read-only).

Ishga tushirish:
    pip install pymysql            # bir marta
    python3 tools/vedomost_merge_viewer.py

Ulanish ma'lumotlari .env dan avtomatik o'qiladi (DB_HOST, DB_PORT, DB_DATABASE,
DB_USERNAME, DB_PASSWORD), lekin oynadagi maydonlardan o'zgartirsa ham bo'ladi.
"""

import csv
import os
import tkinter as tk
from tkinter import messagebox, ttk, filedialog

try:
    import pymysql
except ImportError:
    pymysql = None

# O'zak qoidalar (PHP VedomostMergeService bilan AYNAN bir xil).
# REGEXP_REPLACE iboralarini bir joyda yig'amiz (SQL ichida takror ishlatamiz).
RG = (
    "REGEXP_REPLACE(REGEXP_REPLACE(group_name, ' *\\\\([A-Za-zА-Яа-яёЁ]\\\\) *$', ''), "
    "'(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', '')"
)
RS = "REGEXP_REPLACE(subject_name, ' *\\\\([A-Za-zА-Яа-яёЁ0-9]\\\\) *$', '')"

QUERIES = {
    "1. Barcha guruhlar + o'zagi": f"""
        SELECT group_name,
               {RG} AS root_group,
               COUNT(*) AS yozuvlar
        FROM vedomost_submissions
        GROUP BY group_name
        ORDER BY group_name
    """,
    "2. Qo'shimchali guruhlar (o'zgargan)": f"""
        SELECT DISTINCT group_name, {RG} AS root_group
        FROM vedomost_submissions
        WHERE group_name <> {RG}
        ORDER BY group_name
    """,
    "3. Qo'shimchali fanlar (o'zgargan)": f"""
        SELECT DISTINCT subject_name, {RS} AS root_subject
        FROM vedomost_submissions
        WHERE subject_name <> {RS}
        ORDER BY subject_name
    """,
    "4. BIRLASHTIRISH ko'rinishi (o'qituvchilar bilan)": f"""
        SELECT education_year, semester_code, specialty_name, closing_form,
               {RG} AS root_group,
               {RS} AS root_subject,
               COUNT(*) AS guruhcha_soni,
               GROUP_CONCAT(DISTINCT group_name   ORDER BY group_name   SEPARATOR ', ') AS guruhchalar,
               GROUP_CONCAT(DISTINCT subject_name ORDER BY subject_name SEPARATOR ', ') AS fan_variantlari,
               GROUP_CONCAT(DISTINCT teacher_name ORDER BY teacher_name SEPARATOR ', ') AS oqituvchilar
        FROM vedomost_submissions
        GROUP BY education_year, semester_code, specialty_name, closing_form, root_group, root_subject
        HAVING guruhcha_soni > 1
        ORDER BY guruhcha_soni DESC, root_group, root_subject
    """,
    "5. Yig'ma son (oldin/keyin)": f"""
        SELECT COUNT(*) AS yozuvlar_jami,
               COUNT(DISTINCT CONCAT_WS('|', education_year, semester_code,
                     specialty_name, closing_form, {RG}, {RS})) AS ozak_vedomostlar
        FROM vedomost_submissions
    """,
    "6. NOYOB guruh qo'shimchalari": f"""
        SELECT SUBSTRING(group_name, CHAR_LENGTH({RG}) + 1) AS guruh_qoshimcha,
               COUNT(*)                   AS nechta_yozuv,
               COUNT(DISTINCT group_name) AS nechta_guruh
        FROM vedomost_submissions
        WHERE group_name <> {RG}
        GROUP BY guruh_qoshimcha
        ORDER BY nechta_yozuv DESC
    """,
    "7. NOYOB fan qo'shimchalari": f"""
        SELECT SUBSTRING(subject_name, CHAR_LENGTH({RS}) + 1) AS fan_qoshimcha,
               COUNT(*)                     AS nechta_yozuv,
               COUNT(DISTINCT subject_name) AS nechta_fan
        FROM vedomost_submissions
        WHERE subject_name <> {RS}
        GROUP BY fan_qoshimcha
        ORDER BY nechta_yozuv DESC
    """,
    "8. GURUH quyrug'i (til tegi + harf)": """
        SELECT REGEXP_REPLACE(group_name, '^.*?[0-9]+-[0-9]+', '') AS guruh_quyruq,
               COUNT(*)                   AS nechta_yozuv,
               COUNT(DISTINCT group_name) AS nechta_guruh
        FROM vedomost_submissions
        GROUP BY guruh_quyruq
        ORDER BY nechta_yozuv DESC
    """,
    "9. FAN oxirgi qavsi (har qanday)": r"""
        SELECT REGEXP_SUBSTR(subject_name, '\([^)]*\) *$') AS fan_oxirgi_qavs,
               COUNT(*)                     AS nechta_yozuv,
               COUNT(DISTINCT subject_name) AS nechta_fan
        FROM vedomost_submissions
        WHERE subject_name REGEXP '\([^)]*\) *$'
        GROUP BY fan_oxirgi_qavs
        ORDER BY nechta_yozuv DESC
    """,
}


def parse_env(path=".env"):
    """.env faylidan DB_* qiymatlarini o'qiydi."""
    env = {}
    if not os.path.exists(path):
        # skript tools/ ichidan ham ishlashi uchun ota-papkani sinab ko'ramiz
        alt = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), ".env")
        path = alt if os.path.exists(alt) else path
    try:
        with open(path, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                env[k.strip()] = v.strip().strip('"').strip("'")
    except OSError:
        pass
    return env


class App:
    def __init__(self, root):
        self.root = root
        root.title("Vedomost birlashtirish — ko'ruvchi")
        root.geometry("1200x680")

        env = parse_env()
        top = ttk.Frame(root, padding=8)
        top.pack(fill="x")

        self.vars = {}
        fields = [
            ("Host", "DB_HOST", env.get("DB_HOST", "127.0.0.1"), 14),
            ("Port", "DB_PORT", env.get("DB_PORT", "3306"), 6),
            ("Baza", "DB_DATABASE", env.get("DB_DATABASE", "laravel"), 14),
            ("User", "DB_USERNAME", env.get("DB_USERNAME", "root"), 12),
            ("Parol", "DB_PASSWORD", env.get("DB_PASSWORD", ""), 14),
        ]
        for i, (label, key, val, width) in enumerate(fields):
            ttk.Label(top, text=label).grid(row=0, column=i * 2, padx=(6, 2), sticky="e")
            var = tk.StringVar(value=val)
            show = "*" if key == "DB_PASSWORD" else ""
            ttk.Entry(top, textvariable=var, width=width, show=show).grid(row=0, column=i * 2 + 1, padx=(0, 6))
            self.vars[key] = var

        sel = ttk.Frame(root, padding=(8, 0))
        sel.pack(fill="x")
        ttk.Label(sel, text="So'rov:").pack(side="left", padx=(6, 4))
        self.query_var = tk.StringVar(value=list(QUERIES.keys())[3])
        ttk.Combobox(sel, textvariable=self.query_var, values=list(QUERIES.keys()),
                     state="readonly", width=46).pack(side="left", padx=4)
        ttk.Button(sel, text="▶ Ishga tushirish", command=self.run).pack(side="left", padx=8)
        ttk.Button(sel, text="⬇ CSV", command=self.export_csv).pack(side="left")
        self.status = ttk.Label(sel, text="", foreground="#555")
        self.status.pack(side="left", padx=12)

        body = ttk.Frame(root, padding=8)
        body.pack(fill="both", expand=True)
        self.tree = ttk.Treeview(body, show="headings")
        ysb = ttk.Scrollbar(body, orient="vertical", command=self.tree.yview)
        xsb = ttk.Scrollbar(body, orient="horizontal", command=self.tree.xview)
        self.tree.configure(yscrollcommand=ysb.set, xscrollcommand=xsb.set)
        self.tree.grid(row=0, column=0, sticky="nsew")
        ysb.grid(row=0, column=1, sticky="ns")
        xsb.grid(row=1, column=0, sticky="ew")
        body.rowconfigure(0, weight=1)
        body.columnconfigure(0, weight=1)

        self._rows = []
        self._cols = []

    def connect(self):
        if pymysql is None:
            messagebox.showerror("pymysql yo'q", "Avval o'rnating:\n\n    pip install pymysql")
            return None
        try:
            return pymysql.connect(
                host=self.vars["DB_HOST"].get(),
                port=int(self.vars["DB_PORT"].get() or 3306),
                user=self.vars["DB_USERNAME"].get(),
                password=self.vars["DB_PASSWORD"].get(),
                database=self.vars["DB_DATABASE"].get(),
                charset="utf8mb4",
                cursorclass=pymysql.cursors.Cursor,
            )
        except Exception as e:  # noqa: BLE001
            messagebox.showerror("Ulanish xatosi", str(e))
            return None

    def run(self):
        conn = self.connect()
        if not conn:
            return
        sql = QUERIES[self.query_var.get()]
        try:
            with conn.cursor() as cur:
                cur.execute(sql)
                cols = [d[0] for d in cur.description]
                rows = cur.fetchall()
        except Exception as e:  # noqa: BLE001
            messagebox.showerror("So'rov xatosi", str(e))
            return
        finally:
            conn.close()
        self.fill(cols, rows)
        self.status.config(text=f"{len(rows)} qator")

    def fill(self, cols, rows):
        self._cols, self._rows = cols, rows
        self.tree.delete(*self.tree.get_children())
        self.tree["columns"] = cols
        for c in cols:
            self.tree.heading(c, text=c)
            self.tree.column(c, width=max(90, min(380, len(c) * 12)), anchor="w", stretch=True)
        for r in rows:
            self.tree.insert("", "end", values=["" if v is None else v for v in r])

    def export_csv(self):
        if not self._rows:
            messagebox.showinfo("Bo'sh", "Avval so'rovni ishga tushiring.")
            return
        path = filedialog.asksaveasfilename(defaultextension=".csv",
                                            filetypes=[("CSV", "*.csv")])
        if not path:
            return
        with open(path, "w", newline="", encoding="utf-8-sig") as f:
            w = csv.writer(f)
            w.writerow(self._cols)
            w.writerows(self._rows)
        self.status.config(text=f"Saqlandi: {os.path.basename(path)}")


if __name__ == "__main__":
    root = tk.Tk()
    App(root)
    root.mainloop()
