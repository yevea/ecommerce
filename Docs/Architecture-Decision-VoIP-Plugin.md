# Architecture Decision: Integrate VoIP into Ecommerce Plugin vs. New Separate Plugin

**Date:** March 2026  
**Decision:** Solution A — Cloud PBX (Zadarma) with Webhook Integration  
**Previous analysis:** [VoIP-CRM Integration Analysis](VoIP-CRM-Integration-Analysis.md)

---

## The Question

> Should the Zadarma VoIP/CRM call management be added to the existing `ecommerce` plugin, or built as a separate FacturaScripts plugin?

---

## Recommendation: ★ New Separate Plugin ★

**Create a new FacturaScripts plugin called `crm`** (or `voip`, or `zadarma`) rather than adding VoIP functionality to the existing `ecommerce` plugin.

---

## Detailed Reasoning

### 1. Single Responsibility Principle

The existing `ecommerce` plugin has a clearly defined purpose: **online product catalogue, shopping cart, and checkout with Stripe payments**. Its 14 PHP files, 3 models, and 7 database tables all serve that single purpose.

VoIP call management is a completely different domain:

| Ecommerce Plugin | VoIP/CRM Plugin |
|---|---|
| Product catalogue display | Incoming/outgoing call logging |
| Shopping cart management | Voicemail recording storage |
| Stripe payment processing | Zadarma webhook reception |
| Order creation & tracking | Caller-to-customer matching |
| Customer checkout flow | Call history & statistics |
| Public storefront pages | Admin-only call management |

Mixing these concerns in one plugin would make both harder to maintain.

### 2. Independent Lifecycles

The two systems will evolve at different rates:

- **Ecommerce** changes when you modify products, categories, pricing, or the checkout flow
- **VoIP/CRM** changes when you update call routing rules, switch VoIP providers, or add call features

A bug in the VoIP webhook handler should not risk breaking your shopping cart. Independent plugins can be updated, disabled, or debugged in isolation.

### 3. Optional Dependency

Not every FacturaScripts installation with ecommerce needs VoIP. And not every installation with VoIP needs ecommerce. Keeping them separate means:

- You can **disable the VoIP plugin** without affecting the shop
- Someone else could **reuse the VoIP plugin** without your ecommerce plugin
- You could later **replace Zadarma** with a different provider by swapping just the VoIP plugin

### 4. FacturaScripts Plugin Architecture Supports This Well

FacturaScripts has excellent support for inter-plugin communication without tight coupling:

#### a) Shared database access via Dinamic Models

Both plugins can read each other's data through FacturaScripts' dynamic model system:

```
VoIP plugin reads ecommerce customer data:
  new \FacturaScripts\Dinamic\Model\EcommerceOrder()
  → Searches by customer_phone to match callers to orders

Ecommerce plugin is unaware of the VoIP plugin (no changes needed)
```

#### b) Extension system for UI integration

The VoIP plugin can **extend** ecommerce views without modifying any ecommerce files:

```
VoIP plugin's Extension/XMLView/EditEcommerceOrder.xml
  → Adds a "Call History" tab to the order editor
  → Shows calls linked to that order's customer_phone
  
Ecommerce plugin is unaware of this extension (no changes needed)
```

#### c) Shared settings namespace

Each plugin gets its own settings group:

```
Tools::settings('ecommerce', 'stripe_secret_key')   ← Ecommerce settings
Tools::settings('crm', 'zadarma_api_key')            ← VoIP settings
```

#### d) Menu system

Each plugin registers its own admin menu:

```
Ecommerce → Orders, Products, Categories
CRM       → Call Log, Settings
Admin     → Settings (Ecommerce), Settings (CRM)
```

### 5. The Ecommerce Plugin Does Not Need to Change

The key insight is that the ecommerce plugin **already stores `customer_phone`** in `EcommerceOrder`. The VoIP plugin reads this data — it does not need to write to the ecommerce tables or modify any ecommerce code.

```
Ecommerce plugin (UNCHANGED):
  ecommerce_orders.customer_phone = "+34612345678"

VoIP plugin (NEW):
  Receives webhook: incoming call from +34612345678
  Searches: SELECT * FROM ecommerce_orders WHERE customer_phone LIKE '%612345678%'
  Finds match → logs call with order/customer reference
```

### 6. What the New Plugin Would Contain

```
crm/
├── Controller/
│   ├── EditCrmCall.php              # Edit a single call log entry
│   ├── ListCrmCall.php              # List all calls with search/filters
│   ├── ZadarmaWebhook.php           # Receives Zadarma webhook POSTs (public, no auth)
│   └── SettingsCrm.php              # Zadarma API credentials settings
├── Extension/
│   ├── Controller/                   # (optional, Phase 2+)
│   │   └── EditEcommerceOrder.php   # Adds "Call History" tab to order editor
│   └── XMLView/                      # (optional, Phase 2+)
│       └── EditEcommerceOrder.xml   # Call history widget in order view
├── Model/
│   └── CrmCall.php                  # Call log model
├── Table/
│   └── crm_calls.xml               # Call log table definition
├── Translation/
│   ├── en_EN.json                   # English translations
│   ├── es_ES.json                   # Spanish translations
│   ├── fr_FR.json                   # French translations
│   └── de_DE.json                   # German translations
├── XMLView/
│   ├── EditCrmCall.xml              # Call log edit form
│   ├── ListCrmCall.xml              # Call log list view
│   └── SettingsCrm.xml              # Settings form (Zadarma API key, secret)
├── Init.php                         # Plugin initialization
├── composer.json                    # Plugin metadata
├── facturascripts.ini               # name='crm', min_version=2025.71
├── LICENSE                          # LGPL-3.0-or-later
└── README.md                        # Installation & configuration
```

