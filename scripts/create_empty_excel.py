import pandas as pd
import os
import subprocess
from datetime import datetime
import time

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

# مسح محتوى ملف اللوج scrap_rta.log في بداية التشغيل
log_path = os.path.join(project_dir, 'storage', 'logs', 'scrap_rta.log')
with open(log_path, 'w') as log_file:
    log_file.write('')

# مسح ملف الحالة scrap_rta.done في بداية التشغيل
status_path = os.path.join(project_dir, 'storage', 'logs', 'scrap_rta.done')
if os.path.exists(status_path):
    os.remove(status_path)

# توجيه كل print إلى ملف اللوج أيضاً
import sys
class Logger(object):
    def __init__(self, log_path):
        self.terminal = sys.stdout
        self.log = open(log_path, "a", buffering=1, encoding='utf-8')
    def write(self, message):
        self.terminal.write(message)
        self.log.write(message)
    def flush(self):
        self.terminal.flush()
        self.log.flush()
sys.stdout = Logger(log_path)
sys.stderr = Logger(log_path)

# قراءة البيانات من violations_details.xlsx (من نفس مجلد السكريبت)
details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')

# التحقق من وجود الملف
if not os.path.exists(details_excel_path):
    print(f"File {details_excel_path} not found.")
    print("No violations details to process. Exiting script.")
    exit(0)
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
    print('No data to process. Skipping Clean.xlsx creation.')
    clean_excel_path = os.path.join(base_dir, 'Clean.xlsx')
    if os.path.exists(clean_excel_path):
        print(f'Using existing Clean.xlsx at {clean_excel_path}')
    else:
        print('No Clean.xlsx file found and no data to create one.')
        # خروج من السكريبت إذا لم تكن هناك بيانات
        print("Exiting script due to no data to process.")
        exit(0)

# حذف جميع البيانات من جدول fines قبل الاستيراد
print("Deleting all data from fines table before import...")
try:
    delete_cmd = [
        "php",
        "artisan",
        "tinker",
        "--execute=App\\Models\\Fine::truncate(); echo 'Fines table truncated.';"
    ]
    delete_result = subprocess.run(delete_cmd, capture_output=True, text=True, timeout=30)
    print(delete_result.stdout)
except Exception as delete_err:
    print("Failed to truncate fines table:", delete_err)

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

# فحص إعدادات قاعدة البيانات قبل الاستيراد
print("\n=== Database Connection Diagnostics ===")

# فحص ملف .env
print("Checking .env file...")
env_path = os.path.join(project_dir, '.env')
if os.path.exists(env_path):
    print(f".env file found at: {env_path}")
    try:
        with open(env_path, 'r') as f:
            env_content = f.read()
            # البحث عن إعدادات قاعدة البيانات
            db_lines = [line for line in env_content.split('\n') if line.startswith('DB_')]
            print("Database settings in .env:")
            for line in db_lines:
                if 'PASSWORD' in line:
                    print(f"  {line.split('=')[0]}=***HIDDEN***")
                else:
                    print(f"  {line}")
    except Exception as env_err:
        print(f"Error reading .env file: {env_err}")
else:
    print(f".env file not found at: {env_path}")

try:
    # فحص إعدادات قاعدة البيانات
    db_config_cmd = ["php", "artisan", "tinker", "--execute='echo \"DB Connection: \" . config(\"database.default\"); echo \"DB Host: \" . config(\"database.connections.mysql.host\"); echo \"DB Port: \" . config(\"database.connections.mysql.port\"); echo \"DB Database: \" . config(\"database.connections.mysql.database\"); echo \"DB Username: \" . config(\"database.connections.mysql.username\");'"]
    db_config_result = subprocess.run(db_config_cmd, capture_output=True, text=True, timeout=30)
    print("Database configuration:", db_config_result.stdout)

    # اختبار الاتصال بقاعدة البيانات
    db_test_cmd = ["php", "artisan", "tinker", "--execute='try { DB::connection()->getPdo(); echo \"Database connection: SUCCESS\"; } catch (Exception \$e) { echo \"Database connection: FAILED - \" . \$e->getMessage(); }'"]
    db_test_result = subprocess.run(db_test_cmd, capture_output=True, text=True, timeout=30)
    print("Database connection test:", db_test_result.stdout)

    # فحص عدد المخالفات الموجودة حالياً
    db_count_cmd = ["php", "artisan", "tinker", "--execute='echo \"Current fines count: \" . App\\Models\\Fine::count();'"]
    db_count_result = subprocess.run(db_count_cmd, capture_output=True, text=True, timeout=30)
    print("Current database state:", db_count_result.stdout)

except Exception as db_err:
    print("Database diagnostics failed:", db_err)

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

    # في نهاية النجاح فقط
    with open(status_path, 'w') as f:
        f.write(str(int(time.time())))

except subprocess.CalledProcessError as e:
    print("Import failed!")
    print("Error code:", e.returncode)
    print("STDOUT:", e.stdout)
    print("STDERR:", e.stderr)

    # محاولة تشغيل الأمر مع معلومات إضافية للتشخيص
    print("\nTrying to get more diagnostic information...")
    try:
        db_check_cmd = ["php", "artisan", "tinker", "--execute='echo \"DB Connection: \" . config(\"database.default\"); echo \"DB Host: \" . config(\"database.connections.mysql.host\"); echo \"DB Database: \" . config(\"database.connections.mysql.database\");'"]
        db_result = subprocess.run(db_check_cmd, capture_output=True, text=True, timeout=30)
        print("Database check result:", db_result.stdout)
    except Exception as db_err:
        print("Database check failed:", db_err)
finally:
    # الاحتفاظ بملفات الإكسل الموجودة فقط
    print("Excel files status:")
    excel_files = [
        os.path.join(base_dir, 'violations.xlsx'),
        os.path.join(base_dir, 'violations_details.xlsx'),
        os.path.join(base_dir, 'Clean.xlsx'),
    ]
    for f in excel_files:
        if os.path.exists(f):
            print(f"Found: {f}")
        else:
            print(f"Not found: {f}")

    # تسجيل وقت آخر مزامنة
    last_sync_path = os.path.join(project_dir, 'storage', 'app', 'last_sync.txt')
    try:
        with open(last_sync_path, 'w') as f:
            f.write(datetime.now().isoformat())
        print(f"Last sync time saved to {last_sync_path}")
    except Exception as e:
        print(f"Failed to write last sync time: {e}")
