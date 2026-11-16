<?php

namespace Puleeno\NhanhVn\Syncer\Webhook;

use Puleeno\NhanhVn\Syncer\Webhook\Handler\InventoryChangeHandler;
use Puleeno\NhanhVn\Syncer\Webhook\Handler\ProductAddHandler;
use Puleeno\NhanhVn\Syncer\Webhook\Handler\ProductDeleteHandler;
use Puleeno\NhanhVn\Syncer\Webhook\Handler\ProductUpdateHandler;
use Puleeno\NhanhVn\Syncer\Webhook\Handler\WebhookHandlerInterface;

class WebhookDispatcher
{
    /** @var array<string,WebhookHandlerInterface> */
    private $handlers = [];
    /** @var array<string,mixed> */
    private $settings;

    /** @param array<string,mixed> $settings */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        $this->handlers = [
            'productAdd' => new ProductAddHandler($settings),
            'productUpdate' => new ProductUpdateHandler($settings),
            'productDelete' => new ProductDeleteHandler($settings),
            'inventoryChange' => new InventoryChangeHandler($settings),
        ];
    }

    /** @param array<string,mixed> $payload */
    public function dispatch(array $payload): array
    {
        $event = isset($payload['event']) ? (string) $payload['event'] : '';
        if ($event === '') {
            return ['ok' => false, 'error' => 'missing_event'];
        }
        if (!isset($this->handlers[$event])) {
            // acknowledge unknown events to avoid webhook retries storm
            return ['ok' => true, 'ignored' => true, 'event' => $event];
        }
        try {
            $result = $this->handlers[$event]->handle($payload);
            $result['event'] = $event;
            return $result;
        } catch (\Throwable $e) {
            return ['ok' => false, 'event' => $event, 'error' => $e->getMessage()];
        }
    }
}