### 7. How the Plugins Communicate

```
┌────────────────────────────────────────────────────────────┐
│                    FacturaScripts Core                      │
│                                                            │
│  ┌──────────────────┐        ┌──────────────────────────┐  │
│  │  ecommerce plugin │        │  crm plugin (NEW)        │  │
│  │                  │        │                          │  │
│  │ EcommerceOrder   │◄───────│ CrmCall model            │  │
│  │  .customer_phone │ reads  │  .phone_number           │  │
│  │  .customer_name  │ via    │  .matched_order_id       │  │
│  │  .codcliente     │ Dinamic│  .matched_codcliente     │  │
│  │                  │ Model  │                          │  │
│  │ (NO CHANGES)     │        │ ZadarmaWebhook           │  │
│  │                  │        │  → receives calls         │  │
│  │                  │        │  → matches customer       │  │
│  │                  │        │  → logs in crm_calls      │  │
│  └──────────────────┘        └──────────────────────────┘  │
│                                                            │
│  Extension system: crm can optionally extend ecommerce     │
│  views to show call history in order editor                 │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## What If You Had Chosen to Integrate Into Ecommerce?

For completeness, here is what integration would have looked like and why it is worse:

### Files that would change in the ecommerce plugin

| File | Change |
|---|---|
| `facturascripts.ini` | Version bump |
| `Init.php` | Add VoIP extension loading, cron registration |
| `Table/crm_calls.xml` | New table (mixed domain) |
| `Model/CrmCall.php` | New model (mixed domain) |
| `Controller/ListCrmCall.php` | New controller |
| `Controller/EditCrmCall.php` | New controller |
| `Controller/ZadarmaWebhook.php` | New webhook controller |
| `XMLView/ListCrmCall.xml` | New view |
| `XMLView/EditCrmCall.xml` | New view |
| `XMLView/SettingsEcommerce.xml` | Add Zadarma fields alongside Stripe fields |
| `Translation/*.json` (×4) | Add ~20 new keys mixed with ecommerce keys |

### Problems with this approach

1. **Mixed settings page** — Stripe payment keys and Zadarma VoIP keys on the same settings screen is confusing
2. **Mixed menu items** — "Orders" and "Call Log" under the same "ecommerce" menu does not make sense
3. **Version coupling** — Any VoIP bugfix requires an ecommerce plugin update, which could affect the shop
4. **Testing risk** — Changes to webhook handling could accidentally break checkout or cart functionality
5. **Larger attack surface** — The webhook endpoint (public, no auth) lives in the same plugin as payment processing
6. **Harder to share** — Anyone who wants just the VoIP CRM must install the full ecommerce plugin
7. **Translation bloat** — Translation files grow with unrelated keys, making them harder to maintain

---

## Decision Summary

| Criterion | Integrate | New Plugin |
|---|:---:|:---:|
| **Separation of concerns** | ✗ Mixed domains | ✓ Clean boundaries |
| **Independent updates** | ✗ Coupled versions | ✓ Update separately |
| **Risk isolation** | ✗ Shared failure domain | ✓ VoIP bug can't break shop |
| **Ecommerce plugin changes** | ✗ Needs modifications | ✓ Zero changes |
| **Settings clarity** | ✗ Stripe + Zadarma together | ✓ Separate settings pages |
| **Menu organisation** | ✗ Orders + Calls mixed | ✓ Dedicated CRM menu |
| **Reusability** | ✗ Tied to ecommerce | ✓ Works standalone |
| **Setup effort** | ✓ Slightly less (one plugin) | ✗ Slightly more (scaffold new plugin) |
| **Plugin communication** | ✓ Direct model access | ✓ Dinamic model access (same thing) |

**Verdict: New separate plugin wins on 7 of 9 criteria.**

---

## Next Steps

1. **Scaffold the new `crm` plugin** — Create the directory structure under `Plugins/crm/`
2. **Implement Phase 1** — `CrmCall` model, `ZadarmaWebhook` controller, settings page, list/edit views
3. **Configure Zadarma** — Register virtual number, set up PBX, configure webhooks pointing to `ZadarmaWebhook`
4. **Phase 2** — Add ecommerce integration via Extension (call history tab in order editor)
5. **Phase 3** — Add call recording playback, voicemail transcription, click-to-call

The ecommerce plugin in this repository requires **zero changes** for Phase 1. Phase 2 adds a non-invasive extension from the crm plugin into the ecommerce admin views — the ecommerce plugin's code still does not change.
