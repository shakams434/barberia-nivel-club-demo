<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaileysBotTest extends TestCase
{
    use RefreshDatabase;

    private function connectBot(): WhatsAppAccount
    {
        $business = Business::factory()->create();
        $account = WhatsAppAccount::create([
            'business_id' => $business->id,
            'provider' => 'baileys',
            'connection_mode' => 'baileys',
            'phone_e164' => '+51993807551',
            'connection_status' => 'connected',
            'send_enabled' => true,
        ]);
        $account->access_token = 'bot-token-0123456789abcdef';
        $account->save();
        app(TenantContext::class)->clear();

        return $account;
    }

    public function test_bot_registers_its_tunnel_url_and_url_is_normalized(): void
    {
        $account = $this->connectBot();

        $this->postJson('/api/webhooks/whatsapp-bot/register', [
            'base_url' => 'https://example.trycloudflare.com/send-message/',
        ], ['Authorization' => 'Bearer bot-token-0123456789abcdef'])
            ->assertOk()
            ->assertJsonPath('base_url', 'https://example.trycloudflare.com');

        $account = $account->fresh();
        $this->assertSame('https://example.trycloudflare.com', $account->baileys_base_url);
        $this->assertSame('connected', $account->connection_status);
    }

    public function test_bot_registration_rejects_an_invalid_token(): void
    {
        $this->connectBot();

        $this->postJson('/api/webhooks/whatsapp-bot/register', [
            'base_url' => 'https://example.trycloudflare.com',
        ], ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();
    }

    public function test_bot_webhook_rejects_an_invalid_token(): void
    {
        $this->connectBot();

        $this->postJson('/api/webhooks/whatsapp-bot', [
            'messages' => [['id' => '1', 'from' => '51993807551', 'text' => 'SALDO']],
        ], ['Authorization' => 'Bearer wrong-token'])->assertUnauthorized();
    }
}
