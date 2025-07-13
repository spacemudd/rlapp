import { createI18n } from 'vue-i18n';

// Language messages
const messages = {
    en: {
        // Profile & Settings
        profile: 'Profile',
        profile_information: 'Profile information',
        update_profile_info: 'Update your name and email address',
        name: 'Name',
        email: 'Email address',
        email_unverified: 'Your email address is unverified.',
        resend_verification: 'Click here to resend the verification email.',
        verification_sent: 'A new verification link has been sent to your email address.',
        save: 'Save',
        saved: 'Saved.',
        language: 'Language',
        select_language: 'Select language',
        
        // Navigation & Layout
        dashboard: 'Dashboard',
        customers: 'Customers',
        vehicles: 'Vehicles',
        contracts: 'Contracts',
        invoices: 'Invoices',
        payments: 'Payments',
        locations: 'Locations',
        team_management: 'Team Management',
        settings: 'Settings',
        profile_settings: 'Profile Settings',
        password: 'Password',
        appearance: 'Appearance',
        
        // Common Actions
        create: 'Create',
        edit: 'Edit',
        delete: 'Delete',
        update: 'Update',
        cancel: 'Cancel',
        confirm: 'Confirm',
        back: 'Back',
        next: 'Next',
        previous: 'Previous',
        search: 'Search',
        filter: 'Filter',
        export: 'Export',
        import: 'Import',
        
        // Status & Messages
        active: 'Active',
        inactive: 'Inactive',
        pending: 'Pending',
        completed: 'Completed',
        cancelled: 'Cancelled',
        loading: 'Loading...',
        no_data: 'No data available',
        error_occurred: 'An error occurred',
        success: 'Success',
    },
    ar: {
        // Profile & Settings
        profile: 'الملف الشخصي',
        profile_information: 'معلومات الملف الشخصي',
        update_profile_info: 'تحديث الاسم وعنوان البريد الإلكتروني',
        name: 'الاسم',
        email: 'عنوان البريد الإلكتروني',
        email_unverified: 'عنوان بريدك الإلكتروني غير مُتحقق منه.',
        resend_verification: 'اضغط هنا لإعادة إرسال رسالة التحقق.',
        verification_sent: 'تم إرسال رابط تحقق جديد إلى عنوان بريدك الإلكتروني.',
        save: 'حفظ',
        saved: 'تم الحفظ.',
        language: 'اللغة',
        select_language: 'اختر اللغة',
        
        // Navigation & Layout
        dashboard: 'لوحة التحكم',
        customers: 'العملاء',
        vehicles: 'المركبات',
        contracts: 'العقود',
        invoices: 'الفواتير',
        payments: 'المدفوعات',
        locations: 'المواقع',
        team_management: 'إدارة الفريق',
        settings: 'الإعدادات',
        profile_settings: 'إعدادات الملف الشخصي',
        password: 'كلمة المرور',
        appearance: 'المظهر',
        
        // Common Actions
        create: 'إنشاء',
        edit: 'تعديل',
        delete: 'حذف',
        update: 'تحديث',
        cancel: 'إلغاء',
        confirm: 'تأكيد',
        back: 'رجوع',
        next: 'التالي',
        previous: 'السابق',
        search: 'بحث',
        filter: 'تصفية',
        export: 'تصدير',
        import: 'استيراد',
        
        // Status & Messages
        active: 'نشط',
        inactive: 'غير نشط',
        pending: 'معلق',
        completed: 'مكتمل',
        cancelled: 'ملغي',
        loading: 'جاري التحميل...',
        no_data: 'لا توجد بيانات متاحة',
        error_occurred: 'حدث خطأ',
        success: 'نجح',
    },
};

// RTL languages list
const rtlLanguages = ['ar', 'he', 'fa', 'ur'];

// Create i18n instance
export const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages,
});

// Helper function to check if language is RTL
export const isRTL = (locale: string): boolean => {
    return rtlLanguages.includes(locale);
};

// Helper function to set document direction
export const setDirection = (locale: string): void => {
    const direction = isRTL(locale) ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', direction);
    document.documentElement.setAttribute('lang', locale);
};

// Helper function to get current locale direction
export const getCurrentDirection = (): string => {
    return document.documentElement.getAttribute('dir') || 'ltr';
}; 