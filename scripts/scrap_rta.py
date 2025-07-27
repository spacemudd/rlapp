from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
import time
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException
import pandas as pd
import re
import subprocess
import os

# Enter file number here
file_number = "51564893"  # You can change it to any file numbereaaaa

# مسح ملفات الإكسل الموجودة في بداية السكريبت
print("=== Cleaning up existing Excel files ===")
base_dir = os.path.dirname(os.path.abspath(__file__))
excel_files_to_clean = [
    'violations.xlsx',
    'violations_details.xlsx',
    'Clean.xlsx'
]

for excel_file in excel_files_to_clean:
    file_path = os.path.join(base_dir, excel_file)
    if os.path.exists(file_path):
        try:
            os.remove(file_path)
            print(f"Deleted: {excel_file}")
        except Exception as e:
            print(f"Failed to delete {excel_file}: {e}")
    else:
        print(f"Not found: {excel_file}")

print("=== Excel files cleanup completed ===")
print()

# Set up the browser
options = webdriver.ChromeOptions()
options.add_argument('--headless')  # Run without graphical interface (automated)
options.add_argument('--no-sandbox')
options.add_argument('--disable-dev-shm-usage')
options.add_argument('--disable-blink-features=AutomationControlled')
options.add_argument('--disable-web-security')
options.add_argument('--allow-running-insecure-content')
options.add_argument('--disable-features=VizDisplayCompositor')
options.add_argument('--disable-extensions')
options.add_argument('--disable-plugins')
options.add_argument('--disable-images')
# options.add_argument('--disable-javascript')  # Removed - JavaScript is needed
options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36')
options.add_experimental_option("excludeSwitches", ["enable-automation"])
options.add_experimental_option('useAutomationExtension', False)
driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)

# Hide the fact that the browser is being controlled by Selenium
driver.execute_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")

progress_file = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'progress.txt')
def set_progress(val):
    with open(progress_file, 'w') as pf:
        pf.write(str(val))
set_progress(0)  # بدء العملية

