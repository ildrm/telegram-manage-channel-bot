<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Emoji Preset Service
 * 
 * Manage emoji presets for quick access
 */
class EmojiPresetService
{
    private array $presets = [
        'business' => ['💼', '📊', '💰', '📈', '🎯', '✅', '⚡', '🔥'],
        'tech' => ['💻', '📱', '⚙️', '🔧', '🚀', '💡', '🤖', '🔬'],
        'lifestyle' => ['☕', '🎉', '❤️', '✨', '🌟', '💫', '🎨', '📸'],
        'news' => ['📰', '🗞️', '📢', '⚠️', '🔔', '📣', '🎙️', '📺'],
        'education' => ['📚', '✏️', '🎓', '📖', '🧠', '💭', '📝', '🔍'],
        'food' => ['🍕', '🍔', '🍰', '☕', '🍜', '🥗', '🍱', '🍷'],
        'travel' => ['✈️', '🌍', '🗺️', '📍', '🏖️', '🏔️', '🚗', '🏨'],
        'fitness' => ['💪', '🏃', '🏋️', '🧘', '⚽', '🏀', '🎾', '🏊'],
        'celebration' => ['🎉', '🎊', '🎁', '🎂', '🥳', '🍾', '🎈', '🎆'],
        'weather' => ['☀️', '🌤️', '⛅', '🌧️', '⛈️', '❄️', '🌈', '🌙']
    ];

    /**
     * Get preset by category
     */
    public function getPreset(string $category): array
    {
        return $this->presets[$category] ?? [];
    }

    /**
     * Get all presets
     */
    public function getAllPresets(): array
    {
        return $this->presets;
    }

    /**
     * Get random emoji from category
     */
    public function getRandomEmoji(string $category): string
    {
        $emojis = $this->presets[$category] ?? [];
        
        if (empty($emojis)) {
            return '';
        }

        return $emojis[array_rand($emojis)];
    }

    /**
     * Add emojis to text
     */
    public function decorateText(string $text, string $category): string
    {
        $emoji = $this->getRandomEmoji($category);
        
        if (!$emoji) {
            return $text;
        }

        // Add emoji at start and end
        return $emoji . ' ' . $text . ' ' . $emoji;
    }

    /**
     * Format text with line emojis
     */
    public function formatWithEmojis(string $text, array $lineEmojis = []): string
    {
        $lines = explode("\n", $text);
        $formatted = [];

        foreach ($lines as $i => $line) {
            if (empty(trim($line))) {
                $formatted[] = $line;
                continue;
            }

            $emoji = $lineEmojis[$i] ?? '•';
            $formatted[] = $emoji . ' ' . ltrim($line);
        }

        return implode("\n", $formatted);
    }
}
