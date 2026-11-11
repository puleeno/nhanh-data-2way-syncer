<?php

namespace Puleeno\NhanhVn\Syncer\Repository;

use Puleeno\NhanhVn\Syncer\Contracts\SettingsRepositoryInterface;

class SettingsRepository implements SettingsRepositoryInterface
{
    public const OPTION_KEY = 'nhanh_syncer_settings';

    public function all(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }

    public function save(array $settings): void
    {
        update_option(self::OPTION_KEY, $settings);
    }

    public function getWithDefaults(array $defaults): array
    {
        $current = $this->all();
        // Preserve existing values, fill missing with defaults.
        return array_merge($defaults, $current);
    }

    /**
     * Ensure defaults exist in storage without overwriting existing keys.
     */
    public function ensureDefaults(array $defaults): array
    {
        $merged = $this->getWithDefaults($defaults);
        $this->save($merged);
        return $merged;
    }
}
