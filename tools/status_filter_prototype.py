"""
Talabalar ro'yxati uchun "Holati" (status) filtrining tkinter prototipi.

Maqsad: web LMS'ga tegmasdan turib filtr mantig'ini sinab ko'rish.
  - Sahifa birinchi ochilganda -> faqat O'qimoqda (student_status_code = 11)
  - "Barchasi" tanlansa -> hamma holat
  - Boshqa holatni tanlash mumkin (Bitirgan, Chetlashtirilgan, ...)

Web kodidagi mantiqning aynan o'zi: default = '11', "Barchasi" = '' (filtrsiz).
"""
import sys
import tkinter as tk
from tkinter import ttk

# --- Namuna ma'lumotlar (screenshotdagi Muhamedov + bir nechta qo'shimcha) ---
# (kod, nom) -> HEMIS student_status_code / student_status_name
STATUSES = [
    ("11", "O'qimoqda"),
    ("16", "Bitirgan"),
    ("60", "Chetlashtirilgan"),
    ("13", "Akademik ta'tilda"),
]
STATUS_NAME = dict(STATUSES)

STUDENTS = [
    # fish, talaba_id, talim_turi, holat_kodi
    ("MUHAMEDOV TOLIBJON XASAN O'G'LI", "368191100107", "Bakalavr",   "16"),  # bitirgan
    ("MUHAMEDOV TOLIBJON XASAN O'G'LI", "368251300073", "Ordinatura", "11"),  # o'qimoqda
    ("ABDULLAYEV SARDOR ANVAR O'G'LI",  "368201100201", "Bakalavr",   "11"),
    ("KARIMOVA NODIRA AKMAL QIZI",      "368181100345", "Bakalavr",   "60"),  # chetlashtirilgan
    ("YUSUPOV JAVOHIR BAXTIYOR O'G'LI", "368191100512", "Magistr",    "13"),  # akademik ta'til
    ("TOSHEVA MADINA OYBEK QIZI",       "368211100777", "Bakalavr",   "11"),
]


def filter_students(selected_code):
    """Web controller mantig'ining aynan nusxasi.

    selected_code:
        '11' (default) -> faqat o'qimoqda
        ''   (Barchasi) -> filtrsiz
        boshqa kod      -> shu kod bo'yicha
    """
    if selected_code == "":
        return list(STUDENTS)
    return [s for s in STUDENTS if s[3] == selected_code]


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("LMS — Talabalar (Holat filtri prototipi)")
        self.geometry("900x420")
        self.configure(bg="#eef2f7")

        # Combobox qiymatlari: Barchasi + holatlar
        self.options = [("Barchasi", "")] + [(name, code) for code, name in STATUSES]
        self.labels = [o[0] for o in self.options]

        top = tk.Frame(self, bg="#eef2f7")
        top.pack(fill="x", padx=16, pady=(14, 6))

        tk.Label(top, text="HOLATI", bg="#eef2f7", fg="#475569",
                 font=("Arial", 9, "bold")).pack(side="left")
        self.combo = ttk.Combobox(top, values=self.labels, state="readonly", width=24)
        # DEFAULT = O'qimoqda
        self.combo.current(self.labels.index("O'qimoqda"))
        self.combo.pack(side="left", padx=8)
        self.combo.bind("<<ComboboxSelected>>", lambda e: self.refresh())

        self.count_lbl = tk.Label(top, text="", bg="#2b5ea7", fg="white",
                                  font=("Arial", 10, "bold"), padx=12, pady=4)
        self.count_lbl.pack(side="right")

        cols = ("fish", "id", "turi", "holat")
        self.tree = ttk.Treeview(self, columns=cols, show="headings", height=12)
        for c, t, w in [("fish", "F.I.SH", 360), ("id", "Talaba ID", 140),
                        ("turi", "Ta'lim turi", 140), ("holat", "Holati", 180)]:
            self.tree.heading(c, text=t)
            self.tree.column(c, width=w, anchor="w")
        self.tree.pack(fill="both", expand=True, padx=16, pady=(4, 16))

        self.refresh()

    def selected_code(self):
        return self.options[self.combo.current()][1]

    def refresh(self):
        code = self.selected_code()
        rows = filter_students(code)
        self.tree.delete(*self.tree.get_children())
        for fish, sid, turi, hcode in rows:
            self.tree.insert("", "end", values=(fish, sid, turi, STATUS_NAME[hcode]))
        self.count_lbl.config(text=f"Jami: {len(rows)} ta talaba")


if __name__ == "__main__":
    app = App()
    # Headless rejimda: bir lahzadan keyin yopiladi (screenshot uchun)
    if "--auto-close" in sys.argv:
        ms = 1500
        if "--select" in sys.argv:
            val = sys.argv[sys.argv.index("--select") + 1]
            app.combo.set(val)
            app.refresh()
        app.after(ms, app.destroy)
    app.mainloop()
