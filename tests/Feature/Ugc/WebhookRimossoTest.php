<?php

namespace Tests\Feature\Ugc;

use Tests\TestCase;

class WebhookRimossoTest extends TestCase
{
    public function test_le_rotte_del_webhook_non_esistono_piu(): void
    {
        $this->postJson('/api/webhook/ugc/poi', [])->assertNotFound();
        $this->postJson('/api/webhook/ugc/track', [])->assertNotFound();
    }
}
