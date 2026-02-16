# Agency Banking Management System
## User Manual

**Version:** 1.0  
**Date:** January 2026  
**Language:** English

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Super Admin Features](#super-admin-features)
4. [Business Unit Features](#business-unit-features)
5. [User Management](#user-management)
6. [Client Management](#client-management)
7. [Agent Management](#agent-management)
8. [Terminal Management](#terminal-management)
9. [Account Management](#account-management)
10. [Transaction Management](#transaction-management)
11. [Card Management](#card-management)
12. [SMS Management](#sms-management)
13. [Reports](#reports)
14. [Configuration](#configuration)
15. [Audit Trail](#audit-trail)
16. [System Settings](#system-settings)
17. [Troubleshooting](#troubleshooting)
18. [Appendix](#appendix)

---

## 1. Introduction

### 1.1 Overview

The Agency Banking Management System is a comprehensive web-based application designed to manage agency banking operations. The system supports multiple business units, user management with role-based permissions, client management, transaction processing, SMS communication, and comprehensive reporting.

### 1.2 Key Features

- **Multi-tenant Architecture**: Support for multiple business units with isolated data
- **Role-Based Access Control**: Granular permissions system
- **Client Management**: Complete client lifecycle management with grouping capabilities
- **Transaction Processing**: Full transaction management and tracking
- **SMS Integration**: Send single or bulk SMS messages to clients
- **Reporting System**: Comprehensive reports for agents, clients, transactions, commissions, and costs
- **Card Management**: Card creation, activation, blocking, and replacement
- **Audit Trail**: Complete activity logging
- **Multi-language Support**: English, French, and Portuguese
- **Theme Support**: Dark and light themes

### 1.3 System Requirements

- Modern web browser (Chrome, Firefox, Safari, Edge)
- Internet connection
- Valid user credentials

---

## 2. Getting Started

### 2.1 Logging In

1. Open your web browser and navigate to the application URL
2. Enter your **Email Address** and **Password**
3. Optionally check "Remember Me" to stay logged in
4. Click **Login**

**Note**: If you forget your password, contact your system administrator.

### 2.2 Dashboard Overview

After logging in, you will be redirected to your dashboard:

- **Super Admin Dashboard**: Shows all business units with user counts
- **Business Unit Dashboard**: Shows business unit information, statistics, and quick actions

### 2.3 Navigation

The application uses a sidebar navigation menu:

- Click on menu items to navigate to different sections
- Menu items with arrows (▼) can be expanded to show sub-items
- The active page is highlighted in the menu
- Some menu items may be hidden based on your permissions

### 2.4 Language Selection

1. Click on the language selector in the top header
2. Select your preferred language:
   - English
   - Français (French)
   - Português (Portuguese)
3. The interface will immediately update to the selected language

### 2.5 Theme Selection

1. Click the theme toggle button (☀️/🌙) in the top header
2. Switch between **Light** and **Dark** themes
3. Your preference is saved automatically

### 2.6 Logging Out

1. Click on your name in the top header
2. Select **Logout** from the dropdown menu

---

## 3. Super Admin Features

Super Admin users have access to system-wide management features. These features are only available to users with Super Admin privileges.

### 3.1 Dashboard

The Super Admin dashboard displays:
- List of all business units
- User count for each business unit
- Quick actions to create new business units

### 3.2 Business Units Management

#### 3.2.1 Viewing Business Units

1. Navigate to **Business Units** from the sidebar
2. View the list of all business units
3. Each business unit displays:
   - Name
   - Status (Active/Inactive)
   - User count
   - Actions (Edit, View Users)

#### 3.2.2 Creating a Business Unit

1. Click **Create Business Unit** button
2. Fill in the required information:
   - **Name** (required): Business unit name
   - **Description**: Brief description
   - **Email**: Contact email
   - **Phone**: Contact phone number
   - **Address**: Physical address
   - **Account**: Account number
   - **Tax Number**: Tax identification number
   - **Mailing Address**: Mailing address
   - **Relation Manager Phone**: Phone number of relationship manager
   - **Relation Manager Email**: Email of relationship manager
   - **SMS Sender Number**: Format: `tel:+[country code][number]` (e.g., `tel:+22676396790`)
   - **Logo**: Upload business unit logo (optional)
3. Click **Create**

#### 3.2.3 Editing a Business Unit

1. Navigate to **Business Units**
2. Click **Edit** on the desired business unit
3. Modify the information as needed
4. Click **Update**

#### 3.2.4 Managing Business Unit Users

1. Navigate to **Business Units**
2. Click on a business unit name or **View Users**
3. View users assigned to the business unit
4. To add a user:
   - Click **Add User**
   - Select a user from the dropdown
   - Assign a role
   - Click **Add**

### 3.3 User Management (Global)

Super Admins can manage all users across all business units.

#### 3.3.1 Viewing Users

1. Navigate to **User Management > Users**
2. View the list of all users in the system
3. Filter by business unit if needed

#### 3.3.2 Creating a User

1. Click **Add User**
2. Fill in the user information:
   - **Name**: Full name
   - **Email**: Email address (must be unique)
   - **Password**: Secure password
   - **Preferred Language**: English, French, or Portuguese
   - **Is Super Admin**: Check to grant super admin privileges
   - **Business Units**: Select one or more business units
   - **Roles**: Assign roles for each business unit
3. Click **Create**

#### 3.3.3 Editing a User

1. Navigate to **User Management > Users**
2. Click **Edit** on the desired user
3. Modify information as needed
4. Click **Update**

### 3.4 Roles Management (Global)

#### 3.4.1 Viewing Roles

1. Navigate to **User Management > Roles**
2. View all roles in the system
3. See permissions and user assignments for each role

#### 3.4.2 Creating a Role

1. Click **Create Role**
2. Enter role details:
   - **Name**: Role name
   - **Description**: Role description
   - **Business Unit**: Select a business unit (optional for global roles)
   - **Permissions**: Select permissions to assign
3. Click **Create**

### 3.5 Permissions Management

#### 3.5.1 Viewing Permissions

1. Navigate to **User Management > Permissions**
2. View all permissions grouped by category
3. See permission name, slug, description, and group

#### 3.5.2 Creating a Permission

1. Click **Create Permission**
2. Enter permission details:
   - **Name**: Permission name
   - **Description**: Permission description
   - **Group**: Permission group (for organization)
3. Click **Create**

### 3.6 SMS Configuration

Super Admins can configure the SMS API settings.

#### 3.6.1 Viewing SMS Configuration

1. Navigate to **SMS Management > SMS Configuration**
2. View current configuration:
   - Client ID
   - API Endpoint
   - Sender Name
   - Status (Active/Inactive)

#### 3.6.2 Creating/Editing SMS Configuration

1. Click **Edit** or **Create Configuration**
2. Enter SMS API details:
   - **Client ID**: SMS API client ID (required)
   - **Client Secret**: SMS API client secret (required for creation)
   - **API Endpoint**: API endpoint URL
   - **Sender Name**: Default sender name
   - **Is Active**: Enable/disable configuration
3. Click **Save** or **Update**

#### 3.6.3 Testing SMS Configuration

1. In the SMS Configuration page, click **Test Configuration**
2. Enter a test phone number
3. Click **Test**
4. Verify the configuration is working correctly

### 3.7 Loading SMS Credits

Super Admins can load SMS credits to business units.

1. Navigate to **SMS Management > Load SMS Credits**
2. Select the target business unit from the dropdown
3. Enter the amount of credits to load
4. Enter a description (optional)
5. Click **Load Credits**

### 3.8 Viewing All Business Balances

1. Navigate to **SMS Management > All Businesses Balance**
2. View SMS balance for all business units
3. See current balance, total used, and total credited for each unit

### 3.9 Switching Business Units

Super Admins can switch to view a business unit's perspective:

1. Navigate to a business unit's page
2. Click **Switch to this Business Unit**
3. The interface will switch to show the business unit's menu and data
4. To switch back, navigate to Super Admin dashboard

---

## 4. Business Unit Features

Business unit users have access to features specific to their assigned business unit.

### 4.1 Dashboard

The Business Unit Dashboard displays:
- Business unit information (name, logo, contact details)
- Quick access to main features
- Statistics and summaries

### 4.2 User Management (Business Unit Level)

#### 4.2.1 Viewing Users

1. Navigate to **User Management > Users**
2. View all users assigned to your business unit
3. See user roles and status

#### 4.2.2 Adding Users

1. Click **Add User**
2. Enter user information:
   - **Name**: Full name
   - **Email**: Email address
   - **Password**: Secure password
   - **Role**: Select a role for this business unit
3. Click **Create**

**Note**: Users must be created by Super Admin first, then assigned to business units.

#### 4.2.3 Editing Users

1. Click **Edit** on a user
2. Modify information and role
3. Click **Update**

### 4.3 Roles Management (Business Unit Level)

Business units can create and manage their own roles.

#### 4.3.1 Viewing Roles

1. Navigate to **User Management > Roles**
2. View all roles for your business unit
3. See permissions and user assignments

#### 4.3.2 Creating a Role

1. Click **Create Role**
2. Enter role details:
   - **Name**: Role name
   - **Description**: Role description
   - **Permissions**: Select permissions (super admin permissions are excluded)
3. Click **Create**

#### 4.3.3 Editing a Role

1. Click **Edit** on a role
2. Modify name, description, or permissions
3. Click **Update**

---

## 5. Client Management

### 5.1 Viewing Clients

1. Navigate to **Clients > Clients**
2. View the list of all clients in your business unit
3. Use the search box to filter clients by:
   - Name
   - Email
   - Phone number
   - ID/Passport number

### 5.2 Creating a Client

1. Click **Add Client**
2. Fill in client information:

   **Personal Information:**
   - **Salutation**: Mr., Mrs., Ms., Dr., etc.
   - **First Name** (required)
   - **Middle Name**
   - **Last Name** (required)
   - **Email**
   - **ID/Passport Number**
   - **Home Address**
   - **Phone Number**
   - **Photo**: Upload client photo
   - **Gender**: Male, Female, Other
   - **Marital Status**: Single, Married, Divorced, Widowed
   - **Year of Birth**
   - **Region**
   - **Home Town**
   - **Nationality**

   **Next of Kin Information:**
   - **First Name**
   - **Middle Name**
   - **Last Name**
   - **Phone Number**
   - **Address**
   - **Gender**

3. Click **Create**

### 5.3 Editing a Client

1. Navigate to **Clients > Clients**
2. Click **Edit** on the desired client
3. Modify information as needed
4. Click **Update**

### 5.4 Viewing Client Details

1. Click on a client's name or **View**
2. View complete client information including:
   - Personal details
   - Next of kin information
   - Associated accounts
   - Transaction history

### 5.5 Deleting a Client

1. Navigate to the client's detail page
2. Click **Delete**
3. Confirm deletion

**Warning**: Deleting a client may affect associated transactions and accounts.

### 5.6 Exporting Clients

1. Navigate to **Clients > Clients**
2. Apply any filters if needed
3. Click the **Export** button (📥)
4. Select export format:
   - **CSV**: Comma-separated values
   - **Excel**: Microsoft Excel format (.xlsx)
   - **PDF**: Portable Document Format

### 5.7 Client Groups

Client groups allow you to organize clients into categories for easier management and bulk SMS sending.

#### 5.7.1 Viewing Client Groups

1. Navigate to **Clients > Client Groups**
2. View all client groups for your business unit
3. See group name, description, and member count

#### 5.7.2 Creating a Client Group

1. Click **Create Client Group**
2. Enter group information:
   - **Group Name** (required): e.g., "VIP Clients", "Regular Customers"
   - **Description**: Optional description
3. Select clients to add to the group:
   - Use the search box to filter clients
   - Check the boxes next to clients to add
   - Use "Select All" or "Deselect All" buttons
4. Click **Create**

**Note**: A client can belong to multiple groups.

#### 5.7.3 Editing a Client Group

1. Click **Edit** on a client group
2. Modify group name or description
3. Add or remove clients from the group
4. Click **Update**

#### 5.7.4 Viewing Group Details

1. Click **View** on a client group
2. See group information and all members
3. View member details: name, phone, email

#### 5.7.5 Deleting a Client Group

1. Click **Delete** on a client group
2. Confirm deletion

**Note**: Deleting a group does not delete the clients, only removes the group association.

---

## 6. Agent Management

### 6.1 Viewing Agents

1. Navigate to **Agents**
2. View the list of all agents in your business unit
3. Search and filter agents as needed

### 6.2 Creating an Agent

1. Click **Add Agent**
2. Fill in agent information:
   - Personal information (similar to client fields)
   - Username
   - Physical address
   - P.O. Box
3. Click **Create**

### 6.3 Editing an Agent

1. Click **Edit** on an agent
2. Modify information as needed
3. Click **Update**

### 6.4 Viewing Agent Details

1. Click on an agent's name
2. View complete agent information including:
   - Personal details
   - Assigned terminals
   - Transaction history
   - Commission information

### 6.5 Assigning Terminals to Agents

1. Navigate to agent details page
2. Go to the **Terminals** tab
3. Click **Assign Terminal**
4. Select a terminal from the dropdown
5. Click **Assign**

### 6.6 Unassigning Terminals

1. Navigate to agent details page
2. Go to the **Terminals** tab
3. Click **Unassign** next to the terminal
4. Confirm unassignment

### 6.7 Exporting Agents

1. Navigate to **Agents**
2. Apply filters if needed
3. Click the **Export** button
4. Select export format (CSV, Excel, or PDF)

---

## 7. Terminal Management

### 7.1 Viewing Terminals

1. Navigate to **Terminals**
2. View the list of all terminals in your business unit
3. See terminal status and assigned agent

### 7.2 Creating a Terminal

1. Click **Add Terminal**
2. Fill in terminal information:
   - **Serial Number**: Terminal serial number
   - **SimCard Number**: SIM card number
   - **Location**: Terminal location
   - **Status**: Active or Inactive
3. Click **Create**

### 7.3 Editing a Terminal

1. Click **Edit** on a terminal
2. Modify information as needed
3. Click **Update**

### 7.4 Viewing Terminal Details

1. Click on a terminal's serial number
2. View complete terminal information including:
   - Terminal details
   - Assigned agent
   - Transaction history

### 7.5 Exporting Terminals

1. Navigate to **Terminals**
2. Apply filters if needed
3. Click the **Export** button
4. Select export format (CSV, Excel, or PDF)

---

## 8. Account Management

### 8.1 Viewing Accounts

1. Navigate to **Accounts**
2. View the list of all accounts in your business unit
3. See account numbers, types, balances, and owners

### 8.2 Creating an Account

1. Click **Add Account**
2. Fill in account information:
   - **Account Number**: Leave blank for auto-generation
   - **Account Type**: General, Agent, or Terminal
   - **Owner**: Select client, agent, or terminal
   - **Initial Balance**: Starting balance
   - **Currency**: Account currency
3. Click **Create**

### 8.3 Creating an Account for a Client

1. Navigate to **Clients > Clients**
2. Click on a client's name
3. Go to the **Accounts** tab
4. Click **Add Account**
5. Fill in account details
6. Click **Create**

### 8.4 Editing an Account

1. Click **Edit** on an account
2. Modify information as needed
3. Click **Update**

### 8.5 Viewing Account Details

1. Click on an account number
2. View complete account information including:
   - Account details
   - Owner information
   - Transaction history
   - Current balance

### 8.6 Exporting Accounts

1. Navigate to **Accounts**
2. Apply filters if needed
3. Click the **Export** button
4. Select export format (CSV, Excel, or PDF)

---

## 9. Transaction Management

### 9.1 Viewing Transactions

1. Navigate to **Transactions**
2. View the list of all transactions in your business unit
3. Filter transactions by:
   - Date range
   - Transaction type
   - Status
   - Agent
   - Terminal
   - Account

### 9.2 Creating a Transaction

1. Click **Add Transaction**
2. Fill in transaction details:
   - **Transaction Type**: Select from configured types
   - **Account**: Source account
   - **Amount**: Transaction amount
   - **Description**: Transaction description
   - **Agent**: Associated agent (if applicable)
   - **Terminal**: Associated terminal (if applicable)
3. Click **Create**

### 9.3 Viewing Transaction Details

1. Click on a transaction ID or **View**
2. View complete transaction information including:
   - Transaction details
   - Account information
   - Commission calculations
   - Cost calculations
   - Associated agent and terminal

### 9.4 Exporting Transactions

1. Navigate to **Transactions**
2. Apply filters if needed
3. Click the **Export** button
4. Select export format (CSV, Excel, or PDF)

---

## 10. Card Management

### 10.1 Viewing Cards

1. Navigate to **Cards > List Cards**
2. View the list of all cards in your business unit
3. See card numbers, status, and associated accounts

### 10.2 Creating a Card

1. Navigate to **Cards > Add Card**
2. Fill in card information:
   - **Card Number**: Leave blank for auto-generation
   - **Account**: Select the account to link the card
   - **Cardholder Name**: Name on the card
   - **Expiry Date**: Card expiration date
   - **CVV**: Card security code
3. Click **Create**

### 10.3 Viewing Card Details

1. Click on a card number
2. View complete card information including:
   - Card details
   - Linked account
   - Transaction history
   - Status history

### 10.4 Activating a Card

1. Navigate to card details page
2. Click **Activate Card**
3. Confirm activation

### 10.5 Blocking a Card

1. Navigate to card details page
2. Click **Block Card**
3. Confirm blocking

### 10.6 Replacing a Card

1. Navigate to card details page
2. Click **Replace Card**
3. Confirm replacement
4. A new card will be generated

### 10.7 Editing a Card

1. Navigate to card details page
2. Click **Edit**
3. Modify information as needed
4. Click **Update**

### 10.8 Card Settings

Manage card prefix and other card-related settings:

1. Navigate to **Cards > Card Settings**
2. Configure settings:
   - **Card Prefix**: Prefix for card number generation
   - Other card settings
3. Click **Update Settings**

---

## 11. SMS Management

### 11.1 SMS Dashboard

1. Navigate to **SMS Management > SMS Dashboard**
2. View:
   - Current SMS balance
   - Total credits used
   - Total credits loaded
   - Transaction history
3. Access quick actions to send SMS

### 11.2 Sending a Single SMS

1. Navigate to **SMS Management > Send Single SMS** or click the button on the dashboard
2. Verify sender number is configured
3. Select a client from the dropdown
4. Enter your message (maximum 160 characters)
5. Preview the message and estimated cost
6. Click **Send SMS**

### 11.3 Sending Bulk SMS

#### 11.3.1 Using Client Groups

1. Navigate to **SMS Management > Send Bulk SMS**
2. Verify sender number and balance
3. **Select Groups** (optional):
   - Check the boxes next to groups to select
   - All clients in selected groups will be automatically selected
4. **Select Individual Clients** (optional):
   - Use search to filter clients
   - Check boxes to select specific clients
   - You can combine groups and individual clients
5. Enter your message (maximum 160 characters)
6. View selected recipient count and estimated cost
7. Click **Send Bulk SMS**

**Note**: 
- Selecting a group automatically selects all its members
- You can still deselect individual clients even if their group is selected
- Duplicate clients are automatically handled

#### 11.3.2 Selecting Individual Clients Only

1. Skip the group selection section
2. Select individual clients using checkboxes
3. Use "Select All" or "Deselect All" for convenience
4. Enter message and send

### 11.4 Viewing SMS History

1. Navigate to **SMS Management > SMS History**
2. View all sent SMS messages
3. Filter by:
   - Status (Pending, Sent, Failed, Delivered)
   - Client
   - Date range
4. Click on a message to view details

### 11.5 SMS Transaction History

View SMS credit transactions on the SMS Dashboard:
- Credits loaded
- Credits used (when SMS are sent)
- Balance after each transaction
- User who performed the action
- Date and time

---

## 12. Reports

The reporting system allows you to generate various reports for analysis and record-keeping.

### 12.1 Reports Dashboard

1. Navigate to **Reports**
2. View all available reports
3. See report status: Pending, Processing, Completed, Failed

### 12.2 Requesting a Report

#### 12.2.1 Agent Reports

1. Navigate to **Reports > Agent Reports**
2. Select filters:
   - Date range
   - Agent
   - Status
3. Click **Request Report**
4. The report will be generated in the background
5. You'll be notified when it's ready

#### 12.2.2 Client Reports

1. Navigate to **Reports > Client Reports**
2. Select filters:
   - Date range
   - Client
   - Status
3. Click **Request Report**

#### 12.2.3 Transaction Reports

1. Navigate to **Reports > Transaction Reports**
2. Select filters:
   - Date range
   - Transaction type
   - Agent
   - Terminal
3. Click **Request Report**

#### 12.2.4 Commission Reports

1. Navigate to **Reports > Commission Reports**
2. Select filters:
   - Date range
   - Agent
   - Transaction type
3. Click **Request Report**

#### 12.2.5 Cost Reports

1. Navigate to **Reports > Cost Reports**
2. Select filters:
   - Date range
   - Transaction type
   - Terminal
3. Click **Request Report**

### 12.3 Downloading Reports

1. Navigate to **Reports**
2. Find your completed report
3. Click **Download**
4. The report will download in PDF format

### 12.4 Retrying Failed Reports

1. Navigate to **Reports**
2. Find a failed report
3. Click **Retry**
4. The report will be regenerated

### 12.5 Deleting Reports

1. Navigate to **Reports**
2. Find the report you want to delete
3. Click **Delete**
4. Confirm deletion

---

## 13. Configuration

Business unit administrators can configure various system settings.

### 13.1 Transaction Types

Transaction types define the types of transactions that can be processed.

#### 13.1.1 Viewing Transaction Types

1. Navigate to **Configuration > Transaction Types**
2. View all configured transaction types
3. See type name, code, and status

#### 13.1.2 Creating a Transaction Type

1. Click **Add Transaction Type**
2. Enter:
   - **Name**: Transaction type name
   - **Code**: Unique code
   - **Description**: Description
   - **Is Active**: Enable/disable
3. Click **Create**

#### 13.1.3 Editing a Transaction Type

1. Click **Edit** on a transaction type
2. Modify information
3. Click **Update**

**Note**: Transaction type cannot be changed after creation if it has associated transactions.

### 13.2 Transaction Costs

Configure cost structures for transactions.

#### 13.2.1 Viewing Transaction Costs

1. Navigate to **Configuration > Transaction Costs**
2. View all cost configurations
3. See transaction type, cost structure, and status

#### 13.2.2 Creating a Transaction Cost Configuration

1. Click **Add Transaction Cost**
2. Select:
   - **Transaction Type**: Associated transaction type
   - **Cost Type**: Fixed or Percentage
   - **Amount/Rate**: Cost amount or percentage
3. Configure tiers if needed:
   - Click **Add Tier**
   - Set minimum amount, maximum amount, and rate
   - Add multiple tiers for tiered pricing
4. Click **Create**

#### 13.2.3 Editing Transaction Costs

1. Click **Edit** on a cost configuration
2. Modify cost structure and tiers
3. Click **Update**

### 13.3 Commission Configuration

Configure commission structures for agents.

#### 13.3.1 Viewing Commissions

1. Navigate to **Configuration > Commissions**
2. View all commission configurations
3. See transaction type, commission structure, and status

#### 13.3.2 Creating a Commission Configuration

1. Click **Add Commission**
2. Select:
   - **Transaction Type**: Associated transaction type
   - **Commission Type**: Fixed or Percentage
   - **Amount/Rate**: Commission amount or percentage
3. Configure tiers if needed for tiered commission structures
4. Click **Create**

#### 13.3.3 Editing Commissions

1. Click **Edit** on a commission configuration
2. Modify commission structure and tiers
3. Click **Update**

---

## 14. Audit Trail

The audit trail provides a complete log of all system activities.

### 14.1 Viewing Audit Trail

1. Navigate to **Audit Trail**
2. View all logged activities
3. Filter by:
   - Date range
   - User
   - Model/Entity type
   - Action type (Created, Updated, Deleted)
4. See:
   - Timestamp
   - User who performed the action
   - Entity type
   - Action type
   - Description

### 14.2 Viewing Audit Details

1. Click on an audit entry
2. View detailed information:
   - Complete activity details
   - Changes made (before/after values)
   - User information
   - IP address (if logged)

### 14.3 Exporting Audit Trail

1. Apply filters if needed
2. Click **Export**
3. Select export format (CSV, Excel, or PDF)
4. Download the audit log

---

## 15. System Settings

### 15.1 Language Preferences

Your language preference is automatically saved when you select a language. The system will remember your choice for future sessions.

### 15.2 Theme Preferences

Your theme preference (Light/Dark) is automatically saved. The system will apply your preferred theme on subsequent logins.

### 15.3 User Profile

To change your password or update your profile:
1. Contact your system administrator
2. Super Admins can update user information through User Management

---

## 16. Troubleshooting

### 16.1 Common Issues

#### Cannot Log In

- Verify your email and password are correct
- Ensure you have an active account
- Contact your administrator if issues persist

#### Missing Menu Items

- Some menu items are hidden based on your permissions
- Contact your administrator if you need access to specific features

#### SMS Not Sending

- Verify SMS configuration is set up (Super Admin)
- Check SMS balance is sufficient
- Ensure sender number is configured for your business unit
- Verify client phone numbers are in correct format

#### Export Not Working

- Ensure you have export permissions
- Check browser settings allow downloads
- Try a different browser if issues persist

#### Reports Not Generating

- Check report queue status
- Verify you have report request permissions
- Contact administrator if reports continue to fail

### 16.2 Getting Help

If you encounter issues not covered in this manual:

1. Check this manual's relevant section
2. Contact your system administrator
3. Provide details about:
   - What you were trying to do
   - What error message appeared (if any)
   - Your browser and version
   - Screenshots if possible

---

## 17. Appendix

### 17.1 Glossary

- **Business Unit**: An organization or company that uses the system
- **Role**: A collection of permissions assigned to users
- **Permission**: A specific action or access right
- **Client**: A customer of the business unit
- **Agent**: A person or entity that performs transactions on behalf of clients
- **Terminal**: A device used to process transactions
- **Account**: A financial account linked to a client, agent, or terminal
- **Transaction**: A financial operation processed through the system
- **Card**: A payment card linked to an account
- **SMS Credit**: Unit used for SMS messaging costs
- **Commission**: Payment to agents based on transactions
- **Cost**: Fee charged for transactions
- **Audit Trail**: Log of all system activities

### 17.2 Keyboard Shortcuts

- **Ctrl + F** (Windows/Linux) or **Cmd + F** (Mac): Search within page
- **Esc**: Close modals/dialogs
- **Tab**: Navigate between form fields

### 17.3 Browser Compatibility

The system works best with:
- Google Chrome (latest version)
- Mozilla Firefox (latest version)
- Microsoft Edge (latest version)
- Safari (latest version)

### 17.4 Data Export Formats

- **CSV**: Comma-separated values, opens in Excel and other spreadsheet applications
- **Excel**: Microsoft Excel format (.xlsx), opens in Microsoft Excel
- **PDF**: Portable Document Format, opens in PDF readers

### 17.5 SMS Requirements

- SMS sender numbers must be in format: `tel:+[country code][number]`
- Example: `tel:+22676396790`
- Phone numbers should include country code
- Maximum message length: 160 characters

### 17.6 Security Best Practices

- Use strong passwords
- Log out when finished
- Don't share your login credentials
- Report suspicious activity immediately
- Keep your browser updated

---

## Document Information

**Document Title**: Agency Banking Management System - User Manual  
**Version**: 1.0  
**Last Updated**: January 2026  
**Prepared For**: All System Users  
**Prepared By**: System Documentation Team

---

*This manual is a living document and will be updated as new features are added or existing features are modified. Always refer to the latest version for accurate information.*
