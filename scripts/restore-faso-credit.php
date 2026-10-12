<?php

/**
 * One-off restore for FASO CREDIT (business_unit_id 8) from activity_log + known log metadata.
 * Run: php scripts/restore-faso-credit.php
 */

$basePath = is_file(__DIR__ . '/../vendor/autoload.php')
    ? dirname(__DIR__)
    : '/var/www/html/agency';

require $basePath . '/vendor/autoload.php';
$app = require $basePath . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function activityAttributes(int $subjectId, string $subjectType, string $description = 'created'): ?array
{
    $row = DB::table('activity_log')
        ->where('subject_type', 'like', "%{$subjectType}%")
        ->where('subject_id', $subjectId)
        ->where('description', $description)
        ->orderByDesc('id')
        ->value('properties');

    if (!$row) {
        return null;
    }

    $decoded = json_decode($row, true);

    return $decoded['attributes'] ?? null;
}

function normalizeRowTimestamps(array $row, array $dateFields = [], array $datetimeFields = ['created_at', 'updated_at', 'deleted_at']): array
{
    foreach ($datetimeFields as $field) {
        if (!empty($row[$field])) {
            $row[$field] = date('Y-m-d H:i:s', strtotime((string) $row[$field]));
        }
    }

    foreach ($dateFields as $field) {
        if (!empty($row[$field])) {
            $row[$field] = date('Y-m-d', strtotime((string) $row[$field]));
        }
    }

    return $row;
}

function insertWithId(string $table, array $data): void
{
    $columns = implode(', ', array_map(static fn ($c) => "`{$c}`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    DB::statement("INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})", array_values($data));
}

