import pandas as pd
import os
import subprocess

# الأعمدة المطلوبة
columns_needed = [
    'Car Name', 'Plate Code', 'Plate Number', 'Date and Time', 'Location',
    'Source', 'Amount', 'Fine Number', 'Details', 'Dispute'
]

# احصل على مسار مجلد السكريبت
base_dir = os.path.dirname(os.path.abspath(__file__))

# قراءة البيانات من violations_details.xlsx (من نفس مجلد السكريبت)
details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')
df = pd.read_excel(details_excel_path)

# قائمة لتجميع النتائج
clean_data = []

progress_file = os.path.join(base_dir, 'progress.txt')
total = len(df)
for idx, row in enumerate(df.iterrows()):
    series = row[1]
    details = series['Details'] if 'Details' in series else str(series[0])
    lines = [l.strip() for l in details.split('\n') if l.strip()]
    car_name = ''
    plate_code = ''
    plate_number = ''
    date_time = ''
    location = ''
    source = ''
    amount = ''
    fine_number = ''
    details_field = ''
    dispute = ''
    for i, line in enumerate(lines):
        if i == 1:
            car_name = line
        if i == 2:
            plate_code = line
        if i == 3:
            plate_number = line
        if line.startswith('Date and Time of Issuing The Fine:'):
            date_time = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Location:'):
            location = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Source:'):
            source = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Amount:'):
            amount = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Fine Number:'):
            fine_number = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Details:'):
            details_field = lines[i+1] if i+1 < len(lines) else ''
        if line.startswith('Dispute:'):
            dispute = lines[i+1] if i+1 < len(lines) else ''
    clean_data.append({
        'Car Name': car_name,
        'Plate Code': plate_code,
        'Plate Number': plate_number,
        'Date and Time': date_time,
        'Location': location,
        'Source': source,
        'Amount': amount,
        'Fine Number': fine_number,
        'Details': details_field,
        'Dispute': dispute,
    })
    # تحديث نسبة التقدم كل 1% أو في آخر صف
    if idx % max(1, total // 100) == 0 or idx == total - 1:
        percent = int((idx + 1) / total * 100)
        with open(progress_file, 'w') as pf:
            pf.write(str(percent))

# إنشاء DataFrame جديد وحفظه في نفس مجلد السكريبت
clean_df = pd.DataFrame(clean_data, columns=columns_needed)
clean_excel_path = os.path.join(base_dir, 'Clean.xlsx')
clean_df.to_excel(clean_excel_path, index=False)
print(f'Clean.xlsx created at {clean_excel_path}')

# استيراد فقط المخالفات الجديدة من Clean.xlsx
artisan_cmd = [
    "php",
    "/Applications/XAMPP/xamppfiles/htdocs/rlapp/artisan",
    "import:fines",
    clean_excel_path
]
try:
    result = subprocess.run(artisan_cmd, capture_output=True, text=True, check=True)
    print("Import finished successfully!")
    print(result.stdout)
    # تحليل الرسالة لمعرفة عدد المخالفات الجديدة
    if "لا توجد مخالفات جديدة" in result.stdout or "No new fines" in result.stdout:
        print("No new fines were added.")
    else:
        import re
        match = re.search(r"تم إضافة (\d+) مخالفة جديدة", result.stdout)
        if match:
            print(f"{match.group(1)} new fines have been added!")
except subprocess.CalledProcessError as e:
    print("Import failed!")
    print(e.stderr)
finally:
    # حذف الملفات بعد انتهاء الاستيراد سواء وُجدت مخالفات جديدة أم لا
    files_to_delete = [
        os.path.join(base_dir, 'violations.xlsx'),
        os.path.join(base_dir, 'violations_details.xlsx'),
        os.path.join(base_dir, 'Clean.xlsx'),
    ]
    for f in files_to_delete:
        try:
            if os.path.exists(f):
                os.remove(f)
                print(f"Deleted: {f}")
        except Exception as del_err:
            print(f"Failed to delete {f}: {del_err}")