try:
    # Open the website
    print("Opening the website...")
    driver.get("https://ums.rta.ae/violations/public-fines/fines-search")
    wait = WebDriverWait(driver, 40)
    set_progress(10)  # بعد فتح الموقع

    # Wait for the page to load
    print("Waiting for the page to load...")
    time.sleep(5)

    # Print the current page title and URL
    print(f"Current page title: {driver.title}")
    print(f"Current page URL: {driver.current_url}")

    # Close the cookie consent popup if it appears
    try:
        print("Searching for cookie consent button...")
        cookie_btn = wait.until(EC.element_to_be_clickable((By.XPATH, '//button[contains(., "Accept All")]')))
        cookie_btn.click()
        print("Clicked cookie consent button")
    except TimeoutException:
        print("Cookie consent popup did not appear")

    # Try to find the Traffic Code Number button in multiple ways
    print("Searching for Traffic Code Number button...")
    traffic_code_btn = None

    # Way 1: Search by original XPATH
    try:
        traffic_code_btn = wait.until(
            EC.element_to_be_clickable((By.XPATH, '//span[contains(@class, "trafficCode") and contains(text(), "Traffic Code Number")]'))
        )
        print("Found button by Way 1")
    except TimeoutException:
        print("Way 1 failed")

    # Way 2: Search by text only
    if not traffic_code_btn:
        try:
            traffic_code_btn = wait.until(
                EC.element_to_be_clickable((By.XPATH, '//*[contains(text(), "Traffic Code Number")]'))
            )
            print("Found button by Way 2")
        except TimeoutException:
            print("Way 2 failed")

    # Way 3: Search by any element containing the text
    if not traffic_code_btn:
        try:
            traffic_code_btn = driver.find_element(By.XPATH, '//*[contains(text(), "Traffic Code Number")]')
            print("Found button by Way 3")
        except NoSuchElementException:
            print("Way 3 failed")

    # Way 4: Search in all clickable elements
    if not traffic_code_btn:
        try:
            clickable_elements = driver.find_elements(By.XPATH, '//*[contains(text(), "Traffic Code") or contains(text(), "traffic")]')
            for element in clickable_elements:
                if element.is_displayed() and element.is_enabled():
                    traffic_code_btn = element
                    print("Found button by Way 4")
                    break
        except Exception as e:
            print(f"Way 4 failed: {e}")

    # Print diagnostic information
    if not traffic_code_btn:
        print("Traffic Code Number button not found")
        print("Printing all clickable elements on the page...")
        clickable_elements = driver.find_elements(By.XPATH, '//button | //span | //div[contains(@class, "btn")] | //a')
        for i, elem in enumerate(clickable_elements[:10]):  # Print only the first 10 elements
            try:
                text = elem.text.strip()
                if text:
                    print(f"Element {i}: {text[:50]}")
            except:
                pass
        raise Exception("Traffic Code Number button not found")

    # Click the button
    print("Clicking Traffic Code Number button...")
    driver.execute_script("arguments[0].click();", traffic_code_btn)
    time.sleep(2)

    # Wait until the input field appears and is visible
    print("Searching for file number input field...")
    file_input = wait.until(EC.visibility_of_element_located((By.ID, "Id_trafficFileNumber")))
    file_input.clear()
    file_input.send_keys(file_number)
    print(f"Entered file number: {file_number}")

    # Click the search button
    print("Searching for search button...")
    search_button = wait.until(EC.element_to_be_clickable((By.ID, "Id_searchBTN")))
    search_button.click()
    print("Clicked search button")

    # Wait longer for results to load
    print("Waiting for search results...")
    time.sleep(10)  # Increased wait time for results

    # Wait for navigation to results page
    print("Waiting for navigation to results page...")
    wait.until(EC.url_contains("customer-violations"))
    print("Navigated to results page:", driver.current_url)

    # After navigating to results page
    print("Collecting all rows from the table...")
    time.sleep(5)  # Increased wait time

    # Print page source for debugging
    print("Page title:", driver.title)
    print("Current URL:", driver.current_url)
    print("Page source length:", len(driver.page_source))

    # Check if we're on the right page
    if "customer-violations" not in driver.current_url:
        print("WARNING: Not on the expected results page!")
        print("Current URL:", driver.current_url)

    # Check for common elements
    try:
        table = driver.find_element(By.ID, "Id_FinesResultTable")
        print("Found results table")
    except:
        print("Results table not found!")

    # Check for any text containing "AED" or "Fine"
    page_text = driver.find_element(By.TAG_NAME, "body").text
    if "AED" in page_text:
        print("Found 'AED' in page text")
    if "Fine" in page_text:
        print("Found 'Fine' in page text")
    if "Police" in page_text:
        print("Found 'Police' in page text")

    # تحسين العثور على الصفوف - محاولة عدة طرق
    rows = []

    # الطريقة الأولى: البحث عن الصفوف القابلة للنقر
    rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable .p-selectable-row')
    print(f"Method 1 - p-selectable-row: Found {len(rows)} rows")

    # الطريقة الثانية: البحث عن قائمة المخالفات
    if not rows:
        rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable .fines_violation_list')
        print(f"Method 2 - fines_violation_list: Found {len(rows)} rows")

    # الطريقة الثالثة: البحث عن جميع صفوف الجدول
    if not rows:
        all_trs = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable tr')
        rows = all_trs[1:] if len(all_trs) > 1 else []  # تجاهل صف العنوان
        print(f"Method 3 - all tr elements: Found {len(rows)} rows")

    # الطريقة الرابعة: البحث عن أي صف يحتوي على بيانات
    if not rows:
        rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable tr:not(:first-child)')
        print(f"Method 4 - all tr except header: Found {len(rows)} rows")

    # الطريقة الخامسة: البحث في جميع العناصر داخل الجدول
    if not rows:
        table = driver.find_element(By.ID, "Id_FinesResultTable")
        all_elements = table.find_elements(By.XPATH, './/tr[position()>1]')
        rows = [elem for elem in all_elements if elem.text.strip()]
        print(f"Method 5 - XPath all tr elements: Found {len(rows)} rows")

    print(f"Final number of rows to process: {len(rows)}")

    # طباعة معلومات عن الصفوف الموجودة
    for i, row in enumerate(rows[:5]):  # طباعة أول 5 صفوف فقط
        try:
            row_text = row.text.strip()
            print(f"Row {i+1} preview: {row_text[:100]}...")
        except:
            print(f"Row {i+1}: Could not read text")

    if len(rows) > 5:
        print(f"... and {len(rows) - 5} more rows")

    details_list = []
    page_num = 1
    processed_rows = 0

    while True:
        print(f"Collecting all rows from the table on page {page_num}...")
        time.sleep(2)

        # استخدام نفس منطق العثور على الصفوف
        current_rows = []
        current_rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable .p-selectable-row')
        if not current_rows:
            current_rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable .fines_violation_list')
        if not current_rows:
            all_trs = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable tr')
            current_rows = all_trs[1:] if len(all_trs) > 1 else []
        if not current_rows:
            current_rows = driver.find_elements(By.CSS_SELECTOR, '#Id_FinesResultTable tr:not(:first-child)')

        print(f"Page {page_num}: Found {len(current_rows)} rows to process")

        for idx, row in enumerate(current_rows):
            try:
                row_text = row.text.strip()
                print(f"Row {idx+1}: {row_text}")
                if not row_text or 'Select a single fine to view its details' in row_text:
                    print(f"Skipping Row {idx+1} because it's empty or a instructions message.")
                    continue
                if not row.is_displayed() or not row.is_enabled():
                    continue
                driver.execute_script("arguments[0].scrollIntoView();", row)
                time.sleep(0.5)
                row.click()
                print(f"Clicked Row {idx+1}")
                WebDriverWait(driver, 10).until(
                    EC.visibility_of_element_located((By.CSS_SELECTOR, '.viewDetails'))
                )
                time.sleep(0.5)
                try:
                    details_text = driver.find_element(By.CSS_SELECTOR, '.viewDetails').text
                except Exception as e:
                    details_text = ''
                    print(f"Failed to retrieve details for Row {idx+1}: {e}")
                details_list.append({'Details': details_text})
                processed_rows += 1
                print(f"Successfully processed row {processed_rows} on page {page_num}")
            except Exception as e:
                print(f"Error processing Row {idx+1} on page {page_num}: {e}")
                continue

        # Try to click the next button
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, '.p-paginator-next.p-paginator-element.p-link')
            if "p-disabled" in next_btn.get_attribute("class"):
                print("Next button is disabled. No more pages.")
                break  # Last page
            next_btn.click()
            page_num += 1
            time.sleep(2)  # Wait for the new page to load
        except Exception as e:
            print(f"Next button not found or error: {e}")
            break

    print(f"=== FINAL SUMMARY ===")
    print(f"Total rows processed: {processed_rows}")
    print(f"Total violations collected: {len(details_list)}")
    print(f"Pages processed: {page_num}")

    if details_list:
        df = pd.DataFrame(details_list)
        base_dir = os.path.dirname(os.path.abspath(__file__))
        details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')
        df.to_excel(details_excel_path, index=False)
        print(f'All details saved in {details_excel_path}', flush=True)
        set_progress(40)  # بعد جمع الصفوف وحفظ التفاصيل
    else:
        print('No details found! Creating empty details file...')
        # Create empty details file to prevent errors
        df = pd.DataFrame([{'Details': 'No details found'}])
        base_dir = os.path.dirname(os.path.abspath(__file__))
        details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')
        df.to_excel(details_excel_path, index=False)
        print(f'Empty details file created: {details_excel_path}')
        set_progress(40)

    # Print all tr elements
    trs = driver.find_elements(By.TAG_NAME, "tr")
    print(f"Number of tr elements: {len(trs)}")
    for i, tr in enumerate(trs):
        print(f"tr[{i}]: {tr.text[:100]}")

    # Print all div elements
    divs = driver.find_elements(By.TAG_NAME, "div")
    print(f"Number of div elements: {len(divs)}")
    for i, div in enumerate(divs):
        text = div.text.replace('\n', ' ')
        if len(text) > 0:
            print(f"div[{i}]: {text[:100]}")

    # Wait for table to appear
    table = wait.until(EC.presence_of_element_located((By.ID, "Id_FinesResultTable")))
    time.sleep(2)  # Safety increase

    # Print all child elements of the table and their text (with protection from StaleElementReferenceException)
    all_children = table.find_elements(By.XPATH, './/*')
    for i, child in enumerate(all_children):
        try:
            tag = child.tag_name
        except Exception:
            tag = 'N/A'
        try:
            cls = child.get_attribute('class')
        except Exception:
            cls = 'N/A'
        try:
            txt = child.text[:80]
        except Exception:
            txt = 'N/A'
        print(f"Element {i}: tag={tag}, class={cls}, text={txt}")

    # Wait for results or no results message
    try:
        wait.until(
            EC.any_of(
                EC.presence_of_element_located((By.CSS_SELECTOR, '.violation-details')),
                EC.presence_of_element_located((By.XPATH, '//*[contains(text(), "No results") or contains(text(), "No fines") or contains(text(), "لم يتم العثور على مخالفات")]'))
            )
        )
    except TimeoutException:
        print("End Time and no Results")
    # Print all results elements on the page
    results = driver.find_elements(By.CSS_SELECTOR, '.violation-details')
    print(f"Number of results extracted: {len(results)}")
    if not results:
        print("There is no result for this file number or fines.")

    else:
        for idx, result in enumerate(results, 1):
            print(f"--- Result {idx} ---")
            print(result.text)
            print("----------------------\n")

    # Extract all violations from the page
    violations_list = []
    page_num = 1
    while True:
        print(f"--- Collecting violations from page {page_num} ---")

        # Try multiple selectors to find violations
        violations = driver.find_elements(By.CSS_SELECTOR, '.row.fines_violation_list')
        if not violations:
            violations = driver.find_elements(By.CSS_SELECTOR, '.finesRowList')
        if not violations:
            violations = driver.find_elements(By.CSS_SELECTOR, '[class*="fines"]')
        if not violations:
            violations = driver.find_elements(By.CSS_SELECTOR, '[class*="violation"]')

        print(f"Found {len(violations)} violation elements using CSS selectors")

        for v in violations:
            text = v.text.strip()
            print(f"Raw violation text: {text[:200]}...")

            # Split text if it contains more than one violation (empty lines '\n\n')
            violations_split = [vi.strip() for vi in text.split('\n\n') if vi.strip()]
            for single_violation in violations_split:
                if single_violation and single_violation not in violations_list:
                    violations_list.append(single_violation)
                    print(f"Added violation: {single_violation[:100]}...")
        # Search for next button
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, '.p-paginator-next.p-paginator-element.p-link')
            if "p-disabled" in next_btn.get_attribute("class"):
                break  # Button is not enabled (last page)
            first_violation_text = violations[0].text.strip() if violations else None
            num_violations = len(violations)
            next_btn.click()
            page_num += 1
            def page_changed(driver):
                new_violations = driver.find_elements(By.CSS_SELECTOR, '.row.fines_violation_list')
                if len(new_violations) != num_violations:
                    return True
                if new_violations and new_violations[0].text.strip() != first_violation_text:
                    return True
                return False
            WebDriverWait(driver, 20).until(page_changed)
        except Exception:
            break

    print(f"Collected {len(violations_list)} violations from all pages.")

    # If no violations found through normal method, try direct extraction
    if not violations_list:
        print("Trying direct extraction from page elements...")

        # Method 1: Look for elements with violation data
        all_elements = driver.find_elements(By.XPATH, '//*[contains(@class, "fines") or contains(@class, "violation") or contains(text(), "AED")]')
        for elem in all_elements:
            text = elem.text.strip()
            if text and ('AED' in text or 'Police' in text or 'Fine' in text):
                if text not in violations_list:
                    violations_list.append(text)
                    print(f"Direct extraction found: {text[:100]}...")

        # Method 2: Try to find table rows directly
        if not violations_list:
            print("Trying table row extraction...")
            table_rows = driver.find_elements(By.CSS_SELECTOR, 'table tr')
            for row in table_rows:
                text = row.text.strip()
                if text and len(text) > 20:  # Filter out header rows
                    violations_list.append(text)
                    print(f"Table row found: {text[:100]}...")

        # Method 3: Extract from page source
        if not violations_list:
            print("Trying page source extraction...")
            page_source = driver.page_source
            # Look for patterns that indicate violations
            import re
            violation_patterns = [
                r'(\d+\.\d+ AED)',
                r'(Police.*?\d{4})',
                r'(Fine.*?\d+)',
                r'(\d{2}/\d{2}/\d{4})',  # Date patterns
            ]

            for pattern in violation_patterns:
                matches = re.findall(pattern, page_source)
                for match in matches:
                    if match not in violations_list:
                        violations_list.append(match)
                        print(f"Pattern match found: {match}")

    # Save cleaned_violations part in violations.xlsx as before
    # Save results in Excel file (each violation in a separate row)
    if violations_list:
        unwanted = ["show", "fine source", "category", "code", "sort", "date (new-old)", "date and time of issuing the fine", "amount", "source"]
        cleaned_violations = []
        for v in violations_list:
            matches = re.findall(r'(.*?Black points(?:\n.*)?)(?:\n|$)', str(v), re.DOTALL)
            for match in matches:
                part = match.strip()
                if part:
                    cleaned_violations.append(part)
        if cleaned_violations:
            df_all = pd.DataFrame(cleaned_violations, columns=['Violation'])
            base_dir = os.path.dirname(os.path.abspath(__file__))
            violations_excel_path = os.path.join(base_dir, 'violations.xlsx')
            df_all.to_excel(violations_excel_path, index=False)
            print(f'Violations saved in {violations_excel_path}', flush=True)
        else:
            print('No data found for analysis!')

finally:
    print("Closing the browser...")
    driver.quit()
    base_dir = os.path.dirname(os.path.abspath(__file__))

    # Ensure violations_details.xlsx exists before calling create_empty_excel.py
    details_excel_path = os.path.join(base_dir, 'violations_details.xlsx')
    if not os.path.exists(details_excel_path):
        print(f"Creating violations_details.xlsx at: {details_excel_path}")
        df = pd.DataFrame([{'Details': 'No details found'}])
        df.to_excel(details_excel_path, index=False)
        print(f"File created successfully: {details_excel_path}")
    else:
        print(f"File already exists: {details_excel_path}")

    set_progress(50)  # قبل استدعاء create_empty_excel.py
    subprocess.run(['python3', os.path.join(base_dir, 'create_empty_excel.py')])