function restoreTransactionTypes(): void
{
    // Remove enquiry types inserted at wrong ids after the DB wipe (unused).
    foreach ([3, 4] as $typeId) {
        $refs = DB::table('transactions')->where('transaction_type_id', $typeId)->count()
            + DB::table('business_unit_transaction_type')->where('transaction_type_id', $typeId)->count()
            + DB::table('commission_configurations')->where('transaction_type_id', $typeId)->count()
            + DB::table('transaction_cost_configurations')->where('transaction_type_id', $typeId)->count();

        if ($refs === 0 && DB::table('transaction_types')->where('id', $typeId)->exists()) {
            DB::table('transaction_types')->where('id', $typeId)->delete();
            echo "Removed unused transaction_types id {$typeId}\n";
        }
    }

    $types = [
        4 => ['name' => 'DEPOT EN ESPECES', 'code' => '210000', 'internal_ledger_flow' => 'cash_deposit', 'description' => 'Cash deposit via agency terminal'],
        7 => ['name' => "RETRAIT  D'ESPECES", 'code' => '0110000', 'internal_ledger_flow' => 'cash_withdrawal', 'description' => 'Cash withdrawal via agency terminal'],
        8 => ['name' => 'DEMANDE DE SOLDE', 'code' => '310000', 'internal_ledger_flow' => 'balance_enquiry', 'description' => 'Member balance enquiry via agency terminal'],
        9 => ['name' => 'HOSTORIQUE BANCAIRE', 'code' => '320000', 'internal_ledger_flow' => 'mini_statement', 'description' => 'Member mini-statement via agency terminal'],
        10 => ['name' => 'OUVRIR COMPTE', 'code' => '91000', 'internal_ledger_flow' => null, 'description' => 'Open member account via agency terminal'],
    ];

    // id 1 already holds 0110000 for Réseau/Union historical data; move it so id 7 can use 0110000 for FASO.
    if (DB::table('transaction_types')->where('id', 1)->where('code', '0110000')->exists()
        && !DB::table('transaction_types')->where('id', 7)->exists()) {
        DB::table('transaction_types')->where('id', 1)->update([
            'code' => '0100000',
            'updated_at' => now(),
        ]);
        echo "Moved legacy transaction_types id 1 code to 0100000\n";
    }

    foreach ($types as $id => $type) {
        if (DB::table('transaction_types')->where('id', $id)->exists()) {
            DB::table('transaction_types')->where('id', $id)->update([
                'business_unit_id' => null,
                'name' => $type['name'],
                'code' => $type['code'],
                'description' => $type['description'],
                'internal_ledger_flow' => $type['internal_ledger_flow'],
                'is_active' => 1,
                'updated_at' => now(),
            ]);
            echo "Updated transaction_types id {$id}\n";
            continue;
        }

        insertWithId('transaction_types', [
            'id' => $id,
            'business_unit_id' => null,
            'name' => $type['name'],
            'code' => $type['code'],
            'description' => $type['description'],
            'internal_ledger_flow' => $type['internal_ledger_flow'],
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        echo "Inserted transaction_types id {$id}\n";
    }
}

DB::beginTransaction();

try {
    restoreTransactionTypes();

    $buId = 8;

    if (DB::table('business_units')->where('id', $buId)->exists()) {
        echo "business_units id {$buId} already exists\n";
    } else {
        $katoTokenRow = DB::table('activity_log')
            ->where('subject_type', 'like', '%BusinessUnit%')
            ->where('subject_id', $buId)
            ->where('description', 'updated')
            ->orderByDesc('id')
            ->value('properties');

        $katoToken = null;
        $katoTokenExpires = null;
        if ($katoTokenRow) {
            $decoded = json_decode($katoTokenRow, true);
            $katoToken = $decoded['attributes']['kato_token'] ?? null;
            $katoTokenExpires = isset($decoded['attributes']['kato_token_expires_at'])
                ? date('Y-m-d H:i:s', strtotime($decoded['attributes']['kato_token_expires_at']))
                : null;
        }

        insertWithId('business_units', [
            'id' => $buId,
            'name' => 'FASO CREDIT',
            'slug' => 'faso-credit',
            'description' => null,
            'email' => 'infos@fasocredit.bf',
            'phone' => '+22625333550',
            'address' => 'Ouagadougou',
            'account' => null,
            'tax_number' => null,
            'mailing_address' => null,
            'relation_manager_phone' => null,
            'relation_manager_email' => null,
            'sms_sender_number' => null,
            'core_banking_system_id' => 2,
            'kato_company_id' => '94fac5ed-31a6-4381-b414-590c04ba69ca',
            'kato_admin_user_id' => null,
            'kato_registered_at' => '2026-07-01 10:37:36',
            'kato_registration_error' => null,
            'kato_api_response' => json_encode([
                'success' => true,
                'companyId' => '94fac5ed-31a6-4381-b414-590c04ba69ca',
                'companyName' => 'FASO CREDIT',
                'adminUsername' => 'FASO CREDIT',
            ]),
            'logo' => 'business-logos/1782902256_faso-credit.jpeg',
            'is_active' => 1,
            'cnss_enabled' => 0,
            'created_at' => '2026-07-01 10:37:36',
            'updated_at' => '2026-07-06 17:10:57',
            'deleted_at' => null,
        ]);

        if ($katoToken) {
            DB::table('business_units')->where('id', $buId)->update([
                'kato_token' => $katoToken,
                'kato_token_expires_at' => $katoTokenExpires,
                'kato_admin_username' => 'FASO CREDIT',
            ]);
        }

        echo "Restored business_units id {$buId} (FASO CREDIT)\n";
    }

    $agentId = 15;
    if (DB::table('agents')->where('id', $agentId)->exists()) {
        echo "agents id {$agentId} already exists\n";
    } else {
        insertWithId('agents', [
            'id' => $agentId,
            'business_unit_id' => $buId,
            'first_name' => 'faso',
            'second_name' => 'credit',
            'photo' => 'agent-photos/1782902453_6a44eeb54a172.jpeg',
            'email' => 'infos@fasocredit.bf',
            'phone_number' => '+22625333550',
            'id_passport' => 'fc123001',
            'physical_address' => 'Ouagadougou',
            'po_box' => null,
            'username' => 'FASO CREDIT',
            'password' => Hash::make('12345678'),
            'kato_agent_id' => 'AGT26070001',
            'kato_registration_error' => null,
            'created_at' => '2026-07-01 11:00:00',
            'updated_at' => '2026-07-06 17:10:57',
            'deleted_at' => null,
        ]);
        echo "Restored agents id {$agentId}\n";
    }

    $clientId = 77;
    $clientAttrs = activityAttributes($clientId, 'Client', 'created');
    if (DB::table('clients')->where('id', $clientId)->exists()) {
        echo "clients id {$clientId} already exists\n";
    } elseif ($clientAttrs) {
        $clientAttrs['id'] = $clientId;
        $clientAttrs['business_unit_id'] = $buId;
        $clientAttrs['kato_member_no'] = '2';
        $clientAttrs['date_of_birth'] = isset($clientAttrs['date_of_birth'])
            ? date('Y-m-d', strtotime($clientAttrs['date_of_birth']))
            : null;
        if (isset($clientAttrs['kato_api_response']) && is_array($clientAttrs['kato_api_response'])) {
            $clientAttrs['kato_api_response'] = json_encode($clientAttrs['kato_api_response']);
        }
        if (array_key_exists('biometrics_status', $clientAttrs)) {
            $clientAttrs['biometrics_status'] = $clientAttrs['biometrics_status'] ? 1 : 0;
        }
        $clientAttrs = normalizeRowTimestamps($clientAttrs);
        insertWithId('clients', $clientAttrs);
        echo "Restored clients id {$clientId}\n";
    } else {
        echo "WARNING: client {$clientId} attributes not found in activity_log\n";
    }

    $terminalId = 4;
    if (!DB::table('terminals')->where('id', $terminalId)->exists()) {
        insertWithId('terminals', [
            'id' => $terminalId,
            'business_unit_id' => $buId,
            'serial_number' => '04ad5783ad8eb14f',
            'simcard_number' => '22670000000',
            'device_alias' => 'fp20',
            'model_make' => 'feitain',
            'created_at' => '2026-07-01 12:00:00',
            'updated_at' => '2026-07-06 17:10:57',
            'deleted_at' => null,
        ]);
        echo "Restored terminals id {$terminalId}\n";
    }

    if (!DB::table('agent_terminal')->where('agent_id', $agentId)->where('terminal_id', $terminalId)->exists()) {
        DB::table('agent_terminal')->insert([
            'agent_id' => $agentId,
            'terminal_id' => $terminalId,
            'assigned_at' => '2026-07-01 12:00:00',
            'unassigned_at' => null,
            'created_at' => '2026-07-01 12:00:00',
            'updated_at' => '2026-07-06 17:10:57',
        ]);
        echo "Restored agent_terminal link agent {$agentId} -> terminal {$terminalId}\n";
    }

    foreach ([80, 81] as $accountId) {
        if (DB::table('accounts')->where('id', $accountId)->exists()) {
            continue;
        }
        $type = $accountId === 80 ? 'float' : 'commission';
        $number = $accountId === 80 ? 'AGT26070001FLOAT' : 'AGT26070001COMM';
        $balance = $accountId === 80 ? 185000.00 : 200.00;
        insertWithId('accounts', [
            'id' => $accountId,
            'business_unit_id' => $buId,
            'accountable_type' => 'App\\Models\\Agent',
            'accountable_id' => $agentId,
            'account_type' => $type,
            'account_number' => $number,
            'kato_account_code' => null,
            'member_code' => null,
            'manual_number' => null,
            'signatures_required' => null,
            'balance' => $balance,
            'currency' => 'CFA',
            'status' => 'active',
            'status_1' => null,
            'status_2' => null,
            'signature_date' => null,
            'opening_date' => '2026-07-01',
            'registration_date' => null,
            'manager_name' => null,
            'closing_date' => null,
            'description' => null,
            'created_at' => '2026-07-01 12:00:00',
            'updated_at' => '2026-07-07 09:17:41',
            'deleted_at' => null,
        ]);
        echo "Restored accounts id {$accountId}\n";
    }

    $clientAccountId = 83;
    if (!DB::table('accounts')->where('id', $clientAccountId)->exists()) {
        insertWithId('accounts', [
            'id' => $clientAccountId,
            'business_unit_id' => $buId,
            'accountable_type' => 'App\\Models\\Client',
            'accountable_id' => $clientId,
            'account_type' => 'general',
            'account_number' => '2001',
            'kato_account_code' => '001',
            'member_code' => '001',
            'manual_number' => '001',
            'signatures_required' => null,
            'balance' => 25000.00,
            'currency' => 'CFA',
            'status' => 'active',
            'status_1' => null,
            'status_2' => null,
            'signature_date' => '2026-07-01',
            'opening_date' => '2026-07-01',
            'registration_date' => '2026-07-01',
            'manager_name' => null,
            'closing_date' => null,
            'description' => null,
            'created_at' => '2026-07-01 16:26:38',
            'updated_at' => '2026-07-06 11:44:35',
            'deleted_at' => null,
        ]);
        echo "Restored client accounts id {$clientAccountId}\n";
    }

    $cardId = 11;
    if (!DB::table('cards')->where('id', $cardId)->exists()) {
        insertWithId('cards', [
            'id' => $cardId,
            'business_unit_id' => $buId,
            'account_id' => $clientAccountId,
            'card_number' => 'FAS0000001',
            'card_serial' => '5600E75B',
            'prefix' => 'FAS',
            'status' => 'active',
            'expiration_date' => '2029-12-31',
            'daily_transaction_limit' => null,
            'daily_withdrawal_limit' => null,
            'pin_hash' => null,
            'issued_at' => '2026-07-03 09:53:17',
            'activated_at' => '2026-07-03 09:53:17',
            'written_at' => '2026-07-03 10:04:48',
            'written_by' => '04ad5783ad8eb14f',
            'blocked_at' => null,
            'blocked_reason' => null,
            'replaced_by_card_id' => null,
            'replacement_reason' => null,
            'replaced_at' => null,
            'description' => null,
            'created_at' => '2026-07-03 09:53:17',
            'updated_at' => '2026-07-03 10:04:48',
            'deleted_at' => null,
        ]);
        echo "Restored cards id {$cardId}\n";
    }

    $transactionLogs = DB::table('activity_log')
        ->where('subject_type', 'like', '%Transaction%')
        ->where('description', 'created')
        ->where('properties', 'like', '%"business_unit_id": 8%')
        ->orderBy('id')
        ->get();

    foreach ($transactionLogs as $log) {
        $props = json_decode($log->properties, true);
        $attrs = $props['attributes'] ?? null;
        if (!$attrs || !isset($attrs['id'])) {
            continue;
        }
        if (DB::table('transactions')->where('id', $attrs['id'])->exists()) {
            continue;
        }
        if (isset($attrs['metadata']) && is_array($attrs['metadata'])) {
            $attrs['metadata'] = json_encode($attrs['metadata']);
        }
        $attrs = normalizeRowTimestamps($attrs, [], [
            'created_at', 'updated_at', 'deleted_at', 'transaction_date', 'kato_synced_at', 'ledger_posted_at',
        ]);
        insertWithId('transactions', $attrs);
        echo "Restored transactions id {$attrs['id']}\n";
    }

    foreach ([4, 7, 8, 9, 10] as $typeId) {
        if (!DB::table('transaction_types')->where('id', $typeId)->exists()) {
            continue;
        }
        DB::table('business_unit_transaction_type')->updateOrInsert(
            ['business_unit_id' => $buId, 'transaction_type_id' => $typeId],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
    echo "Assigned transaction types to business unit {$buId}\n";

    if (!DB::table('transaction_cost_configurations')->where('business_unit_id', $buId)->where('transaction_type_id', 7)->exists()) {
        DB::table('transaction_cost_configurations')->insert([
            'business_unit_id' => $buId,
            'transaction_type_id' => 7,
            'cost_type' => 'tiered_fixed',
            'fixed_amount' => null,
            'percentage_rate' => null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        echo "Restored transaction_cost_configurations for bu {$buId} tt 7\n";
    }

    if (!DB::table('commission_configurations')->where('business_unit_id', $buId)->where('transaction_type_id', 7)->exists()) {
        DB::table('commission_configurations')->insert([
            'business_unit_id' => $buId,
            'transaction_type_id' => 7,
            'commission_type' => 'tiered_fixed',
            'fixed_amount' => null,
            'percentage_rate' => null,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);
        echo "Restored commission_configurations for bu {$buId} tt 7\n";
    }

    DB::commit();
    echo "FASO CREDIT restore completed.\n";
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'Restore failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
