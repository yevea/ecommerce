# CRM Plugin Kickoff Guide — Step-by-Step

**Date:** March 2026  
**Purpose:** How to create a new GitHub repository for the Zadarma CRM plugin and transfer all accumulated knowledge from the ecommerce analysis sessions into the first Copilot prompt.

**Previous analysis documents (in this repository):**
- [VoIP-CRM Integration Analysis](VoIP-CRM-Integration-Analysis.md)
- [Architecture Decision: VoIP Plugin](Architecture-Decision-VoIP-Plugin.md)
- [Solution A vs. C Deep Dive](Solution-A-vs-C-Deep-Dive.md)

---

## Table of Contents

1. [Overview — What We're Building](#1-overview--what-were-building)
2. [Step-by-Step: Create the Repository](#2-step-by-step-create-the-repository)
3. [The Kickoff Prompt for Copilot](#3-the-kickoff-prompt-for-copilot)
4. [What the Prompt Will Produce](#4-what-the-prompt-will-produce)
5. [After Phase 1 — Next Prompts](#5-after-phase-1--next-prompts)
6. [Reference: Ecommerce Plugin Structure](#6-reference-ecommerce-plugin-structure)
7. [Reference: Zadarma API Details](#7-reference-zadarma-api-details)

---

## 1. Overview — What We're Building

A **separate FacturaScripts plugin called `crm`** that:

- Receives Zadarma webhook notifications when calls start/end/are recorded
- Logs all calls in a `crm_calls` database table
- Matches incoming phone numbers to existing FS `contactos` (contacts), `clientes` (customers), and `ecommerce_orders` (from the ecommerce plugin)
- Provides admin list/edit views for call history
- Has a settings page for Zadarma API credentials
- Uses a cron job as a backup to poll the Zadarma API for any missed webhook events
- Supports the same 4 languages as the ecommerce plugin (en_EN, es_ES, fr_FR, de_DE)

The plugin lives in its **own repository** (`yevea/crm`) and is installed alongside the ecommerce plugin. It reads ecommerce data via FacturaScripts' `Dinamic\Model` system — **zero changes** to the ecommerce plugin are needed.

---

## 2. Step-by-Step: Create the Repository

### Step 1: Create the GitHub repository

1. Go to https://github.com/new
2. Fill in:
   - **Repository name:** `crm`
   - **Description:** `Zadarma VoIP CRM plugin for FacturaScripts — call logging, customer matching, webhook integration`
   - **Visibility:** Private (you can make it public later)
   - **Add a README file:** ✓ Yes
   - **Add .gitignore:** PHP
   - **Choose a license:** GNU Lesser General Public License v3.0
3. Click **Create repository**

### Step 2: Enable Copilot Coding Agent

1. In the new `yevea/crm` repository, go to **Settings → Copilot → Coding agent**
2. Enable the coding agent (same as you have for the ecommerce repo)

### Step 3: Create the first issue

1. Go to **Issues → New issue**
2. Use the title and body from [Section 3 below](#3-the-kickoff-prompt-for-copilot)
3. Assign the issue to Copilot

That's it. Copilot will create a PR with the full Phase 1 scaffold.

---

## 3. The Kickoff Prompt for Copilot

Create a GitHub issue in the `yevea/crm` repository with the following content. Copy everything between the `---` markers:

---

### Issue title

```
Scaffold FacturaScripts CRM plugin — Phase 1: Zadarma webhook integration + call log
```

### Issue body

````markdown
## Context

This is a brand-new FacturaScripts plugin called `crm`. It integrates Zadarma Cloud PBX with FacturaScripts to log incoming/outgoing phone calls and match callers to existing customers and orders.

### Business context

- Solo olive wood business in isolated mountain area (Spain)
- No mobile network — only Starlink internet
- Using Zadarma free Standard plan (~€3.60/month for Spanish DID number)
- Zadarma provides: Cloud PBX, SIP softphone, webhooks, REST API — all on free plan
- The business already has a separate `ecommerce` plugin (repo: yevea/ecommerce) that stores orders with `customer_phone`, `customer_name`, `customer_email`, `codcliente` fields
- This CRM plugin must work alongside the ecommerce plugin but be completely independent — it reads ecommerce data via FacturaScripts' `Dinamic\Model` system, never writes to ecommerce tables

### Architecture decisions (already analysed)

1. **Separate plugin** — NOT integrated into the ecommerce plugin. Reasons: single responsibility, independent lifecycles, risk isolation, zero changes to ecommerce plugin
2. **Webhooks as primary** — Zadarma pushes real-time events (NOTIFY_START, NOTIFY_END, NOTIFY_RECORD) to a webhook controller
3. **API polling as backup** — A cron job every 15 minutes fetches recent call history to catch any missed webhooks
4. **4-language support** — en_EN, es_ES, fr_FR, de_DE (same as ecommerce plugin)

## What to build (Phase 1)

### Plugin metadata

**facturascripts.ini:**
```ini
name = 'crm'
description = 'Zadarma VoIP CRM plugin for FacturaScripts — call logging and customer matching'
version = 0.1
min_version = 2025.71
```

**composer.json:**
```json
{
    "name": "facturascripts/crm",
    "description": "Zadarma VoIP CRM plugin for FacturaScripts — call logging and customer matching",
    "type": "facturascripts-plugin",
    "license": "LGPL-3.0-or-later",
    "require": {
        "php": ">=8.1"
    }
}
```

### Database table: `crm_calls`

Create `Table/crm_calls.xml`:

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | serial | NO | — | Primary key |
| `call_id` | varchar(50) | YES | — | Zadarma's unique call identifier |
| `direction` | varchar(10) | NO | 'inbound' | 'inbound' or 'outbound' |
| `caller_number` | varchar(30) | YES | — | Caller's phone number (E.164 format) |
| `called_number` | varchar(30) | YES | — | Called number |
| `started_at` | timestamp | YES | — | When the call started |
| `ended_at` | timestamp | YES | — | When the call ended |
| `duration_seconds` | integer | YES | 0 | Call duration in seconds |
| `status` | varchar(20) | NO | 'unknown' | answered, missed, busy, voicemail, unknown |
| `recording_url` | text | YES | — | URL to call recording on Zadarma |
| `voicemail` | boolean | NO | false | Whether this was a voicemail |
| `matched_codcliente` | varchar(10) | YES | — | Matched FS client code (from contactos or ecommerce_orders) |
| `matched_order_code` | varchar(20) | YES | — | Matched ecommerce order code |
| `matched_name` | varchar(200) | YES | — | Matched customer name (denormalised for display) |
| `notes` | text | YES | — | Admin notes |
| `webhook_event` | varchar(30) | YES | — | The Zadarma event that created/updated this record |
| `raw_payload` | text | YES | — | Raw JSON webhook payload (for debugging) |
| `creation_date` | timestamp | YES | — | Record creation timestamp |
| `modification_date` | timestamp | YES | — | Last modification timestamp |

Constraints: `PRIMARY KEY (id)`, `UNIQUE (call_id)` where call_id is not null.

### Model: `CrmCall`

Create `Model/CrmCall.php`:

- Extends `FacturaScripts\Core\Template\ModelClass`
- Uses `FacturaScripts\Core\Template\ModelTrait`
- Table name: `crm_calls`
- Primary key: `id`
- Properties matching all table columns with correct PHP types
- `primaryDescriptionColumn()` returns `'call_id'`
- `test()` method: normalise phone numbers (strip spaces, ensure + prefix for international), validate status is one of allowed values
- `url()` method: standard FS URL pattern

### Controller: `ZadarmaWebhook`

Create `Controller/ZadarmaWebhook.php`:

This is the most critical file. It receives HTTP POST requests from Zadarma.

- Extends `FacturaScripts\Core\Template\Controller` (NOT AdminController — it must be publicly accessible)
- **No authentication required from Zadarma** — but validate the request using Zadarma's IP whitelist or their signature verification
- Override `getPageData()`: set `name = 'ZadarmaWebhook'`, `showOnMenu = false` (hidden from admin menu)
- Override `privateCore()` to call `publicCore()` (allow unauthenticated access)
- Override `publicCore()`:
  1. Read POST body
  2. Determine event type from the `event` parameter
  3. Handle `NOTIFY_START`: create a new CrmCall record with direction, caller_number, called_number, started_at, status='ringing'
  4. Handle `NOTIFY_END`: find existing CrmCall by call_id, update ended_at, duration_seconds, status (answered/missed/busy)
  5. Handle `NOTIFY_RECORD`: find existing CrmCall by call_id, update recording_url
  6. Handle `NOTIFY_OUT_START`, `NOTIFY_OUT_END`: same as above but direction='outbound'
  7. After creating/updating a call record, run the **customer matching logic**
  8. Store raw JSON payload in `raw_payload` field
  9. Return HTTP 200 with `{"status":"ok"}`

**Zadarma webhook parameter reference** (the POST parameters Zadarma sends):

For `NOTIFY_START`:
- `event` = "NOTIFY_START"
- `call_start` = timestamp
- `pbx_call_id` = unique call ID
- `caller_id` = caller's phone number
- `called_did` = your virtual number that was called

For `NOTIFY_END`:
- `event` = "NOTIFY_END"
- `call_start` = timestamp
- `pbx_call_id` = unique call ID
- `caller_id` = caller's phone number
- `called_did` = your virtual number
- `duration` = call duration in seconds
- `disposition` = "answered", "busy", "cancel", "no answer", "failed", "congestion"
- `status_code` = SIP status code
- `is_recorded` = 1 if recorded, 0 if not

For `NOTIFY_RECORD`:
- `event` = "NOTIFY_RECORD"
- `call_id_with_rec` = call ID
- `pbx_call_id` = unique call ID

For `NOTIFY_OUT_START`:
- `event` = "NOTIFY_OUT_START"
- `call_start` = timestamp
- `pbx_call_id` = unique call ID
- `destination` = called number
- `caller_id` = your number

For `NOTIFY_OUT_END`:
- `event` = "NOTIFY_OUT_END"
- `call_start` = timestamp
- `pbx_call_id` = unique call ID
- `destination` = called number
- `caller_id` = your number
- `duration` = seconds
- `disposition` = same values as NOTIFY_END
- `is_recorded` = 1/0

### Customer matching logic

When a call is logged, try to match the phone number to an existing customer:

```
1. Normalise the phone number: strip spaces, leading zeros, ensure E.164 (+34...)
2. Search FacturaScripts core `contactos` table:
   new \FacturaScripts\Dinamic\Model\Contacto()
   → Search where telefono1 or telefono2 LIKE '%<last 9 digits>%'
   → If found: set matched_codcliente = contacto.codcliente, matched_name = contacto.nombre
3. If not found in contactos, search ecommerce_orders:
   new \FacturaScripts\Dinamic\Model\EcommerceOrder()  (from the ecommerce plugin)
   → Search where customer_phone LIKE '%<last 9 digits>%'
   → If found: set matched_codcliente = order.codcliente, matched_order_code = order.code, matched_name = order.customer_name
4. If not found anywhere: leave matched fields null (unknown caller)
```

**Important:** Use `class_exists()` to check if the EcommerceOrder model exists before trying to use it. The CRM plugin must work even if the ecommerce plugin is not installed.

### Controller: `ListCrmCall`

Create `Controller/ListCrmCall.php`:

- Extends `FacturaScripts\Core\Lib\ExtendedController\ListController`
- `getPageData()`: name='ListCrmCall', title=trans('call-log'), icon='fas fa-phone', menu='CRM'
- `createViews()`: add a single list view for CrmCall model
- Searchable fields: caller_number, called_number, matched_name, notes
- Order by: started_at DESC (default), duration_seconds, status
- Filters:
  - Select filter on `direction` (inbound/outbound)
  - Select filter on `status` (answered/missed/busy/voicemail/unknown)
  - Period filter on `started_at`

### Controller: `EditCrmCall`

Create `Controller/EditCrmCall.php`:

- Extends `FacturaScripts\Core\Lib\ExtendedController\EditController`
- `getModelClassName()` returns `'CrmCall'`
- `getPageData()`: name='EditCrmCall', title=trans('call'), icon='fas fa-phone', showOnMenu=false

### Controller: `SettingsCrm`

Create `Controller/SettingsCrm.php`:

- Extends `FacturaScripts\Core\Lib\ExtendedController\EditController` or a settings-style controller
- Stores Zadarma API credentials using `FacturaScripts\Core\Tools::settings('crm', 'zadarma_api_key')` and `Tools::settings('crm', 'zadarma_api_secret')`
- `getPageData()`: name='SettingsCrm', title=trans('crm-settings'), icon='fas fa-cog', menu='admin', submenu='CRM'

### XMLView: `ListCrmCall.xml`

List view columns:
- `started_at` (datetime, order 100)
- `direction` (text with icon, order 110) — show fas fa-arrow-down for inbound, fas fa-arrow-up for outbound
- `caller_number` (text, order 120)
- `called_number` (text, order 130)
- `duration_seconds` (number, order 140)
- `status` (text, order 150) — color-coded: green=answered, red=missed, yellow=busy, purple=voicemail
- `matched_name` (text, order 160)
- `recording_url` (link, order 170) — if not null, show a play icon

### XMLView: `EditCrmCall.xml`

Edit view with groups:
- **Call Details** group: call_id (readonly), direction (readonly), caller_number (readonly), called_number (readonly), started_at (readonly), ended_at (readonly), duration_seconds (readonly), status (readonly)
- **Customer Match** group: matched_codcliente, matched_order_code, matched_name
- **Notes** group: notes (textarea)
- **Recording** group: recording_url (readonly, link)
- **Debug** group (collapsed by default): webhook_event (readonly), raw_payload (readonly textarea)

### XMLView: `SettingsCrm.xml`

Settings form:
- **Zadarma API** group: zadarma_api_key (text), zadarma_api_secret (password)
- **Instructions** group: a text widget explaining where to find the API credentials in Zadarma dashboard and how to configure the webhook URL

### Init.php

```php
namespace FacturaScripts\Plugins\crm;

use FacturaScripts\Core\Template\InitClass;

class Init extends InitClass
{
    public function init(): void
    {
        // Phase 1: nothing to load
        // Phase 2: load extensions for ecommerce views
    }

    public function update(): void
    {
        // Phase 1: nothing to migrate
    }

    public function uninstall(): void
    {
    }
}
```

### Translations

Create translation files for all 4 languages (en_EN, es_ES, fr_FR, de_DE) with these keys:

```json
{
    "crm": "CRM",
    "call-log": "Call Log",
    "call": "Call",
    "call-id": "Call ID",
    "direction": "Direction",
    "inbound": "Inbound",
    "outbound": "Outbound",
    "caller-number": "Caller Number",
    "called-number": "Called Number",
    "started-at": "Started At",
    "ended-at": "Ended At",
    "duration-seconds": "Duration (s)",
    "status": "Status",
    "answered": "Answered",
    "missed": "Missed",
    "busy": "Busy",
    "voicemail": "Voicemail",
    "unknown": "Unknown",
    "recording-url": "Recording",
    "matched-codcliente": "Client Code",
    "matched-order-code": "Order Code",
    "matched-name": "Matched Customer",
    "notes": "Notes",
    "webhook-event": "Webhook Event",
    "raw-payload": "Raw Payload",
    "crm-settings": "CRM Settings",
    "zadarma-api-key": "Zadarma API Key",
    "zadarma-api-secret": "Zadarma API Secret",
    "zadarma-api-instructions": "Enter your Zadarma API credentials. Find them at: Zadarma Dashboard → Settings → API.\nSet the webhook URL in Zadarma to: https://your-facturascripts-domain.com/ZadarmaWebhook",
    "call-details": "Call Details",
    "customer-match": "Customer Match",
    "recording": "Recording",
    "debug": "Debug"
}
```

Translate all values into es_ES, fr_FR, and de_DE.

### Directory structure

```
crm/
├── Controller/
│   ├── EditCrmCall.php
│   ├── ListCrmCall.php
│   ├── SettingsCrm.php
│   └── ZadarmaWebhook.php
├── Model/
│   └── CrmCall.php
├── Table/
│   └── crm_calls.xml
├── Translation/
│   ├── en_EN.json
│   ├── es_ES.json
│   ├── fr_FR.json
│   └── de_DE.json
├── XMLView/
│   ├── EditCrmCall.xml
│   ├── ListCrmCall.xml
│   └── SettingsCrm.xml
├── Init.php
├── composer.json
├── facturascripts.ini
├── LICENSE
└── README.md
```

### README.md

Write a README that includes:
- Plugin description
- Requirements (FacturaScripts >= 2025.71, PHP >= 8.1)
- Installation instructions (copy to Plugins/crm, enable in FS admin)
- Configuration (Zadarma API credentials, webhook URL setup)
- How it works (webhooks + backup polling)
- How customer matching works
- Integration with the ecommerce plugin (optional, auto-detected)
- Phase roadmap (Phase 1: current, Phase 2: ecommerce extensions, Phase 3: WhatsApp notifications, click-to-call)

## Conventions to follow

These conventions come from the existing `ecommerce` plugin (repo: yevea/ecommerce) and must be consistent:

1. **License header:** All PHP files start with the LGPL-3.0 license block (see ecommerce Init.php)
2. **Namespace:** `FacturaScripts\Plugins\crm` (matching the plugin folder name)
3. **Model pattern:** Extend `ModelClass`, use `ModelTrait`, define `primaryColumn()`, `tableName()`, `primaryDescriptionColumn()`, `test()`, `url()`
4. **Where clauses:** Use `Where::eq()`, `Where::in()` static factory methods (preferred FS API)
5. **Settings:** Use `Tools::settings('crm', 'key')` for plugin settings
6. **Translations:** All 4 languages must have identical keys. Use `trans('key')` in Twig templates.
7. **XMLView:** Follow FS XMLView schema. Column `name` attribute must match the model property name. Use `fieldname` for form fields.
8. **Menu:** Register under a new 'CRM' menu group, with settings under 'admin' submenu
9. **No test infrastructure** — the ecommerce plugin has no tests; match this pattern (don't add test framework unless asked)
10. **PHP 8.1+** — use typed properties, match expressions where appropriate

## Important: What NOT to do

- Do NOT modify any files in the ecommerce plugin repository
- Do NOT create database tables that duplicate data from FS core (contactos, clientes, etc.)
- Do NOT hardcode Zadarma credentials — always read from settings
- Do NOT require the ecommerce plugin — use `class_exists()` to check if EcommerceOrder is available
- Do NOT add a composer dependency on Zadarma SDK — use plain PHP `file_get_contents` or `curl` for API calls
````

---

## 4. What the Prompt Will Produce

After Copilot processes this issue, you should get a PR containing:

```
crm/
├── Controller/
│   ├── EditCrmCall.php         ← Admin edit view for a single call
│   ├── ListCrmCall.php         ← Admin list view with search and filters
│   ├── SettingsCrm.php         ← API credentials settings page
│   └── ZadarmaWebhook.php     ← Receives Zadarma webhook POSTs
├── Model/
│   └── CrmCall.php             ← Call log model (maps to crm_calls table)
├── Table/
│   └── crm_calls.xml           ← Database table definition
├── Translation/
│   ├── en_EN.json              ← English
│   ├── es_ES.json              ← Spanish
│   ├── fr_FR.json              ← French
│   └── de_DE.json              ← German
├── XMLView/
│   ├── EditCrmCall.xml         ← Edit form layout
│   ├── ListCrmCall.xml         ← List view layout
│   └── SettingsCrm.xml         ← Settings form layout
├── Init.php                    ← Plugin initialization (empty for Phase 1)
├── composer.json               ← Plugin metadata
├── facturascripts.ini          ← FS plugin descriptor
├── LICENSE                     ← LGPL-3.0
└── README.md                   ← Documentation
```

### What to verify in the PR

Before merging, check:

1. **ZadarmaWebhook.php** — is `publicCore()` implemented correctly? Does it handle all 5 event types?
2. **CrmCall.php** — does `test()` normalise phone numbers? Are all columns mapped?
3. **Customer matching** — does it use `class_exists()` for the EcommerceOrder check?
4. **crm_calls.xml** — does it have all columns from the table spec?
5. **Translations** — do all 4 files have identical keys?
6. **ListCrmCall.xml** — are the search/filter configurations correct?

---

## 5. After Phase 1 — Next Prompts

### Phase 2: Backup API polling (cron job)

```
Add a cron job that polls the Zadarma API every 15 minutes to fetch recent call
history and fill in any calls that were missed by webhooks.

Use Zadarma's /v1/statistics/ API endpoint.
Authentication: Zadarma uses HMAC-SHA1 signature (api_key + api_secret).
Read credentials from Tools::settings('crm', 'zadarma_api_key') and
Tools::settings('crm', 'zadarma_api_secret').

Implement in Init.php::init() by registering a cron job using
$this->loadExtension() or the FS cron system.

Only insert calls where the call_id does not already exist in crm_calls
(avoid duplicates with webhook-created records).
```

### Phase 3: Ecommerce integration extensions

```
Add Extension/Controller/EditEcommerceOrder.php and
Extension/XMLView/EditEcommerceOrder.xml that add a "Call History" tab
to the ecommerce order editor. The tab shows all CrmCall records where
matched_order_code matches the current order's code.

Use the FS extension system — this must NOT modify any files in the
ecommerce plugin. The extension files in THIS plugin automatically
extend the ecommerce views when both plugins are installed.

Use class_exists() to check if EditEcommerceOrder controller exists
before registering the extension.
```

### Phase 4: Click-to-call

```
Add a "Call" button next to phone numbers in the CrmCall list view and
in the ecommerce order editor extension. When clicked, use the Zadarma
/v1/request/callback/ API to initiate a callback:
1. First rings YOUR extension (the Zadarma softphone on your PC/mobile)
2. When you answer, it calls the customer's number
3. The customer sees your business number as the caller ID

This requires the Zadarma API key/secret from settings.
```

### Phase 5: WhatsApp missed call notifications

```
When a missed call or voicemail is logged (NOTIFY_END with
disposition='no answer' or voicemail=true), send a WhatsApp message
to a configured admin phone number using the WhatsApp Business Cloud API.

Message format:
"📞 Missed call from {caller_number} ({matched_name})
 {matched_order_code ? 'Order: ' + matched_order_code : ''}
 {voicemail ? 'Voicemail recording available' : ''}
 Time: {started_at}"

Store WhatsApp API credentials in CRM settings alongside Zadarma keys.
This is optional — only send if WhatsApp credentials are configured.
```

---

## 6. Reference: Ecommerce Plugin Structure

For context, this is the structure of the existing ecommerce plugin that the CRM plugin will integrate with:

```
ecommerce/                          ← yevea/ecommerce repository
├── Controller/
│   ├── EditEcommerceOrder.php      ← Admin: edit single order
│   ├── ListEcommerceOrder.php      ← Admin: list orders with search
│   ├── Presupuesto.php             ← Customer: shopping cart / quote
│   ├── ProductoDetalle.php         ← Customer: product detail page
│   ├── SettingsEcommerce.php       ← Admin: Stripe settings
│   ├── ShoppingCartView.php        ← Customer: cart view
│   ├── StoreFront.php              ← Customer: main catalogue page
│   └── Tableros.php                ← Customer: category pages
├── Extension/
│   ├── Controller/
│   │   ├── EditFamilia.php         ← Extends FS core EditFamilia
│   │   └── EditProducto.php        ← Extends FS core EditProducto
│   └── XMLView/
│       ├── EditFamilia.xml
│       ├── EditProducto.xml
│       └── EditVariante.xml
├── Model/
│   ├── EcommerceCartItem.php
│   ├── EcommerceOrder.php          ← Has customer_phone, codcliente fields
│   └── EcommerceOrderLine.php
├── Table/
│   ├── ProductoImagen.xml
│   ├── ecommerce_cart_items.xml
│   ├── ecommerce_order_lines.xml
│   └── ecommerce_orders.xml        ← Defines the schema CRM will query
├── Translation/
│   ├── en_EN.json
│   ├── es_ES.json
│   ├── fr_FR.json
│   └── de_DE.json
├── Init.php
├── composer.json
├── facturascripts.ini              ← name='ecommerce', version=1.2
└── README.md
```

### Key fields the CRM plugin reads from ecommerce

From `ecommerce_orders` table (via `EcommerceOrder` model):

| Field | Type | Used by CRM for |
|---|---|---|
| `customer_phone` | varchar(30) | Matching caller to order |
| `customer_name` | varchar(200) | Display matched customer name |
| `customer_email` | varchar(200) | Future: email notifications |
| `code` | varchar(20) | Linking call to order code |
| `codcliente` | varchar(10) | Linking call to FS client |

### Key fields the CRM plugin reads from FS core

From `contactos` table (via `Contacto` model):

| Field | Type | Used by CRM for |
|---|---|---|
| `telefono1` | varchar(30) | Primary phone match |
| `telefono2` | varchar(30) | Secondary phone match |
| `nombre` | varchar(100) | Display matched contact name |
| `codcliente` | varchar(10) | Linking call to FS client |

---

## 7. Reference: Zadarma API Details

### Webhook configuration

In Zadarma dashboard → **Settings → API → Notifications**:

- **Webhook URL:** `https://your-facturascripts-domain.com/ZadarmaWebhook`
- **Events to enable:** NOTIFY_START, NOTIFY_END, NOTIFY_RECORD, NOTIFY_OUT_START, NOTIFY_OUT_END
- **Method:** POST

### API authentication

Zadarma REST API uses HMAC-SHA1 signature authentication:

```php
$apiKey = Tools::settings('crm', 'zadarma_api_key');
$apiSecret = Tools::settings('crm', 'zadarma_api_secret');

$params = ['start' => '2026-03-01', 'end' => '2026-03-07'];
ksort($params);
$paramsStr = http_build_query($params);

$sign = base64_encode(hash_hmac('sha1',
    '/v1/statistics/' . $paramsStr . md5($paramsStr),
    $apiSecret,
    true
));

$headers = [
    'Authorization: ' . $apiKey . ':' . $sign,
];
```

### Call history API endpoint

```
GET https://api.zadarma.com/v1/statistics/
Parameters:
  start = YYYY-MM-DD HH:MM:SS
  end = YYYY-MM-DD HH:MM:SS
  
Response (JSON):
{
  "status": "success",
  "stats": [
    {
      "id": "123456",
      "callstart": "2026-03-07 10:30:00",
      "from": "+34612345678",
      "to": "+34911234567",
      "duration": 180,
      "disposition": "answered",
      "clid": "Juan Garcia",
      "pbx_call_id": "abc-123-def"
    }
  ]
}
```

### Recording URL API endpoint

```
GET https://api.zadarma.com/v1/pbx/record/request/
Parameters:
  call_id = the pbx_call_id value
  
Response:
{
  "status": "success",
  "link": "https://api.zadarma.com/v1/pbx/record/download/..."
}
```
