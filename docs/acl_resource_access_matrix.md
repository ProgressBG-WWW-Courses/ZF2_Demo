# ACL Resource Access Matrix

This document outlines the systematic breakdown of resource access by role as implemented in the `AclService.php`. Each role inherits all permissions from the levels below it.

## 🔑 Role Hierarchy
`guest` → `staff` → `manager` → `admin`

---

## 🛡️ Access Control Matrix

| Resource (Route Name) | Guest | Staff | Manager | Admin | Notes |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **Home (`home`)** | ✅ | ✅ | ✅ | ✅ | Public Landing Page |
| **Access Denied (`error-403`)** | ✅ | ✅ | ✅ | ✅ | Custom 403 Page |
| **Login (`auth/login`)** | ✅ | ✅ | ✅ | ✅ | Authentication |
| **Logout (`auth/logout`)** | ✅ | ✅ | ✅ | ✅ | Authentication |
| **Webhook (`payment/webhook`)** | ✅ | ✅ | ✅ | ✅ | For Revolut API calls |
| --- | --- | --- | --- | --- | --- |
| **Room List (`room`)** | ❌ | ✅ | ✅ | ✅ | Protected |
| **Room Details (`room/detail`)** | ❌ | ✅ | ✅ | ✅ | Protected |
| **Room Search (`room/search`)** | ❌ | ✅ | ✅ | ✅ | Protected |
| **About Page (`room-about`)** | ❌ | ✅ | ✅ | ✅ | Protected |
| **Payment Success (`payment/success`)** | ❌ | ✅ | ✅ | ✅ | Success Redirect |
| **Payment Cancel (`payment/cancel`)** | ❌ | ✅ | ✅ | ✅ | Cancel Redirect |
| **Payment Status (`payment/status`)** | ❌ | ✅ | ✅ | ✅ | JSON Polling |
| **Initiate Payment (`payment/create`)** | ❌ | ✅ | ✅ | ✅ | Room Booking |
| --- | --- | --- | --- | --- | --- |
| **Create Room (`room/create`)** | ❌ | ❌ | ✅ | ✅ | Managerial access |
| --- | --- | --- | --- | --- | --- |
| **All Else** | ❌ | ❌ | ❌ | ✅ | "Secure by Default" |

---

## 📂 Implementation Details

- **Event Listener**: `Application\Module::checkAccess` attached to `EVENT_ROUTE` with priority `-100`.
- **Enforcement**: Redirects guests to `auth/login` and logged-in users to `error-403`.
- **View Helper**: `$this->isAllowed($resource)` used in templates to conditionally hide UI elements.
- **Default State**: Secure by Default. If a resource isn't explicitly allowed, it's denied (except for `admin`).
