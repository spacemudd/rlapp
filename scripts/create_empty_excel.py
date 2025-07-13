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

# تغيير المجلد إلى مجلد المشروع (أعلى مستوى)
project_dir = os.path.dirname(base_dir)
os.chdir(project_dir)
print(f"Changed working directory to: {project_dir}")

# قراءة البيانات من violations_details.xlsx (من نفس مجلد السكريبت)
details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')

# التحقق من وجود الملف
if not os.path.exists(details_excel_path):
    print(f"File {details_excel_path} not found. Creating empty DataFrame.")
    # إنشاء DataFrame فارغ مع العمود المطلوب
    df = pd.DataFrame(columns=['Details'])
else:
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
if clean_data:
    clean_df = pd.DataFrame(clean_data, columns=columns_needed)
    clean_excel_path = os.path.join(base_dir, 'Clean.xlsx')
    clean_df.to_excel(clean_excel_path, index=False)
    print(f'Clean.xlsx created at {clean_excel_path}')
else:
    print('No data to process. Creating empty Clean.xlsx file.')
    clean_df = pd.DataFrame(columns=columns_needed)
    clean_excel_path = os.path.join(base_dir, 'Clean.xlsx')
    clean_df.to_excel(clean_excel_path, index=False)
    print(f'Empty Clean.xlsx created at {clean_excel_path}')

# استيراد فقط المخالفات الجديدة من Clean.xlsx
# استخدام المسار النسبي بدلاً من المسار المطلق
artisan_cmd = [
    "php",
    "artisan",
    "import:fines",
    clean_excel_path
]

print(f"Running command: {' '.join(artisan_cmd)}")
print(f"Clean.xlsx path: {clean_excel_path}")
print(f"File exists: {os.path.exists(clean_excel_path)}")
print(f"Number of rows in Clean.xlsx: {len(clean_data)}")

# طباعة أول 3 صفوف للتحقق
if clean_data:
    print("\nFirst 3 rows of data to be imported:")
    for i, row in enumerate(clean_data[:3]):
        print(f"Row {i+1}: {row}")

try:
    result = subprocess.run(artisan_cmd, capture_output=True, text=True, check=True)
    print("Import finished successfully!")
    print("STDOUT:", result.stdout)
    print("STDERR:", result.stderr)

    # تحليل الرسالة لمعرفة عدد المخالفات الجديدة
    if "لا توجد مخالفات جديدة" in result.stdout or "No new fines" in result.stdout:
        print("No new fines were added.")
    elif "تم إضافة" in result.stdout:
        import re
        match = re.search(r"تم إضافة (\d+) مخالفة", result.stdout)
        if match:
            print(f"{match.group(1)} fines have been added to database!")
        else:
            print("Could not determine number of fines added from output.")
    else:
        print("Import completed but could not determine result from output.")

    # التحقق من قاعدة البيانات بعد الاستيراد
    print("\nVerifying database import...")
    try:
        verify_cmd = ["php", "artisan", "tinker", "--execute='echo \"Total fines in database: \" . App\\Models\\Fine::count();'"]
        verify_result = subprocess.run(verify_cmd, capture_output=True, text=True, timeout=30)
        print("Database verification result:", verify_result.stdout)
    except Exception as verify_err:
        print("Database verification failed:", verify_err)

except subprocess.CalledProcessError as e:
    print("Import failed!")
    print("Error code:", e.returncode)
    print("STDOUT:", e.stdout)
    print("STDERR:", e.stderr)

    # محاولة تشغيل الأمر مع معلومات إضافية للتشخيص
    print("\nTrying to get more diagnostic information...")
    try:
        # فحص حالة قاعدة البيانات
        db_check_cmd = ["php", "artisan", "tinker", "--execute='echo \"DB Connection: \" . config(\"database.default\"); echo \"DB Host: \" . config(\"database.connections.mysql.host\"); echo \"DB Database: \" . config(\"database.connections.mysql.database\");'"]
        db_result = subprocess.run(db_check_cmd, capture_output=True, text=True, timeout=30)
        print("Database check result:", db_result.stdout)
    except Exception as db_err:
        print("Database check failed:", db_err)
finally:
    # الاحتفاظ بملفات الإكسل كما هي بدون حذف
    print("Excel files have been preserved:")
    excel_files = [
        os.path.join(base_dir, 'violations.xlsx'),
        os.path.join(base_dir, 'violations_details.xlsx'),
        os.path.join(base_dir, 'Clean.xlsx'),
    ]
    for f in excel_files:
        if os.path.exists(f):
            print(f"Preserved: {f}")
        else:
            print(f"Not found: {f}")
            # إنشاء ملف فارغ إذا لم يكن موجوداً
            if f.endswith('violations.xlsx'):
                empty_df = pd.DataFrame(columns=['Violation'])
                empty_df.to_excel(f, index=False)
                print(f"Created empty: {f}")
            elif f.endswith('violations_details.xlsx'):
                empty_df = pd.DataFrame(columns=['Details'])
                empty_df.to_excel(f, index=False)
                print(f"Created empty: {f}")
            elif f.endswith('Clean.xlsx'):
                empty_df = pd.DataFrame(columns=columns_needed)
                empty_df.to_excel(f, index=False)
                print(f"Created empty: {f}")
