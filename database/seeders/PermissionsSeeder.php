<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Illuminate\Support\Str;

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
                'group' => 'Reports',
            ],
            [
                'name' => 'Request Agent Reports',
                'slug' => 'request-agent-reports',
                'description' => 'Can request agent reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Request Client Reports',
                'slug' => 'request-client-reports',
                'description' => 'Can request client reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Request Transaction Reports',
                'slug' => 'request-transaction-reports',
                'description' => 'Can request transaction reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Request Commission Reports',
                'slug' => 'request-commission-reports',
                'description' => 'Can request commission reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Request Cost Reports',
                'slug' => 'request-cost-reports',
                'description' => 'Can request transaction cost reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Download Reports',
                'slug' => 'download-reports',
                'description' => 'Can download completed reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Delete Reports',
                'slug' => 'delete-reports',
                'description' => 'Can delete reports',
                'group' => 'Reports',
            ],
            [
                'name' => 'Export Reports',
                'slug' => 'export-reports',
                'description' => 'Can export reports to various formats',
                'group' => 'Reports',
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
                'group' => 'Configuration',
            ],
            [
                'name' => 'Create Transaction Type',
                'slug' => 'create-transaction-type',
                'description' => 'Can create new transaction types',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Edit Transaction Type',
                'slug' => 'edit-transaction-type',
                'description' => 'Can edit transaction types',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Delete Transaction Type',
                'slug' => 'delete-transaction-type',
                'description' => 'Can delete transaction types',
                'group' => 'Configuration',
            ],
            [
                'name' => 'View Transaction Costs',
                'slug' => 'view-transaction-costs',
                'description' => 'Can view transaction cost configuration',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Create Transaction Cost',
                'slug' => 'create-transaction-cost',
                'description' => 'Can create transaction cost configurations',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Edit Transaction Cost',
                'slug' => 'edit-transaction-cost',
                'description' => 'Can edit transaction cost configurations',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Delete Transaction Cost',
                'slug' => 'delete-transaction-cost',
                'description' => 'Can delete transaction cost configurations',
                'group' => 'Configuration',
            ],
            [
                'name' => 'View Commissions',
                'slug' => 'view-commissions',
                'description' => 'Can view commission configuration',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Create Commission',
                'slug' => 'create-commission',
                'description' => 'Can create commission configurations',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Edit Commission',
                'slug' => 'edit-commission',
                'description' => 'Can edit commission configurations',
                'group' => 'Configuration',
            ],
            [
                'name' => 'Delete Commission',
                'slug' => 'delete-commission',
                'description' => 'Can delete commission configurations',
                'group' => 'Configuration',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $this->command->info('Default permissions created successfully!');
    }
}
