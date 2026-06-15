<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\Card;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createBusinessUnitWithCard(): array
    {
        $bu = BusinessUnit::create([
            'name' => 'Card API BU',
            'slug' => 'card-api-bu-'.uniqid(),
            'is_active' => true,
        ]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'Card',
            'last_name' => 'Client',
            'phone_number' => '237600000099',
            'id_passport' => 'ID'.uniqid(),
        ]);

        $account = Account::create([
            'business_unit_id' => $bu->id,
            'accountable_type' => Client::class,
            'accountable_id' => $client->id,
            'account_type' => 'general',
            'account_number' => '0100101000004',
            'balance' => 0,
            'currency' => 'CFA',
            'status' => 'active',
            'opening_date' => now()->toDateString(),
        ]);

        $card = Card::create([
            'business_unit_id' => $bu->id,
            'account_id' => $account->id,
            'card_number' => '7800000002',
            'card_serial' => '04197D7AFE1D91',
            'prefix' => '7800',
            'status' => 'active',
            'expiration_date' => now()->addYears(3)->toDateString(),
        ]);

        return compact('bu', 'client', 'account', 'card');
    }

    public function test_get_by_uid_does_not_require_web_auth(): void
    {
        $data = $this->createBusinessUnitWithCard();

        $response = $this->getJson(
            '/api/v1/cards/uid/04197D7AFE1D91?business_unit_id='.$data['bu']->id
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('card.account_number', '0100101000004')
            ->assertJsonPath('card.card_serial', '04197D7AFE1D91')
            ->assertJsonPath('card.business_unit_id', $data['bu']->id);
    }

    public function test_get_by_uid_works_without_business_unit_id(): void
    {
        $data = $this->createBusinessUnitWithCard();

        $this->getJson('/api/v1/cards/uid/04197D7AFE1D91')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('card.account_number', '0100101000004')
            ->assertJsonPath('card.business_unit_id', $data['bu']->id);
    }

    public function test_get_by_uid_accepts_lowercase_serial(): void
    {
        $data = $this->createBusinessUnitWithCard();

        $this->getJson(
            '/api/v1/cards/uid/04197d7afe1d91?business_unit_id='.$data['bu']->id
        )
            ->assertOk()
            ->assertJsonPath('card.card_serial', '04197D7AFE1D91');
    }

    public function test_get_by_uid_returns_404_when_not_found(): void
    {
        $bu = BusinessUnit::create([
            'name' => 'Empty BU',
            'slug' => 'empty-bu-'.uniqid(),
            'is_active' => true,
        ]);

        $this->getJson(
            '/api/v1/cards/uid/UNKNOWNUID123?business_unit_id='.$bu->id
        )
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_mark_as_written_does_not_require_web_auth(): void
    {
        $data = $this->createBusinessUnitWithCard();
        $data['card']->update(['status' => 'inactive']);

        $this->postJson('/api/v1/cards/'.$data['card']->id.'/mark-written', [
            'business_unit_id' => $data['bu']->id,
            'device_serial' => 'TERM-001',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $data['card']->refresh();
        $this->assertSame('active', $data['card']->status);
        $this->assertNotNull($data['card']->written_at);
    }

    public function test_mark_as_written_works_without_business_unit_id(): void
    {
        $data = $this->createBusinessUnitWithCard();
        $data['card']->update(['status' => 'inactive']);

        $this->postJson('/api/v1/cards/'.$data['card']->id.'/mark-written', [
            'device_serial' => 'TERM-002',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $data['card']->refresh();
        $this->assertSame('active', $data['card']->status);
    }
}
