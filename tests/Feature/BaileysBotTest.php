<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppConversation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_conversation_page_renders_after_bot_join_flow(): void
    {
        Http::fake(['*/send-message' => Http::sequence()
            ->push(['ok' => true, 'id' => 'TESTMSG1', 'to' => '51993807551@s.whatsapp.net'])
            ->push(['ok' => true, 'id' => 'TESTMSG2', 'to' => '51993807551@s.whatsapp.net'])]);
        $account = $this->connectBot();
        $account->update(['baileys_base_url' => 'https://example.trycloudflare.com']);

        $this->postJson('/api/webhooks/whatsapp-bot', [
            'messages' => [['id' => 'join-1', 'from' => '51993807551', 'text' => 'QUIERO UNIRME', 'timestamp' => '1720000000']],
        ], ['Authorization' => 'Bearer bot-token-0123456789abcdef'])->assertOk();

        $conversation = WhatsAppConversation::firstOrFail();
        $admin = User::factory()->create(['business_id' => $account->business_id, 'role' => 'admin']);

        $this->actingAs($admin)->get(route('whatsapp.conversations.show', $conversation))->assertOk();

        $this->actingAs($admin)->post(route('whatsapp.conversations.reply', $conversation), [
            'message' => 'Claro, tenemos espacio hoy.',
        ])->assertRedirect(route('whatsapp.conversations.show', $conversation));

        $this->actingAs($admin)->get(route('whatsapp.conversations.show', $conversation))->assertOk();
        $this->actingAs($admin)->get(route('whatsapp.conversations.index'))->assertOk();
    }

    public function test_conversation_page_handles_edge_cases_without_crashing(): void
    {
        Http::fake(['*/send-message' => Http::response(['ok' => true, 'id' => 'EDGE1', 'to' => 'x'])]);
        $account = $this->connectBot();
        $admin = User::factory()->create(['business_id' => $account->business_id, 'role' => 'admin']);

        $business = $account->business;

        $ghost = \App\Models\Customer::create([
            'business_id' => $business->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Cliente Borrado',
            'phone_e164' => '+51900000001',
            'joined_at' => now(),
        ]);
        $deletedConversation = WhatsAppConversation::create([
            'business_id' => $business->id,
            'customer_id' => $ghost->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'phone_e164' => '+51900000001',
            'last_message_at' => now(),
        ]);
        $ghost->delete();

        $orphan = WhatsAppConversation::create([
            'business_id' => $business->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'phone_e164' => '+51900000002',
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ]);
        $orphan->inboundMessages()->create([
            'business_id' => $business->id,
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'meta_message_id' => 'edge-inbound-1',
            'from_phone_e164' => '+51900000002',
            'message_text' => 'Hola',
            'payload' => ['type' => 'text', 'timestamp' => 1720000000],
            'status' => 'received',
        ]);

        $this->actingAs($admin)->get(route('whatsapp.conversations.show', $deletedConversation))->assertOk();
        $this->actingAs($admin)->get(route('whatsapp.conversations.show', $orphan))->assertOk();
        $this->actingAs($admin)->get(route('whatsapp.conversations.index'))->assertOk();
    }
}
