#!/usr/bin/env python3
"""
Ikki Excel faylni solishtirish skripti.

Ishlatish:
    python3 excel_diff.py fayl_a.xlsx fayl_b.xlsx --key hemis_id

Natija: diff_natija.xlsx fayli yaratiladi, 3 ta sheet bilan:
    - Faqat_A_da    : A'da bor, B'da yo'q satrlar
    - Faqat_B_da    : B'da bor, A'da yo'q satrlar
    - Ozgarganlar   : Kalit bir xil, lekin boshqa ustunlar farqli
"""

import argparse
import sys
import pandas as pd


def main():
    parser = argparse.ArgumentParser(description="Ikki Excel faylni solishtirish")
    parser.add_argument("file_a", help="Birinchi Excel fayl yo'li (A)")
    parser.add_argument("file_b", help="Ikkinchi Excel fayl yo'li (B)")
    parser.add_argument(
        "--key",
        required=True,
        help="Kalit ustun nomi (masalan: hemis_id). Vergul bilan bir nechta: id,semester",
    )
    parser.add_argument(
        "--sheet-a", default=0, help="A faylidagi sheet nomi yoki indeksi (default: 0)"
    )
    parser.add_argument(
        "--sheet-b", default=0, help="B faylidagi sheet nomi yoki indeksi (default: 0)"
    )
    parser.add_argument(
        "--output", default="diff_natija.xlsx", help="Natija fayl nomi (default: diff_natija.xlsx)"
    )
    args = parser.parse_args()

    keys = [k.strip() for k in args.key.split(",")]

    print(f"[1/4] '{args.file_a}' o'qilmoqda...")
    a = pd.read_excel(args.file_a, sheet_name=args.sheet_a, dtype=str).fillna("")
    print(f"      {len(a)} satr, {len(a.columns)} ustun")

    print(f"[2/4] '{args.file_b}' o'qilmoqda...")
    b = pd.read_excel(args.file_b, sheet_name=args.sheet_b, dtype=str).fillna("")
    print(f"      {len(b)} satr, {len(b.columns)} ustun")

    # Kalit ustun(lar) tekshirish
    for k in keys:
        if k not in a.columns:
            sys.exit(f"XATO: '{k}' ustuni A faylida topilmadi. Mavjud ustunlar: {list(a.columns)}")
        if k not in b.columns:
            sys.exit(f"XATO: '{k}' ustuni B faylida topilmadi. Mavjud ustunlar: {list(b.columns)}")

    print(f"[3/4] Kalit '{','.join(keys)}' bo'yicha solishtirilmoqda...")

    # Kalit kombinatsiyasi
    a_keys = a[keys].agg("|".join, axis=1)
    b_keys = b[keys].agg("|".join, axis=1)

    only_in_a = a[~a_keys.isin(b_keys)].copy()
    only_in_b = b[~b_keys.isin(a_keys)].copy()

    # O'zgarganlar — umumiy kalitlar bo'yicha join va farq topish
    common_cols = [c for c in a.columns if c in b.columns and c not in keys]
    a_common = a[a_keys.isin(b_keys)].set_index(a_keys[a_keys.isin(b_keys)])
    b_common = b[b_keys.isin(a_keys)].set_index(b_keys[b_keys.isin(a_keys)])

    changed_rows = []
    for idx in a_common.index:
        if idx not in b_common.index:
            continue
        row_a = a_common.loc[idx]
        row_b = b_common.loc[idx]
        # Bir xil indeksda bir nechta qator bo'lishi mumkin (duplicate keys)
        if isinstance(row_a, pd.DataFrame):
            row_a = row_a.iloc[0]
        if isinstance(row_b, pd.DataFrame):
            row_b = row_b.iloc[0]
        diffs = {}
        for col in common_cols:
            va = str(row_a.get(col, ""))
            vb = str(row_b.get(col, ""))
            if va != vb:
                diffs[f"{col} (A)"] = va
                diffs[f"{col} (B)"] = vb
        if diffs:
            row = {k: row_a.get(k, "") for k in keys}
            row.update(diffs)
            changed_rows.append(row)

    changed = pd.DataFrame(changed_rows)

    print(f"[4/4] '{args.output}' yozilmoqda...")
    with pd.ExcelWriter(args.output, engine="openpyxl") as writer:
        only_in_a.to_excel(writer, sheet_name="Faqat_A_da", index=False)
        only_in_b.to_excel(writer, sheet_name="Faqat_B_da", index=False)
        if not changed.empty:
            changed.to_excel(writer, sheet_name="Ozgarganlar", index=False)
        else:
            pd.DataFrame([{"natija": "O'zgargan satr topilmadi"}]).to_excel(
                writer, sheet_name="Ozgarganlar", index=False
            )

    print()
    print("=" * 50)
    print(f"TAYYOR — '{args.output}'")
    print(f"  Faqat A'da: {len(only_in_a)} satr")
    print(f"  Faqat B'da: {len(only_in_b)} satr")
    print(f"  O'zgarganlar: {len(changed)} satr")
    print("=" * 50)


if __name__ == "__main__":
    main()
