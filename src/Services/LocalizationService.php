<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Localization Service
 * 
 * Multi-language support
 */
class LocalizationService
{
    private string $lang = 'en';
    private array $translations = [];

    public function __construct(string $defaultLang = 'en')
    {
        $this->lang = $defaultLang;
        $this->loadTranslations();
    }

    /**
     * Load translations
     */
    private function loadTranslations(): void
    {
        $this->translations = [
            'en' => [
                'welcome' => '👋 Welcome to Channel Manager Bot!',
                'select_channel' => 'Select a channel:',
                'new_post' => '✍️ New Post',
                'drafts' => '📝 Drafts',
                'schedule' => '⏰ Schedule',
                'analytics' => '📊 Analytics',
                'settings' => '🔧 Settings',
                'help' => '📖 Help',
                'back' => '« Back',
                'cancel' => '❌ Cancel',
                'post_published' => '✅ Post Published!',
                'post_scheduled' => '✅ Post Scheduled!',
                'error' => '❌ Error: {error}',
            ],
            'fa' => [ // Persian/Farsi
                'welcome' => '👋 به ربات مدیریت کانال خوش آمدید!',
                'select_channel' => 'یک کانال انتخاب کنید:',
                'new_post' => '✍️ پست جدید',
                'drafts' => '📝 پیش‌نویس‌ها',
                'schedule' => '⏰ زمان‌بندی',
                'analytics' => '📊 آمار',
                'settings' => '🔧 تنظیمات',
                'help' => '📖 راهنما',
                'back' => '« بازگشت',
                'cancel' => '❌ لغو',
                'post_published' => '✅ پست منتشر شد!',
                'post_scheduled' => '✅ پست زمان‌بندی شد!',
                'error' => '❌ خطا: {error}',
            ],
            'ar' => [ // Arabic
                'welcome' => '👋 مرحبا بك في بوت إدارة القناة!',
                'select_channel' => 'اختر قناة:',
                'new_post' => '✍️ منشور جديد',
                'drafts' => '📝 المسودات',
                'schedule' => '⏰ الجدولة',
                'analytics' => '📊 الإحصائيات',
                'settings' => '🔧 الإعدادات',
                'help' => '📖 المساعدة',
                'back' => '« رجوع',
                'cancel' => '❌ إلغاء',
                'post_published' => '✅ تم نشر المنشور!',
                'post_scheduled' => '✅ تم جدولة المنشور!',
                'error' => '❌ خطأ: {error}',
            ]
        ];
    }

    /**
     * Set language
     */
    public function setLanguage(string $lang): void
    {
        if (isset($this->translations[$lang])) {
            $this->lang = $lang;
        }
    }

    /**
     * Translate key
     */
    public function translate(string $key, array $params = []): string
    {
        $translation = $this->translations[$this->lang][$key] ?? $this->translations['en'][$key] ?? $key;

        // Replace parameters
        foreach ($params as $param => $value) {
            $translation = str_replace('{' . $param . '}', $value, $translation);
        }

        return $translation;
    }

    /**
     * Alias for translate
     */
    public function __invoke(string $key, array $params = []): string
    {
        return $this->translate($key, $params);
    }
}
