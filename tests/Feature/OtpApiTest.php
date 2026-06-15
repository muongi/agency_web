<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OtpApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createClientAccount(): array
    {
        $bu = BusinessUnit::create([
            'name' => 'OTP API BU',
            'slug' => 'otp-api-bu-'.uniqid(),
            'is_active' => true,
            'sms_sender_number' => 'tel:+237600000001',
        ]);

        $client = Client::create([
            'business_unit_id' => $bu->id,
            'first_name' => 'OTP',
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

        return compact('bu', 'client', 'account');
    }

    protected function mockSmsSuccess(): void
    {
        $mock = Mockery::mock(SmsService::class);
        $mock->shouldReceive('sendSms')
            ->andReturn([
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => 'test-msg-id',
                'sms_message_id' => 1,
            ]);
        $this->app->instance(SmsService::class, $mock);
    }

    /**
     * @dataProvider validOtpCodesProvider
     */
    public function test_forward_otp_accepts_valid_numeric_lengths(string $otpCode): void
    {
        $this->mockSmsSuccess();
        $data = $this->createClientAccount();

        $response = $this->postJson('/api/v1/otp/forward', [
            'business_unit_id' => $data['bu']->id,
            'account_number' => $data['account']->account_number,
            'otp_code' => $otpCode,
            'purpose' => 'transaction',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sms_status', 'sent');
    }

    public static function validOtpCodesProvider(): array
    {
        return [
            'four_digits' => ['1234'],
            'five_digits' => ['12345'],
            'six_digits' => ['123456'],
        ];
    }

    /**
     * @dataProvider invalidOtpCodesProvider
     */
    public function test_forward_otp_rejects_invalid_codes(string $otpCode): void
    {
        $this->mockSmsSuccess();
        $data = $this->createClientAccount();

        $response = $this->postJson('/api/v1/otp/forward', [
            'business_unit_id' => $data['bu']->id,
            'account_number' => $data['account']->account_number,
            'otp_code' => $otpCode,
            'purpose' => 'transaction',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['otp_code']);
    }

    public static function invalidOtpCodesProvider(): array
    {
        return [
            'three_digits' => ['123'],
            'seven_digits' => ['1234567'],
            'non_numeric' => ['12ab34'],
            'empty' => [''],
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
