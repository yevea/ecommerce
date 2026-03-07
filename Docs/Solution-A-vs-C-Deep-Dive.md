# Solution A vs. Solution C — Deep Dive

**Date:** March 2026  
**Context:** You have chosen Solution A (Cloud PBX with Zadarma). This document answers two specific questions before any code is written.  
**Previous documents:**
- [VoIP-CRM Integration Analysis](VoIP-CRM-Integration-Analysis.md) — full analysis of all four solutions
- [Architecture Decision: VoIP Plugin](Architecture-Decision-VoIP-Plugin.md) — decision to build a separate FS plugin

---

## Table of Contents

1. [Question 1: What Is the Actual Difference Between Solution A and C?](#1-what-is-the-actual-difference-between-solution-a-and-c)
2. [Question 2: Can the Call Land on a Mobile Phone via WiFi as a WhatsApp Call?](#2-can-the-call-land-on-a-mobile-phone-via-wifi-as-a-whatsapp-call)
3. [Updated Recommendation](#3-updated-recommendation)

---

## 1. What Is the Actual Difference Between Solution A and C?

### Short answer

**For your specific case with Zadarma: almost nothing.** The original analysis used generic provider categories. But Zadarma blurs the line because their free Standard plan already includes a Cloud PBX with webhooks and API. The distinction between "Cloud PBX" (Solution A) and "Hybrid API" (Solution C) only matters when comparing different providers or different Zadarma tiers.

### The original distinction explained

The original analysis defined four solutions on a complexity spectrum:

```
D: Minimal          C: Hybrid API        A: Cloud PBX         B: Self-hosted PBX
─────────────────────────────────────────────────────────────────────────────────
No FS changes       API polling           Real-time webhooks   You run Asterisk
Email only          OR simple webhooks    Full PBX features    Full control
€4/mo               €4/mo                 €15–40/mo            €12–25/mo + server
```

The cost difference between A (€15–40/mo) and C (€10–25/mo) in the original analysis came from assuming **different providers at different tiers**:

| Assumption in original analysis | Solution C | Solution A |
|---|---|---|
| **Provider tier** | Basic VoIP account (just a number + API) | Full Cloud PBX subscription |
| **Example provider** | Zadarma Standard (free) or Netelip basic | Cloudtalk (€25/mo), Aircall (€30/mo), or Zadarma Office (€22/mo) |
| **What you get** | Virtual number, voicemail, API to fetch call history | All of C + IVR menus, call queues, call recording, analytics dashboard, multi-user support |
| **Integration method** | API polling (your server asks "any new calls?") OR basic webhooks | Real-time webhooks (provider pushes events to your server instantly) |
| **PBX features** | Minimal or DIY | Full-featured, provider-managed |

### Why the costs were different

The original cost estimates reflected **different service tiers**:

```
Solution C at €4/month:
  Zadarma Standard plan ($0) + Spanish DID number (~€3.60/mo) = ~€4/mo
  → Basic PBX, pay-per-minute calls, 200MB recording storage
  → API + webhooks included (yes, even on the free plan!)

Solution A at €15–40/month:
  A premium Cloud PBX subscription was assumed:
  → Cloudtalk from €25/mo    (CRM-focused, rich integrations)
  → Aircall from €30/mo      (enterprise-grade call center)
  → Zadarma Office plan €22/mo (2000 bundled minutes, larger recording storage)
  → 3CX hosted from €15/mo   (advanced PBX features)
```

### The key insight: Zadarma's free plan already has the "Solution A" features

After researching Zadarma's actual pricing and feature set, here is what their **free Standard plan** includes:

| Feature | Zadarma Standard (FREE) | Typically requires paid PBX? |
|---|---|---|
| Cloud PBX with extensions | ✓ (up to 5 users) | Yes |
| Call routing rules | ✓ | Yes |
| Voicemail with email notification | ✓ | Yes |
| IVR menus ("Press 1 for...") | ✓ (up to 4 menus) | Yes |
| Time-based routing | ✓ | Yes |
| Call recording | ✓ (200MB ≈ 14 hours) | Yes |
| REST API | ✓ | Sometimes |
| Real-time webhooks (NOTIFY_START, NOTIFY_END, etc.) | ✓ | Often paid |
| Softphone apps (iOS/Android/Desktop) | ✓ | Sometimes |
| WebRTC browser calling | ✓ | Sometimes |
| CRM integration | ✓ (built-in Zadarma CRM) | Often paid |

**This means Zadarma Standard already gives you everything originally categorised as "Solution A" — at "Solution C" pricing (~€4/month).**

### Webhooks vs. API Polling — Explained Simply

This is the core technical difference between how your FacturaScripts server learns about calls:

#### API Polling (the "checking your mailbox" approach)

```
Every 5 minutes, your FacturaScripts server asks Zadarma:
  "Hey, any new calls since I last checked?"

  ┌──────────────┐                    ┌──────────────┐
  │ FacturaScripts│── GET /calls? ───>│   Zadarma    │
  │   Server      │<── Response ──────│   API        │
  │               │   (list of calls) │              │
  └──────────────┘                    └──────────────┘

  Your server:
  → Receives a list of recent calls
  → Stores new ones in the database
  → Matches phone numbers to customers
  → Repeats in 5 minutes

  Pros: Simple, reliable, works even if your server was temporarily offline
  Cons: Not real-time (up to 5-minute delay), uses API quota on every poll
```

Think of it like checking your physical mailbox every few minutes. You will always find your mail eventually, but you don't know the instant it arrives.

#### Webhooks (the "doorbell" approach)

```
Zadarma immediately tells your server when something happens:

  Call starts → Zadarma POSTs to your server: "Call from +34612345678 just started"
  Call ends   → Zadarma POSTs to your server: "Call ended, duration 3m22s"
  Voicemail   → Zadarma POSTs to your server: "Voicemail left, recording URL: ..."

  ┌──────────────┐                    ┌──────────────┐
  │   Zadarma    │── POST /webhook ──>│ FacturaScripts│
  │   Cloud      │   {event data}     │   Server      │
  │              │                    │   → Logs call  │
  │              │                    │   → Matches    │
  │              │                    │     customer   │
  └──────────────┘                    └──────────────┘

  Pros: Instant (real-time), efficient (no wasted requests)
  Cons: Requires your server to be online and publicly accessible
        If your server is down when a webhook fires, you miss it
        (but can use API polling as backup to catch missed events)
```

Think of it like having a doorbell — you know the instant someone arrives.

#### Zadarma's Specific Webhook Events

Zadarma sends these webhook notifications to your server:

| Event | When it fires | What data you receive |
|---|---|---|
| `NOTIFY_START` | A call begins (inbound or outbound) | Caller number, called number, call ID, direction |
| `NOTIFY_END` | A call ends | Call ID, duration, status (answered/missed/busy), disposition |
| `NOTIFY_INTERNAL` | Internal extension-to-extension call | Extension numbers, status |
| `NOTIFY_RECORD` | A call recording is ready | Call ID, recording URL |
| `NOTIFY_IVR` | Caller interacts with IVR menu | Call ID, key pressed, menu |
| `NOTIFY_OUT_START` | Outbound call initiated | Destination number, call ID |
| `NOTIFY_OUT_END` | Outbound call ended | Call ID, duration, status |

#### Best Practice: Use Both

The recommended approach for the FacturaScripts plugin is:

```
Primary:  Webhooks for real-time logging
          → Zadarma pushes events instantly
          → Plugin logs each call immediately

Backup:   API polling as a safety net
          → Every 15 minutes, fetch recent call history via API
          → If any calls were missed by webhooks (server was restarting, etc.),
            the polling job catches them and fills in the gaps
```

### Revised Solution A vs C — Specific to Zadarma

Now that we've analysed Zadarma specifically, here's the real comparison:

| Aspect | "Solution C" (Zadarma Standard + API Polling) | "Solution A" (Zadarma Standard + Webhooks) | "Solution A+" (Zadarma Office) |
|---|---|---|---|
| **Monthly cost** | ~€4 (number only) | ~€4 (number only) | ~€22 (EU Office plan) |
| **How FS learns about calls** | Polls API every 5–15 min | Receives webhook POSTs in real-time | Same as webhooks |
| **Call logging delay** | Up to 15 minutes | Instant (< 1 second) | Instant |
| **PBX features** | ✓ Included free | ✓ Included free | ✓ More IVR, more users |
| **Call recording storage** | 200MB (~14 hrs) | 200MB (~14 hrs) | 2GB (~142 hrs) |
| **Bundled minutes** | Pay-per-minute | Pay-per-minute | 2000 min included |
| **Plugin complexity** | Cron job + API client | Webhook controller (slightly more) | Same as webhooks |
| **Reliability** | Very reliable (polls catch everything) | Needs backup polling for missed webhooks | Same |
| **Max API requests** | 100/min general, 3/min for statistics | No polling needed (webhook-driven) | Same |
| **Best for** | Very low call volume, simplest code | Real-time logging, professional feel | Higher call volume |

### Bottom Line for Your Case

**You don't need to choose between A and C.** With Zadarma Standard (free plan):

1. You get the Cloud PBX features originally described as "Solution A"
2. At the cost originally described as "Solution C" (~€4/month)
3. You can use webhooks (Solution A style) AND API polling (Solution C style) simultaneously
4. The plugin we'll build will support both methods

**The only reason to upgrade to Zadarma Office (€22/mo) would be:**
- You start making many outbound calls (bundled minutes save money)
- You need more than 14 hours of call recordings stored
- You need more than 4 IVR menus
- You add more than 5 users/extensions

For a solo operator with occasional calls, the free plan covers everything.

---

## 2. Can the Call Land on a Mobile Phone via WiFi as a WhatsApp Call?

### Short answer

**No, not as a WhatsApp call. But yes, the call can ring on your mobile phone over WiFi — it just uses a SIP/VoIP app instead of WhatsApp.**

The end result is practically the same: your phone rings when someone calls your business number, using only WiFi (Starlink), with no mobile network needed.

### Why WhatsApp cannot receive VoIP-forwarded calls

WhatsApp is a **closed ecosystem**. It does not accept incoming calls from external phone networks or VoIP systems. Here's why:

```
What you wanted:
  Customer calls +34 9XX XXX XXX (your Zadarma number)
      → Zadarma forwards to WhatsApp on your phone
      → Phone rings as a WhatsApp call
      ✗ This is impossible

Why:
  1. WhatsApp calls can only come FROM other WhatsApp users
  2. WhatsApp does not have a SIP interface or any way to receive forwarded calls
  3. WhatsApp numbers must be verified mobile numbers (VoIP numbers are rejected)
  4. There is no API to "inject" a call into WhatsApp — it's a completely separate network
```

WhatsApp is like a private walkie-talkie system — only people with the same walkie-talkie can talk to each other. A regular phone call cannot be bridged into it.

### What you CAN do: SIP softphone app on your mobile phone

The **real solution** is even better than WhatsApp: install a **SIP softphone app** on your mobile phone. This app connects to Zadarma over WiFi and rings when someone calls your business number.

#### How it works

```
Customer calls +34 9XX XXX XXX (your Zadarma virtual number)
    ↓
Zadarma Cloud PBX receives the call
    ↓
PBX routes to your registered devices (all ring simultaneously):
    ├── Desktop softphone (Zadarma app on your PC)
    ├── Mobile softphone (Zadarma app on your Android/iPhone via WiFi)
    └── Web browser (Zadarma WebRTC client)
    ↓
You answer on whichever device is handy
    ↓
Voice travels over WiFi → Starlink → Internet → Zadarma → Customer
```

#### Step-by-step: mobile phone setup

1. **Connect your phone to your WiFi network** (which goes through Starlink)
2. **Install the Zadarma app** from Google Play (Android) or App Store (iOS)
   - Alternative: Zoiper (free) or Linphone (free, open source)
3. **Enter your Zadarma SIP credentials** (provided in your Zadarma dashboard)
4. **Your phone is now a VoIP phone** — it rings when customers call your business number

#### Android-specific settings for reliable WiFi-only operation

Since your phone has **no mobile network** (no cell signal), you need these settings:

| Setting | Where | Value | Why |
|---|---|---|---|
| **Airplane mode** | Android Settings → Network | ON | Prevents the phone from searching for cell towers (saves battery) |
| **WiFi** | Android Settings → Network | ON (override airplane mode) | Keeps WiFi active while airplane mode blocks cellular |
| **Keep WiFi on during sleep** | WiFi → Advanced settings | Always | Prevents WiFi disconnection when screen is off |
| **Battery optimization** | Settings → Battery → App settings | Unrestricted for Zadarma/Zoiper | Prevents Android from killing the app in background |
| **Background data** | Settings → Apps → Zadarma → Data | Unrestricted | Allows the app to receive calls while in background |
| **STUN server** | In the softphone app | Use Zadarma's STUN server | Helps with Starlink's CGNAT NAT traversal |

#### Zadarma app vs. Zoiper vs. Linphone

| Feature | Zadarma App | Zoiper Free | Linphone |
|---|---|---|---|
| **Cost** | Free | Free | Free (open source) |
| **Auto-configured for Zadarma** | ✓ (one-click login) | Manual SIP setup | Manual SIP setup |
| **Push notifications** | ✓ (reliable even when app is in background) | ✓ (paid feature in Zoiper, free in Zoiper 5 with limitations) | ✓ |
| **WiFi-only mode** | ✓ | ✓ | ✓ |
| **Call quality** | Good | Good | Good |
| **Battery usage** | Low | Very low | Low |
| **Multiple accounts** | ✓ | ✓ (paid) | ✓ |
| **Recommendation** | ★ Best for Zadarma | Good alternative | Good if you prefer open source |

**Recommendation: Use the Zadarma app.** It's pre-configured for their service, supports push notifications (so calls ring reliably even when the app is sleeping), and requires zero SIP configuration.

### What about receiving a WhatsApp MESSAGE when you miss a call?

This IS possible, via automation:

```
Customer calls your Zadarma number
    ↓
You don't answer → voicemail
    ↓
Zadarma webhook fires → your FacturaScripts plugin logs the call
    ↓
Plugin ALSO triggers a WhatsApp Business API notification to your phone:
    "📞 Missed call from +34 612 345 678 (Juan García, Order ORD-A1B2C3D4)
     Voicemail: [link to recording]
     Tap to call back: [link]"
```

This uses the **WhatsApp Business API** for messaging (not calling). It requires:
- A WhatsApp Business account
- A third-party integration service (e.g. Twilio, SendPulse, or Albato)
- Additional cost (~€10–30/month for the messaging API)

**This is a Phase 3 enhancement**, not needed initially. For now, Zadarma can send missed-call notifications via **email** (free, already included).

### Comparison: What you wanted vs. what's possible

| Scenario | WhatsApp Call | SIP Softphone App | Verdict |
|---|---|---|---|
| **Phone rings on your mobile** | ✗ Not possible | ✓ Yes, over WiFi | SIP app ✓ |
| **Works with no mobile network** | ✗ WhatsApp needs either mobile data or WiFi, but calls only work WhatsApp-to-WhatsApp | ✓ Works on WiFi only | SIP app ✓ |
| **Caller dials your business number** | ✗ Would need WhatsApp contact | ✓ Any phone can call your number | SIP app ✓ |
| **Shows on your phone like a normal call** | ✗ | ✓ (rings like a phone call, with caller ID) | SIP app ✓ |
| **Call quality** | Good | Good | Equal |
| **You can call back showing your business number** | ✗ | ✓ | SIP app ✓ |
| **Free** | N/A | ✓ (Zadarma app) | SIP app ✓ |

**The SIP softphone is actually better than WhatsApp** for this use case, because:
1. **Any phone** (mobile, landline) can call your number — they don't need WhatsApp
2. Your **business number** appears as the caller ID when you call back
3. It works **exactly like a regular phone**, but over WiFi
4. It's **free** (the Zadarma app costs nothing)

---

## 3. Updated Recommendation

Based on this analysis, the original recommendation is updated:

### What to do — revised plan

#### Phase 1: Immediate (this week, ~30 minutes, no coding)

1. **Sign up at zadarma.com** — create a free account (Standard plan, €0/month)
2. **Register a Spanish geographic number** from your province (~€3.60/month)
3. **Complete identity verification** (DNI/passport + address proof)
4. **Install the Zadarma app** on:
   - Your **PC/laptop** (for when you're at your desk)
   - Your **mobile phone** (for when you're moving around — connected to WiFi/Starlink)
5. **On your mobile phone:**
   - Enable Airplane Mode + WiFi ON
   - Disable battery optimization for the Zadarma app
   - Set "Keep WiFi on during sleep" to Always
6. **Configure Zadarma PBX** (in their web panel):
   - Record a voicemail greeting in Spanish
   - Set ring time: 20 seconds before voicemail
   - Enable email notification for voicemail (with MP3 attachment)
   - Set business hours routing (optional)
7. **Test:** Have a friend call your new number
   - Both your PC and mobile should ring simultaneously
   - If you don't answer after 20 seconds, caller gets voicemail
   - You receive email with voicemail MP3

**Cost: ~€3.60/month. No coding needed. Working phone system in one afternoon.**

#### Phase 2: After 2–4 weeks (when you understand your call patterns)

1. **Enable Zadarma webhooks** in their dashboard, pointing to your FacturaScripts server
2. **Build the FS CRM plugin** (separate plugin, as decided in the Architecture Decision document):
   - Webhook controller to receive `NOTIFY_START`, `NOTIFY_END`, `NOTIFY_RECORD`
   - Call log model and database table
   - Admin list/edit views for call history
   - Customer matching logic (phone number → order/customer)
   - Backup API polling cron job (every 15 minutes)
3. **Cost: ~€3.60/month (unchanged). Development time: 16–20 hours.**

#### Phase 3: Optional enhancements (as needed)

- WhatsApp missed-call notifications (via WhatsApp Business API + Albato/Zapier)
- Call recording playback in FacturaScripts
- Click-to-call from order views
- Call statistics dashboard
- Voicemail transcription (Zadarma speech-to-text)

### Cost summary (revised)

| Phase | Monthly cost | One-time effort |
|---|---|---|
| Phase 1 (now) | ~€3.60 | 30 minutes setup |
| Phase 2 (after 2–4 weeks) | ~€3.60 (unchanged) | 16–20 hours development |
| Phase 3 (optional) | ~€3.60 + WhatsApp API if desired | Additional development |

This is significantly cheaper than the original €15–40/month estimate for "Solution A", because Zadarma's free Standard plan includes everything you need.
