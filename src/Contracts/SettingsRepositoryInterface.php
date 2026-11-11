<?php

namespace Puleeno\NhanhVn\Syncer\Contracts;

interface SettingsRepositoryInterface
{
    /**
     * @return array<string,mixed>
     */
    public function all(): array;

    /**
     * @param array<string,mixed> $settings
     */
    public function save(array $settings): void;

    /**
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public function getWithDefaults(array $defaults): array;
}
