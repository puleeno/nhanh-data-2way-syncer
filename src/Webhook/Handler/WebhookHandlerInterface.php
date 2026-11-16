<?php

namespace Puleeno\NhanhVn\Syncer\Webhook\Handler;

interface WebhookHandlerInterface
{
    /** @return array<string,mixed> */
    public function handle(array $event): array;
}
