<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            // User Management Permissions
            [
                'name' => 'Add User',
                'slug' => 'add-user',
                'description' => 'Can create new users in the business unit',
                'group' => 'Users',
            ],
            [
                'name' => 'Edit User',
                'slug' => 'edit-user',
                'description' => 'Can edit existing users in the business unit',
                'group' => 'Users',
            ],
            [
                'name' => 'Delete User',
                'slug' => 'delete-user',
                'description' => 'Can delete users from the business unit',
                'group' => 'Users',
            ],
            [
                'name' => 'View Users',
                'slug' => 'view-users',
                'description' => 'Can view list of users in the business unit',
                'group' => 'Users',
            ],

            // Role Management Permissions
            [
                'name' => 'Create Role',
                'slug' => 'create-role',
                'description' => 'Can create new roles for the business unit',
                'group' => 'Roles',
            ],
            [
                'name' => 'Edit Role',
                'slug' => 'edit-role',
                'description' => 'Can edit existing roles in the business unit',
                'group' => 'Roles',
            ],
            [
                'name' => 'Delete Role',
                'slug' => 'delete-role',
                'description' => 'Can delete roles from the business unit',
                'group' => 'Roles',
            ],
            [
                'name' => 'View Roles',
                'slug' => 'view-roles',
                'description' => 'Can view list of roles in the business unit',
                'group' => 'Roles',
            ],

            // Business Unit Permissions
            [
                'name' => 'View Business Unit',
                'slug' => 'view-business-unit',
                'description' => 'Can view business unit details',
                'group' => 'Business Unit',
            ],
            [
                'name' => 'Edit Business Unit',
                'slug' => 'edit-business-unit',
                'description' => 'Can edit business unit information',
                'group' => 'Business Unit',
            ],

            // Dashboard Permissions
            [
                'name' => 'View Dashboard',
                'slug' => 'view-dashboard',
                'description' => 'Can access the business unit dashboard',
                'group' => 'Dashboard',
            ],

            // Reports Permissions
            [
                'name' => 'View Reports',
                'slug' => 'view-reports',
                'description' => 'Can view reports and analytics',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Request Agent Reports',
                'slug' => 'request-agent-reports',
                'description' => 'Can request agent reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Request Client Reports',
                'slug' => 'request-client-reports',
                'description' => 'Can request client reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Request Transaction Reports',
                'slug' => 'request-transaction-reports',
                'description' => 'Can request transaction reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Request Commission Reports',
                'slug' => 'request-commission-reports',
                'description' => 'Can request commission reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Request Cost Reports',
                'slug' => 'request-cost-reports',
                'description' => 'Can request transaction cost reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Download Reports',
                'slug' => 'download-reports',
                'description' => 'Can download completed reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Delete Reports',
                'slug' => 'delete-reports',
                'description' => 'Can delete reports',
                'group' => 'Banking Reports',
            ],
            [
                'name' => 'Export Reports',
                'slug' => 'export-reports',
                'description' => 'Can export reports to various formats',
                'group' => 'Banking Reports',
            ],

            // Card Management Permissions
            [
                'name' => 'View Cards',
                'slug' => 'view-cards',
                'description' => 'Can view list of cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'Create Card',
                'slug' => 'create-card',
                'description' => 'Can create new cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'View Card',
                'slug' => 'view-card',
                'description' => 'Can view card details',
                'group' => 'Cards',
            ],
            [
                'name' => 'Edit Card',
                'slug' => 'edit-card',
                'description' => 'Can edit card details',
                'group' => 'Cards',
            ],
            [
                'name' => 'Delete Card',
                'slug' => 'delete-card',
                'description' => 'Can delete cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'Activate Card',
                'slug' => 'activate-card',
                'description' => 'Can activate cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'Block Card',
                'slug' => 'block-card',
                'description' => 'Can block cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'Replace Card',
                'slug' => 'replace-card',
                'description' => 'Can replace cards',
                'group' => 'Cards',
            ],
            [
                'name' => 'Manage Card Settings',
                'slug' => 'manage-card-settings',
                'description' => 'Can manage card prefix and settings',
                'group' => 'Cards',
            ],

            // SMS Management Permissions
            [
                'name' => 'View SMS',
                'slug' => 'view-sms',
                'description' => 'Can view SMS balance and transaction history',
                'group' => 'SMS',
            ],
            [
                'name' => 'Send Single SMS',
                'slug' => 'send-single-sms',
                'description' => 'Can send single SMS to one client',
                'group' => 'SMS',
            ],
            [
                'name' => 'Send Bulk SMS',
                'slug' => 'send-bulk-sms',
                'description' => 'Can send bulk SMS to multiple clients',
                'group' => 'SMS',
            ],
            [
                'name' => 'View SMS History',
                'slug' => 'view-sms-history',
                'description' => 'Can view SMS message history',
                'group' => 'SMS',
            ],
            [
                'name' => 'Load SMS Credits',
                'slug' => 'load-sms-credits',
                'description' => 'Can load SMS credits to business units',
                'group' => 'SMS',
            ],
            [
                'name' => 'Configure SMS',
                'slug' => 'configure-sms',
                'description' => 'Can configure SMS API credentials and settings',
                'group' => 'SMS',
            ],
            [
                'name' => 'View All SMS Balances',
                'slug' => 'view-all-sms-balances',
                'description' => 'Can view SMS balances for all business units',
                'group' => 'SMS',
            ],

            // Clients Permissions
            [
                'name' => 'View Clients',
                'slug' => 'view-clients',
                'description' => 'Can view list of clients',
                'group' => 'Clients',
            ],
            [
                'name' => 'Create Client',
                'slug' => 'create-client',
                'description' => 'Can create new clients',
                'group' => 'Clients',
            ],
            [
                'name' => 'Edit Client',
                'slug' => 'edit-client',
                'description' => 'Can edit existing clients',
                'group' => 'Clients',
            ],
            [
                'name' => 'Delete Client',
                'slug' => 'delete-client',
                'description' => 'Can delete clients',
                'group' => 'Clients',
            ],

            // Agents Permissions
            [
                'name' => 'View Agents',
                'slug' => 'view-agents',
                'description' => 'Can view list of agents',
                'group' => 'Agents',
            ],
            [
                'name' => 'Create Agent',
                'slug' => 'create-agent',
                'description' => 'Can create new agents',
                'group' => 'Agents',
            ],
            [
                'name' => 'Edit Agent',
                'slug' => 'edit-agent',
                'description' => 'Can edit existing agents',
                'group' => 'Agents',
            ],
            [
                'name' => 'Delete Agent',
                'slug' => 'delete-agent',
                'description' => 'Can delete agents',
                'group' => 'Agents',
            ],
            [
                'name' => 'Manage Agent Card Rights',
                'slug' => 'manage-agent-card-rights',
                'description' => 'Can grant or revoke card write / rewrite rights on the TPE',
                'group' => 'Agents',
            ],

            // Accounts Permissions
            [
                'name' => 'View Accounts',
                'slug' => 'view-accounts',
                'description' => 'Can view list of accounts',
                'group' => 'Accounts',
            ],
            [
                'name' => 'Create Account',
                'slug' => 'create-account',
                'description' => 'Can create new accounts',
                'group' => 'Accounts',
            ],
            [
                'name' => 'Edit Account',
                'slug' => 'edit-account',
                'description' => 'Can edit existing accounts',
                'group' => 'Accounts',
            ],
            [
                'name' => 'Delete Account',
                'slug' => 'delete-account',
                'description' => 'Can delete accounts',
                'group' => 'Accounts',
            ],

            // Terminals Permissions
            [
                'name' => 'View Terminals',
                'slug' => 'view-terminals',
                'description' => 'Can view list of terminals',
                'group' => 'Terminals',
            ],
            [
                'name' => 'Create Terminal',
                'slug' => 'create-terminal',
                'description' => 'Can create new terminals',
                'group' => 'Terminals',
            ],
            [
                'name' => 'Edit Terminal',
                'slug' => 'edit-terminal',
                'description' => 'Can edit existing terminals',
                'group' => 'Terminals',
            ],
            [
                'name' => 'Delete Terminal',
                'slug' => 'delete-terminal',
                'description' => 'Can delete terminals',
                'group' => 'Terminals',
            ],

            // Transactions Permissions
            [
                'name' => 'View Transactions',
                'slug' => 'view-transactions',
                'description' => 'Can view list of transactions',
                'group' => 'Transactions',
            ],
            [
                'name' => 'Create Transaction',
                'slug' => 'create-transaction',
                'description' => 'Can create new transactions',
                'group' => 'Transactions',
            ],
            [
                'name' => 'View Transaction',
                'slug' => 'view-transaction',
                'description' => 'Can view transaction details',
                'group' => 'Transactions',
            ],
            [
                'name' => 'API Create Transaction',
                'slug' => 'api-create-transaction',
                'description' => 'Can create transactions via API',
                'group' => 'Transactions',
            ],

            // CNSS (voluntary enrollments & contributions)
            [
                'name' => 'View CNSS Members',
                'slug' => 'view-cnss-members',
                'description' => 'Can view CNSS voluntary enrollment list for the business unit',
                'group' => 'CNSS',
            ],
            [
                'name' => 'View CNSS Member',
                'slug' => 'view-cnss-member',
                'description' => 'Can view a CNSS voluntary enrollment and its contributions',
                'group' => 'CNSS',
            ],
            [
                'name' => 'Edit CNSS Member',
                'slug' => 'edit-cnss-member',
                'description' => 'Can edit CNSS voluntary enrollments for the business unit',
                'group' => 'CNSS',
            ],
            [
                'name' => 'Delete CNSS Member',
                'slug' => 'delete-cnss-member',
                'description' => 'Can delete CNSS voluntary enrollments when they have no contributions',
                'group' => 'CNSS',
            ],

            // Audit Trail Permissions
            [
                'name' => 'View Audit Trail',
                'slug' => 'view-audit-trail',
                'description' => 'Can view audit trail logs',
                'group' => 'Audit Trail',
            ],
            [
                'name' => 'View Audit Details',
                'slug' => 'view-audit-details',
                'description' => 'Can view detailed audit log entries',
                'group' => 'Audit Trail',
            ],
            [
                'name' => 'Export Audit Trail',
                'slug' => 'export-audit-trail',
                'description' => 'Can export audit logs',
                'group' => 'Audit Trail',
            ],

            // Configuration Permissions
            [
                'name' => 'View Transaction Types',
                'slug' => 'view-transaction-types',
                'description' => 'Can view transaction types configuration',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Create Transaction Type',
                'slug' => 'create-transaction-type',
                'description' => 'Can create new transaction types',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Edit Transaction Type',
                'slug' => 'edit-transaction-type',
                'description' => 'Can edit transaction types',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Delete Transaction Type',
                'slug' => 'delete-transaction-type',
                'description' => 'Can delete transaction types',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'View Transaction Costs',
                'slug' => 'view-transaction-costs',
                'description' => 'Can view transaction cost configuration',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Create Transaction Cost',
                'slug' => 'create-transaction-cost',
                'description' => 'Can create transaction cost configurations',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Edit Transaction Cost',
                'slug' => 'edit-transaction-cost',
                'description' => 'Can edit transaction cost configurations',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Delete Transaction Cost',
                'slug' => 'delete-transaction-cost',
                'description' => 'Can delete transaction cost configurations',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'View Commissions',
                'slug' => 'view-commissions',
                'description' => 'Can view commission configuration',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Create Commission',
                'slug' => 'create-commission',
                'description' => 'Can create commission configurations',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Edit Commission',
                'slug' => 'edit-commission',
                'description' => 'Can edit commission configurations',
                'group' => 'Banking Configuration',
            ],
            [
                'name' => 'Delete Commission',
                'slug' => 'delete-commission',
                'description' => 'Can delete commission configurations',
                'group' => 'Banking Configuration',
            ],
        ];

        foreach (array_merge($permissions, self::sunuPermissions()) as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $this->command->info('Default permissions created successfully!');
    }

    /**
     * SUNU web back office permissions (proposals, policies, premiums, pay-ins,
     * products / import, SUNU reports). Also used by the migration that grants
     * them to existing roles.
     */
    public static function sunuPermissions(): array
    {
        return [
            // SUNU Proposals
            [
                'name' => 'View SUNU Proposals',
                'slug' => 'view-sunu-proposals',
                'description' => 'Can view subscription proposals sent by the TPE, with their photos',
                'group' => 'SUNU Proposals',
            ],
            [
                'name' => 'Accept SUNU Proposal',
                'slug' => 'accept-sunu-proposal',
                'description' => 'Can accept a proposal and create its policy',
                'group' => 'SUNU Proposals',
            ],
            [
                'name' => 'Link SUNU Card',
                'slug' => 'link-sunu-card',
                'description' => 'Can link a customer card to a proposal or a policy',
                'group' => 'SUNU Proposals',
            ],

            // SUNU Policies
            [
                'name' => 'View SUNU Policies',
                'slug' => 'view-sunu-policies',
                'description' => 'Can view the policies list',
                'group' => 'SUNU Policies',
            ],

            // SUNU Premiums
            [
                'name' => 'View SUNU Premiums',
                'slug' => 'view-sunu-premiums',
                'description' => 'Can view premiums collected on the TPE',
                'group' => 'SUNU Premiums',
            ],
            [
                'name' => 'Export SUNU Premiums',
                'slug' => 'export-sunu-premiums',
                'description' => 'Can export the premiums list',
                'group' => 'SUNU Premiums',
            ],

            // SUNU Pay-ins
            [
                'name' => 'View SUNU Pay-ins',
                'slug' => 'view-sunu-payins',
                'description' => 'Can view collector pay-ins',
                'group' => 'SUNU Pay-ins',
            ],
            [
                'name' => 'Validate SUNU Pay-ins',
                'slug' => 'validate-sunu-payins',
                'description' => 'Can confirm or reject collector pay-ins',
                'group' => 'SUNU Pay-ins',
            ],
            [
                'name' => 'Manage SUNU Collector Limits',
                'slug' => 'manage-sunu-collector-caps',
                'description' => 'Can set the collection limit (default and per agent) and release a blocked TPE',
                'group' => 'SUNU Pay-ins',
            ],

            // SUNU Corrections (collector error reports from the TPE)
            [
                'name' => 'View SUNU Correction Requests',
                'slug' => 'view-sunu-corrections',
                'description' => 'Can view collector correction requests and their history',
                'group' => 'SUNU Corrections',
            ],
            [
                'name' => 'Approve SUNU Correction Requests',
                'slug' => 'approve-sunu-corrections',
                'description' => 'Can approve or reject a collector correction request (decision only)',
                'group' => 'SUNU Corrections',
            ],
            [
                'name' => 'Apply SUNU Corrections',
                'slug' => 'apply-sunu-corrections',
                'description' => 'Can cancel / correct a premium or subscription for an approved request',
                'group' => 'SUNU Corrections',
            ],

            // Configuration (SUNU)
            [
                'name' => 'View SUNU Products',
                'slug' => 'view-sunu-products',
                'description' => 'Can view insurance products',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Manage SUNU Products',
                'slug' => 'manage-sunu-products',
                'description' => 'Can create, edit, activate or deactivate insurance products',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Import SUNU Policies',
                'slug' => 'import-sunu-policies',
                'description' => 'Can import SUNULIFE policies from a CSV file (Phase 0)',
                'group' => 'Configuration',
            ],

            // Reports (SUNU)
            [
                'name' => 'Premiums Report',
                'slug' => 'view-sunu-report-premiums',
                'description' => 'Can view the collected premiums report',
                'group' => 'Reports',
            ],
            [
                'name' => 'Reconciliation Report',
                'slug' => 'view-sunu-report-reconciliation',
                'description' => 'Can view the per-agent reconciliation report',
                'group' => 'Reports',
            ],
            [
                'name' => 'Subscriptions Report',
                'slug' => 'view-sunu-report-subscriptions',
                'description' => 'Can view the subscriptions and overdue policies report',
                'group' => 'Reports',
            ],
            [
                'name' => 'Cards Report',
                'slug' => 'view-sunu-report-cards',
                'description' => 'Can view the cards report',
                'group' => 'Reports',
            ],
            [
                'name' => 'Export SUNU Reports',
                'slug' => 'export-sunu-reports',
                'description' => 'Can export SUNU reports to CSV / Excel',
                'group' => 'Reports',
            ],
        ];
    }
}
