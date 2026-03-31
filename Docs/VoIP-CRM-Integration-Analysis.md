# VoIP-CRM Integration Analysis for FacturaScripts Ecommerce

**Date:** March 2026  
**Context:** Solo olive wood business in an isolated mountain area with Starlink internet, no mobile coverage, no staff for phone calls.  
**Goal:** Integrate a geo-specific virtual phone number with the FacturaScripts ecommerce plugin to manage incoming calls, voicemail, and callbacks through simple CRM functionality.

> **Decision taken:** Solution A (Cloud PBX with Zadarma) will be implemented as a **separate FacturaScripts plugin**. See [Architecture Decision: VoIP Plugin](Architecture-Decision-VoIP-Plugin.md) for the full rationale.
>
> **Follow-up analysis:** The cost and feature differences between Solution A and C largely disappear when using Zadarma specifically — their free Standard plan includes Cloud PBX + webhooks + API. Calls can ring on a mobile phone via WiFi using a SIP softphone app (not WhatsApp). See [Solution A vs. C Deep Dive](Solution-A-vs-C-Deep-Dive.md) for full details.

---

## Table of Contents

1. [Your Specific Situation](#1-your-specific-situation)
2. [VoIP Fundamentals — How It Works](#2-voip-fundamentals--how-it-works)
3. [What Is a Geo-Specific Virtual Number?](#3-what-is-a-geo-specific-virtual-number)
4. [How Call Routing Works](#4-how-call-routing-works)
5. [Architecture Options](#5-architecture-options)
6. [Solution Comparison Matrix](#6-solution-comparison-matrix)
7. [Solution A — Cloud PBX with Webhook Integration](#7-solution-a--cloud-pbx-with-webhook-integration)
8. [Solution B — SIP Trunk with Self-Hosted PBX](#8-solution-b--sip-trunk-with-self-hosted-pbx)
9. [Solution C — Hybrid: Cloud VoIP + Simple CRM Log via API](#9-solution-c--hybrid-cloud-voip--simple-crm-log-via-api)
10. [Solution D — Minimal: Virtual Number with Email Notifications](#10-solution-d--minimal-virtual-number-with-email-notifications)
11. [Starlink-Specific Considerations](#11-starlink-specific-considerations)
12. [Call Flow Diagrams](#12-call-flow-diagrams)
13. [What Would Change in This Plugin](#13-what-would-change-in-this-plugin)
14. [Recommended Providers](#14-recommended-providers)
15. [Cost Estimates](#15-cost-estimates)
16. [Recommendation](#16-recommendation)
17. [Glossary](#17-glossary)

---

## 1. Your Specific Situation

Before analysing solutions, let's clearly state the constraints:

| Constraint | Detail |
|---|---|
| **Location** | Isolated mountain area, no mobile network coverage |
| **Internet** | Starlink satellite connection (good bandwidth ~50–200 Mbps, but variable latency ~25–60 ms, occasional dropouts) |
| **Staff** | None — you work alone |
| **Call availability** | Can personally answer calls only sometimes |
| **Voicemail** | Needed for when you cannot answer |
| **Callback** | You want the ability to return calls |
| **Virtual number** | Will register a geo-specific number (e.g. a Spanish landline number like +34 9XX XXX XXX) |
| **CRM** | Needs to integrate with FacturaScripts (this ecommerce plugin already stores customer name, email, phone, and order details) |
| **Technical skill** | No prior VoIP experience |

### What you need the system to do

1. **Customer calls your virtual number** → the call reaches you over the internet (via Starlink)
2. **If you're available** → you answer on a computer, tablet, or a VoIP desk phone connected to your local network
3. **If you're not available** → the caller hears a professional greeting and can leave a voicemail
4. **Voicemail arrives** → you get notified (email, or a log in FacturaScripts)
5. **You return the call** → you call back from the same virtual number (so the customer sees your business number, not a personal number)
6. **Call is logged** → the CRM (FacturaScripts) records who called, when, duration, and any notes you add

---

## 2. VoIP Fundamentals — How It Works

### What is VoIP?

**VoIP** stands for **Voice over Internet Protocol**. Instead of using traditional phone lines (copper wires or mobile towers), VoIP converts your voice into digital data packets and sends them over the internet — exactly like a video call or streaming music, but optimised for real-time two-way audio.

### How a phone call becomes internet data

```
Your voice → Microphone → Analogue-to-Digital Converter → Compressed audio packets
    → Sent over internet (UDP protocol) → Received by the other end
    → Decompressed → Speaker → The other person hears you
```

This happens in **both directions simultaneously** (full-duplex), with packets being sent roughly every 20 milliseconds.

### Key VoIP protocols

| Protocol | What it does | Analogy |
|---|---|---|
| **SIP** (Session Initiation Protocol) | Sets up, manages, and ends calls. "Ring ring, pick up, hang up." | The postal system that delivers an invitation to a meeting |
| **RTP** (Real-time Transport Protocol) | Carries the actual audio data during a call | The actual conversation at the meeting |
| **SRTP** | Encrypted version of RTP | A private, secure conversation |
| **WebRTC** | Browser-based real-time communication (no app needed) | A video call directly in your web browser |

### What you need for VoIP to work

1. **Internet connection** — You have Starlink ✓
2. **A VoIP account** — From a VoIP provider (like a "phone company for internet calls")
3. **A device to make/receive calls** — Options:
   - **Softphone app** on your computer or tablet (free software)
   - **VoIP desk phone** (hardware, ~€50–200) plugged into your router
   - **Web browser** (some providers offer a browser-based phone)
   - **ATA adapter** (Analogue Telephone Adapter, ~€30–50) — lets you plug a regular phone into your router

### How VoIP differs from a regular phone

| Regular phone | VoIP |
|---|---|
| Needs a phone line or mobile signal | Needs only internet |
| Fixed location (landline) or mobile coverage | Works anywhere with internet |
| One number per line | Can have many numbers on one device |
| Simple setup | Slightly more technical to set up |
| Calls cost money per minute | Calls often included in monthly plan or much cheaper |

**For your situation:** Since you have no mobile coverage but you do have Starlink internet, VoIP is the **only way** to have a phone service. This is a perfect use case.

---

## 3. What Is a Geo-Specific Virtual Number?

### Virtual number explained

A **virtual number** is a phone number that is **not tied to a physical phone line or SIM card**. It exists only as a routing instruction in a phone company's computer system: "When someone calls this number, send the call to [destination X]."

### Geo-specific

**Geo-specific** means the number looks like it belongs to a specific geographic area. In Spain:

- **+34 91X XXX XXX** — looks like a Madrid landline
- **+34 95X XXX XXX** — looks like a Seville/Andalusia landline
- **+34 96X XXX XXX** — looks like a Valencia landline

This gives your business a local presence. A customer in Madrid calling a Madrid number feels like they're calling a local business — even though the call is actually routed to your mountain location via the internet.

### How it works in practice

```
Customer dials +34 91X XXX XXX (your Madrid virtual number)
    ↓
Spanish phone network routes call to your VoIP provider's server
    ↓
VoIP provider's server converts call to internet data (SIP/RTP)
    ↓
Internet data travels to your Starlink connection
    ↓
Your softphone/VoIP phone rings
    ↓
You answer → voice travels back the same way
```

### Registering a virtual number in Spain

To get a Spanish geo-specific virtual number, you typically:

1. **Choose a VoIP provider** that offers Spanish numbers (see [Recommended Providers](#14-recommended-providers))
2. **Provide identity verification** — Spanish regulations (CNMC) require proof of identity and address for number registration
3. **Select your area code** — choose the province/city you want (e.g. your business registration area)
4. **Pay a monthly fee** — typically €3–15/month for a Spanish DID (Direct Inward Dialling) number

---

## 4. How Call Routing Works

Call routing is the set of rules that determine **what happens when someone calls your number**. Think of it as a flowchart the phone system follows automatically.

### Basic routing rules you can configure

```
Incoming call to your virtual number
    │
    ├─ Rule 1: Ring your softphone/VoIP phone for 20 seconds
    │   ├─ You answer → Call connected ✓
    │   └─ No answer → Continue to Rule 2
    │
    ├─ Rule 2: Play voicemail greeting
    │   "Hello, you've reached [Business Name]. I'm not available right now.
    │    Please leave a message with your name and number, and I'll call you back."
    │   ├─ Caller leaves message → Voicemail saved ✓
    │   └─ Caller hangs up → Missed call logged ✓
    │
    └─ Rule 3: Send notification
        ├─ Email with voicemail audio attached
        └─ (Optional) Log call in CRM / FacturaScripts
```

### Time-based routing (advanced)

```
Incoming call
    │
    ├─ Is it Monday–Friday, 9:00–14:00?
    │   ├─ YES → Ring your phone for 25 seconds, then voicemail
    │   └─ NO → Go directly to voicemail with "outside business hours" greeting
    │
    └─ Is it Saturday/Sunday or holiday?
        └─ Go to "closed" greeting
```

### What this means for you

You can configure different behaviours depending on:
- **Time of day** — business hours vs. after hours
- **Day of week** — weekdays vs. weekends
- **Your status** — you manually set yourself as "available" or "away"
- **Caller ID** — known customers could get priority routing

---

## 5. Architecture Options

There are four main approaches to integrating VoIP with a CRM like FacturaScripts, ranging from simple to complex:

### Overview

```
Complexity:  LOW ◄──────────────────────────────► HIGH
Cost:        LOW ◄──────────────────────────────► HIGH
Control:     LOW ◄──────────────────────────────► HIGH

   Solution D        Solution C       Solution A       Solution B
   (Minimal)         (Hybrid)         (Cloud PBX)      (Self-hosted)

   Virtual number    Virtual number   Virtual number   Virtual number
   + Voicemail       + Cloud VoIP     + Cloud PBX      + SIP trunk
   + Email notify    + API webhook    + Webhooks       + Asterisk/FreePBX
                     + FS plugin      + FS plugin      + FS plugin
```

---

## 6. Solution Comparison Matrix

> **Note (updated March 2026):** The cost estimates below assumed generic providers at different tiers. With Zadarma specifically, Solutions A and C cost the same (~€4/month) because Zadarma's free Standard plan includes Cloud PBX + webhooks + API. See [Solution A vs. C Deep Dive](Solution-A-vs-C-Deep-Dive.md) for detailed analysis.

| Feature | D: Minimal | C: Hybrid API | A: Cloud PBX | B: Self-hosted PBX |
|---|:---:|:---:|:---:|:---:|
| **Monthly cost** | €5–10 | €10–25 | €15–40 | €10–20 + server |
| **Monthly cost (Zadarma)** | ~€4 | ~€4 | ~€4 (Standard) or €22 (Office) | N/A |
| **Setup difficulty** | ★☆☆☆☆ | ★★☆☆☆ | ★★★☆☆ | ★★★★★ |
| **Maintenance** | None | Low | Low | High |
| **Answer calls on computer** | No | Yes | Yes | Yes |
| **Answer calls on mobile (WiFi)** | No | Yes (SIP app) | Yes (SIP app) | Yes (SIP app) |
| **Voicemail** | Yes (provider) | Yes | Yes | Yes |
| **Call back from business number** | Maybe | Yes | Yes | Yes |
| **Auto-log calls in FacturaScripts** | No (manual) | Yes | Yes | Yes |
| **Match caller to customer** | No | Yes | Yes | Yes |
| **Voicemail transcription** | Some providers | Some providers | Yes | With add-on |
| **Call recording** | No | Some providers | Yes | Yes |
| **Works with Starlink** | Yes | Yes | Yes (best) | Yes (needs tuning) |
| **Needs FS plugin changes** | No | Yes (small) | Yes (medium) | Yes (large) |
| **Risk if internet drops** | Voicemail catches call | Voicemail catches call | Cloud handles it | Calls lost |
| **Recommended for you** | Short-term start | ★ **Best fit** ★ | Good if budget allows | Overkill |

---

## 7. Solution A — Cloud PBX with Webhook Integration

### What is a Cloud PBX?

A **PBX** (Private Branch Exchange) is a phone system that manages calls — routing, voicemail, hold music, menus ("Press 1 for sales, 2 for support"), extensions, and more. Traditionally this was expensive hardware in an office. A **Cloud PBX** is the same thing, but hosted by a provider on their servers. You just configure it through a web interface.

### How it works

```
1. You sign up with a Cloud PBX provider (e.g. Zadarma, 3CX, Cloudtalk)
2. You get a Spanish virtual number
3. You configure call rules in their web panel:
   - Ring your softphone → if no answer → voicemail
4. The provider sends "webhooks" to your FacturaScripts server:
   - When a call starts: POST https://your-facturascripts.com/webhook/call-start
   - When a call ends: POST https://your-facturascripts.com/webhook/call-end
   - When voicemail is left: POST https://your-facturascripts.com/webhook/voicemail
5. Your FS plugin receives these webhooks and:
   - Logs the call (caller number, time, duration)
   - Matches the phone number to an existing customer
   - Creates a CRM activity record
   - Notifies you if you have a new voicemail
```

### What is a webhook?

A **webhook** is a way for one system to notify another system automatically when something happens. It's an HTTP request (like loading a web page, but from server to server).

**Example webhook payload** (what the VoIP provider sends to your server):

```json
{
  "event": "call.completed",
  "call_id": "abc-123-def",
  "direction": "inbound",
  "caller_number": "+34612345678",
  "called_number": "+34911234567",
  "started_at": "2026-03-07T10:30:00Z",
  "ended_at": "2026-03-07T10:35:22Z",
  "duration_seconds": 322,
  "status": "answered",
  "recording_url": "https://provider.com/recordings/abc-123-def.mp3",
  "voicemail": false
}
```

Your FacturaScripts plugin would receive this data and store it in the database, linked to the customer.

### Pros

- Professional phone system with many features
- Provider handles all the telephony complexity
- Automatic call logging via webhooks
- Voicemail, call recording, time-based routing all included
- Works well with Starlink (the cloud handles routing even if your internet drops)

### Cons

- Higher monthly cost (~€15–40/month)
- Requires building a FacturaScripts plugin to receive webhooks
- More complex initial setup
- Provider lock-in

### Providers for this approach

- **Zadarma** (zadarma.com) — Spanish numbers, PBX included, webhooks API, from €0/month + per-minute
- **3CX** (3cx.com) — Free tier for up to 10 users, Spanish numbers via SIP trunk
- **Cloudtalk** (cloudtalk.io) — CRM-focused, Spanish numbers, webhooks, from €25/month
- **Aircall** (aircall.io) — CRM integration focused, from €30/month

---

## 8. Solution B — SIP Trunk with Self-Hosted PBX

### What is a SIP trunk?

A **SIP trunk** is a direct connection between a VoIP provider and your own phone server. Think of it as a "raw phone line" delivered over the internet. Unlike a Cloud PBX where the provider manages the phone system, here **you** run your own phone server software.

### What is Asterisk/FreePBX?

**Asterisk** is free, open-source phone server software that runs on Linux. **FreePBX** is a web interface for Asterisk. Together they let you build a complete phone system on your own hardware or a virtual server.

### How it works

```
1. You rent a Spanish virtual number from a SIP trunk provider (e.g. VoIPstunt, Sipgate)
2. You install Asterisk + FreePBX on:
   - A Raspberry Pi at home (behind Starlink router) — cheapest
   - OR a cloud VPS (e.g. Hetzner €4/month) — more reliable
3. You configure Asterisk to handle:
   - Incoming calls → ring your softphone → voicemail
   - Outgoing calls → use your business number as caller ID
4. You write Asterisk dial-plan scripts or use the AGI (Asterisk Gateway Interface)
   to call your FacturaScripts API when calls happen
5. Your FS plugin exposes an API endpoint that Asterisk calls to log the activity
```

### Pros

- Maximum control over every aspect of the phone system
- Lowest per-minute costs (SIP trunking is the cheapest way to route calls)
- Can handle complex routing, IVR menus, call queues
- No vendor lock-in for the PBX itself
- Huge open-source community

### Cons

- **Very complex setup** — requires Linux administration, SIP configuration, firewall rules, NAT traversal
- **Ongoing maintenance** — security updates, debugging call quality issues
- **Starlink NAT issues** — Starlink uses CGNAT (Carrier-Grade NAT), which complicates SIP registration. You may need a cloud VPS as a relay
- **Overkill for a single user** — PBX systems are designed for offices with multiple extensions
- **Requires significant technical knowledge** — or hiring someone to set it up

### Why this is NOT recommended for your situation

Running a self-hosted PBX requires ongoing Linux system administration. With Starlink's CGNAT and variable latency, you'd need a cloud relay server anyway, which eliminates the main advantage (local control). For a single user with no VoIP experience, this adds complexity without proportional benefit.

---

## 9. Solution C — Hybrid: Cloud VoIP + Simple CRM Log via API

### ★ This is the recommended approach ★

This is the sweet spot between simplicity and functionality. You use a cloud VoIP provider for the phone handling, but integrate it with FacturaScripts using the provider's API to log calls.

### How it works

```
┌─────────────────────────────────────────────────────────────┐
│                    CLOUD (Provider's servers)                │
│                                                             │
│  Spanish Virtual Number (+34 9XX XXX XXX)                   │
│         │                                                   │
│         ▼                                                   │
│  ┌─────────────┐    Call rules:                             │
│  │  VoIP Cloud  │    1. Ring your softphone 20s             │
│  │   Server     │    2. If no answer → voicemail            │
│  │              │    3. Send email notification              │
│  └──────┬───────┘    4. Log call via API/webhook            │
│         │                                                   │
└─────────┼───────────────────────────────────────────────────┘
          │ Internet (SIP + RTP over Starlink)
          │
┌─────────┼───────────────────────────────────────────────────┐
│  YOUR LOCATION (Mountain, Starlink)                         │
│         │                                                   │
│         ▼                                                   │
│  ┌─────────────┐         ┌──────────────────────────┐       │
│  │  Softphone   │         │  FacturaScripts Server   │       │
│  │  App on PC   │         │  (your-domain.com)       │       │
│  │  or Tablet   │         │                          │       │
│  │              │         │  Receives webhook:       │       │
│  │  You answer  │         │  "Call from +3461234567" │       │
│  │  or miss     │         │  → Logs in CRM           │       │
│  │  the call    │         │  → Matches to Customer   │       │
│  └─────────────┘         └──────────────────────────┘       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Step-by-step setup

#### Step 1: Choose a VoIP provider with API access

Select a provider that offers:
- Spanish geographic numbers (DID)
- Softphone app or WebRTC support
- Voicemail with email notification
- API or webhooks for call events
- Reasonable pricing for low call volume

**Recommended: Zadarma** (see [Recommended Providers](#14-recommended-providers) for details)

#### Step 2: Register your virtual number

1. Create an account at the provider's website
2. Complete identity verification (passport/DNI + proof of address)
3. Select a Spanish geographic number from your province
4. Pay the monthly fee (typically €3–10/month)

#### Step 3: Configure call routing in the provider's panel

Set up rules:

| Priority | Condition | Action |
|---|---|---|
| 1 | Call arrives | Ring softphone for 20 seconds |
| 2 | No answer after 20s | Play voicemail greeting |
| 3 | Voicemail left | Send email with audio file |
| 4 | Any call event | Send webhook to your FS server |
| 5 | Outside business hours | Go directly to voicemail |

#### Step 4: Install a softphone

A **softphone** is an application that turns your computer, tablet, or smartphone into a VoIP phone. You configure it with your VoIP account credentials.

**Free softphone options:**

| Platform | Software | Notes |
|---|---|---|
| **Windows/Mac/Linux** | Zoiper (free tier) | Simple, reliable, supports most providers |
| **Windows/Mac/Linux** | MicroSIP (Windows only) | Very lightweight, open source |
| **Android/iOS** | Zoiper mobile | Works on WiFi (connected to Starlink) |
| **Web browser** | Provider's WebRTC client | No install needed, works in Chrome/Firefox |
| **Linux** | Linphone | Open source, full-featured |

**Softphone configuration** requires these settings from your VoIP provider:

```
SIP Server:     sip.provider.com
Username:       your_sip_username
Password:       your_sip_password
Port:           5060 (or 5061 for TLS)
Transport:      UDP or TLS (prefer TLS for encryption)
STUN Server:    stun.provider.com:3478 (needed for Starlink NAT traversal)
```

The provider gives you these credentials when you register. You enter them in the softphone app — then calls to your virtual number will ring on your computer/tablet.

#### Step 5: Configure the FacturaScripts integration

This is where a small FacturaScripts plugin extension would receive call data:

**Option A — Provider polls (simplest):**
- Use a scheduled FacturaScripts cron job to periodically fetch your call history from the provider's API
- Every 5–15 minutes, query the API for new calls and store them in the database
- No webhook needed — simpler to implement, slight delay in logging

**Option B — Webhooks (real-time):**
- Configure the provider to send HTTP POST requests to your FS server when call events happen
- Your FS plugin has a controller that receives these webhooks and logs the call data
- Real-time, but requires your FS server to be publicly accessible

**Option C — Manual logging with pre-filled data:**
- The simplest approach: when you receive a voicemail notification email, you manually open FacturaScripts, search for the customer by phone number, and add a note
- The plugin could provide a "Call Log" section where you enter the phone number and it auto-finds the matching customer

#### Step 6: Making outbound calls (callbacks)

To return a call showing your business number:

1. Open your softphone app
2. Dial the customer's number
3. The call routes through your VoIP provider
4. The customer sees your virtual business number on their caller ID
5. The call is logged the same way as inbound calls

### Pros

- **Balanced complexity** — the hard telephony stuff is handled by the cloud provider
- **Resilient** — if your Starlink drops, voicemail still works (it's in the cloud)
- **Affordable** — €10–25/month total
- **Scalable** — if you later hire someone, they just install the softphone too
- **Professional** — customers see a proper business number
- **CRM integration** — calls linked to customers in FacturaScripts

### Cons

- Requires a small FacturaScripts plugin extension (for call logging)
- Provider API varies — integration is provider-specific
- Starlink latency may occasionally affect call quality

---

## 10. Solution D — Minimal: Virtual Number with Email Notifications

### The simplest possible approach (good starting point)

If you want to start immediately with **zero development** and upgrade later:

### How it works

```
Customer calls your virtual number
    ↓
VoIP provider tries to ring your softphone
    ↓
No answer after 20 seconds → voicemail plays
    ↓
Caller leaves message
    ↓
Provider sends you an email:
  Subject: "New voicemail from +34 612 345 678"
  Attachment: voicemail.mp3
    ↓
You listen to the message
    ↓
You manually look up the customer in FacturaScripts
    ↓
You call them back from your softphone
```

### What you need

1. **VoIP provider account** with a Spanish virtual number (~€5–10/month)
2. **Softphone app** on your computer (free)
3. **Email account** (you already have one)

### No FacturaScripts changes needed

You handle the CRM part manually:
- Check email for voicemail notifications
- Open FacturaScripts admin → Orders list
- Search by phone number to find the customer
- Add notes to the order manually

### When to use this approach

- **Right now** — to start immediately while evaluating VoIP
- **Testing phase** — to understand call patterns before investing in automation
- **Very low call volume** — if you get fewer than 2–3 calls per day

### Upgrade path

Start with Solution D, then upgrade to Solution C by adding the FacturaScripts integration once you understand your call patterns and volume.

---

## 11. Starlink-Specific Considerations

Starlink presents unique challenges and advantages for VoIP. Understanding these is critical for your setup.

### Starlink network characteristics

| Parameter | Typical value | Impact on VoIP |
|---|---|---|
| **Download speed** | 50–200 Mbps | Excellent — VoIP needs only 0.1 Mbps |
| **Upload speed** | 10–40 Mbps | Excellent — VoIP needs only 0.1 Mbps |
| **Latency** | 25–60 ms | Acceptable — below the 150 ms threshold for comfortable calls |
| **Jitter** | 5–30 ms | Moderate — may cause occasional audio glitches |
| **Packet loss** | 0.5–2% | Moderate — can cause brief audio dropouts |
| **Outages** | Brief (seconds) during satellite handoffs | Voicemail catches calls during outages |
| **CGNAT** | Yes (Carrier-Grade NAT) | Complicates direct SIP — use STUN/TURN or provider's relay |

### What is CGNAT and why it matters

**NAT** (Network Address Translation) lets multiple devices share one public IP address. **CGNAT** means Starlink itself adds another layer of NAT — your traffic goes through **two** levels of address translation before reaching the internet.

This matters for VoIP because SIP protocol needs to know your real IP address to route audio correctly. With CGNAT, the VoIP server sees Starlink's IP, not yours.

**Solutions:**
1. **STUN server** — a third-party server that tells your softphone what its public IP looks like from the outside (most providers include this)
2. **TURN server** — relays audio through a server if direct connection fails (slightly higher latency, but always works)
3. **Use the provider's WebRTC client** — browser-based calling avoids SIP NAT issues entirely

### Recommendations for Starlink VoIP

1. **Use a Cloud VoIP provider** (Solutions A or C) — the cloud handles routing, so your Starlink quirks are less impactful
2. **Prefer WebRTC** over traditional SIP — WebRTC handles NAT traversal better
3. **Configure STUN/TURN** in your softphone — use the provider's STUN/TURN servers
4. **Enable voicemail** — it catches calls during Starlink's brief satellite handoff outages
5. **Use the Opus codec** — modern audio codec that handles jitter and packet loss better than older codecs (G.711)
6. **Consider a Starlink priority plan** — if call quality is poor, Starlink's priority tier has better consistency
7. **Position your Starlink dish** with clear sky view — obstructions cause more frequent handoffs and outages

### Starlink and VoIP quality expectations

For your use case (occasional personal calls, not a call center), Starlink VoIP quality will be:
- **Mostly good** — 90%+ of the time, calls will sound clear
- **Occasional glitches** — brief audio hiccups during satellite handoffs (every few minutes, lasting < 1 second)
- **Rare drops** — during severe weather or satellite constellation changes
- **Always backed by voicemail** — if a call drops or can't connect, the caller gets voicemail

This is perfectly acceptable for a small business receiving occasional calls.

---

## 12. Call Flow Diagrams

### Flow 1: Customer calls — you answer

```
Customer                   VoIP Provider              Your Softphone
   │                           │                           │
   │── Dials your number ─────>│                           │
   │                           │── SIP INVITE ────────────>│
   │                           │                           │── RINGS
   │                           │                           │
   │                           │<── 200 OK (you answer) ──│
   │                           │                           │
   │<══════════ Voice call connected (RTP audio) ═════════>│
   │                           │                           │
   │                           │── Webhook: call.started ──> FacturaScripts
   │                           │                           │        │
   │── Hangs up ──────────────>│                           │        │
   │                           │── BYE ───────────────────>│        │
   │                           │── Webhook: call.ended ────────────>│
   │                           │                           │  Logs: caller, time,
   │                           │                           │  duration, matched
   │                           │                           │  to customer record
```

### Flow 2: Customer calls — you don't answer (voicemail)

```
Customer                   VoIP Provider              Your Softphone
   │                           │                           │
   │── Dials your number ─────>│                           │
   │                           │── SIP INVITE ────────────>│
   │                           │                           │── RINGS (20 sec)
   │                           │                           │── No answer
   │                           │<── 408 Timeout ──────────│
   │                           │                           │
   │<── "Please leave a msg" ──│                           │
   │                           │                           │
   │── Leaves voicemail ──────>│                           │
   │                           │── Stores recording        │
   │                           │── Sends email with .mp3   │
   │── Hangs up ──────────────>│── Webhook: voicemail ────────────> FacturaScripts
   │                           │                           │         │
   │                           │                           │  Logs: missed call,
   │                           │                           │  voicemail URL,
   │                           │                           │  matched to customer
```

### Flow 3: You return a call

```
Your Softphone              VoIP Provider              Customer
   │                           │                           │
   │── Dial customer number ──>│                           │
   │                           │── Routes call ───────────>│
   │                           │   (shows YOUR business    │
   │                           │    number as caller ID)   │
   │                           │                           │── RINGS
   │                           │                           │
   │                           │<── Customer answers ──────│
   │                           │                           │
   │<══════════ Voice call connected (RTP audio) ═════════>│
   │                           │                           │
   │                           │── Webhook: call.started ──> FacturaScripts
```

---

## 13. What Would Change in This Plugin

This section outlines what FacturaScripts plugin modifications each solution would require. **No code is included** — only the architectural description.

### Solution D (Minimal) — No changes needed

The existing ecommerce plugin already stores `customer_phone` in `EcommerceOrder`. You would manually cross-reference phone numbers from voicemail emails with customer records in the admin panel.

### Solution C (Hybrid — Recommended) — Small additions

| Component | Purpose |
|---|---|
| **New database table: `ecommerce_call_log`** | Stores call records: id, phone_number, direction (inbound/outbound), timestamp, duration, status (answered/missed/voicemail), voicemail_url, notes, matched codcliente |
| **New Model: `EcommerceCallLog`** | FacturaScripts model for the call log table |
| **New Controller: `ListEcommerceCallLog`** | Admin list view of all calls, searchable by phone number, date, status |
| **New Controller: `EditEcommerceCallLog`** | Edit a call log entry, add notes, link to customer |
| **New Controller or API endpoint** | Receives webhook POST from VoIP provider, creates call log entry, auto-matches phone number to existing `EcommerceOrder.customer_phone` or FS `Cliente` |
| **XMLView files** | List and edit views for the call log |
| **Translation keys** | Call log labels in all 4 languages |
| **Optional: Cron job** | If using API polling instead of webhooks — a scheduled task that fetches recent calls from the VoIP provider API every 5–15 minutes |

### Solution A (Cloud PBX) — Medium additions

Same as Solution C, plus:
- More complex webhook handling (multiple event types)
- Call recording playback integration
- Possibly an IVR (Interactive Voice Response) configuration panel

### Solution B (Self-hosted PBX) — Large additions

Same as Solution A, plus:
- Asterisk AGI scripts
- SIP configuration management
- Complex debugging and monitoring

### Customer matching logic

When a call comes in, the system would try to match the caller's phone number:

```
1. Search ecommerce_orders.customer_phone for the caller's number
   → If found: link call log to that order and customer

2. Search FacturaScripts contactos table (if available)
   → If found: link call log to that contact/client

3. No match found:
   → Create call log with "Unknown caller" flag
   → Admin can manually link it to a customer later
```

---

## 14. Recommended Providers

### For Spanish virtual numbers with API/webhook support

#### 1. Zadarma (★ Best fit for your case)

| Feature | Details |
|---|---|
| **Website** | zadarma.com |
| **Spanish numbers** | Yes — geographic numbers from most provinces |
| **Monthly cost** | Number: ~€3.60/month, Free PBX included |
| **Per-minute cost** | Inbound: free (caller pays), Outbound to Spain: ~€0.01/min |
| **Softphone** | Own app (Zadarma) for Windows, Mac, iOS, Android |
| **WebRTC** | Yes — browser-based calling |
| **Voicemail** | Yes — email notification with MP3 |
| **API** | Full REST API for call history, webhooks for real-time events |
| **Webhooks** | Yes — NOTIFY_START, NOTIFY_END, NOTIFY_RECORD, NOTIFY_IVR |
| **Call recording** | Yes — stored in cloud, accessible via API |
| **Languages** | Interface in Spanish, English, and many more |
| **Setup difficulty** | Low — web-based PBX configuration |
| **ID verification** | Required for Spanish numbers (passport/DNI) |

**Why Zadarma fits your case:**
- Free PBX included (no extra cost for call routing and voicemail)
- Spanish geographic numbers available
- Full API and webhooks for FacturaScripts integration
- Very low cost for low call volume
- Softphone app included
- WebRTC option avoids Starlink CGNAT issues

#### 2. Fonvirtual

| Feature | Details |
|---|---|
| **Website** | fonvirtual.com |
| **Spanish numbers** | Yes — specialises in Spanish virtual numbers |
| **Monthly cost** | From ~€9.95/month |
| **Focus** | Spanish market, multilingual virtual receptionist |
| **Voicemail** | Yes |
| **API** | Limited — less automation-friendly |
| **Best for** | If you want a purely managed solution with no technical setup |

#### 3. Netelip

| Feature | Details |
|---|---|
| **Website** | netelip.com |
| **Spanish numbers** | Yes — geographic and mobile |
| **Monthly cost** | From ~€4.95/month |
| **API** | REST API available |
| **Webhooks** | Yes |
| **Best for** | Budget-friendly Spanish provider with API access |

#### 4. Twilio (developer-focused)

| Feature | Details |
|---|---|
| **Website** | twilio.com |
| **Spanish numbers** | Yes |
| **Monthly cost** | Number: ~€3/month + per-minute usage |
| **API** | Extremely powerful — full programmable voice |
| **Webhooks** | Yes — highly customisable call flows via TwiML |
| **Best for** | Maximum flexibility and custom integration |
| **Downside** | Requires developer skills, no built-in PBX or softphone |

---

## 15. Cost Estimates

### Solution D (Minimal) — Starting immediately

| Item | Monthly cost | One-time cost |
|---|---|---|
| Zadarma Spanish DID number | ~€3.60 | €0 |
| Softphone (Zoiper free) | €0 | €0 |
| **Total** | **~€4/month** | **€0** |

### Solution C (Hybrid — Recommended)

| Item | Monthly cost | One-time cost |
|---|---|---|
| Zadarma Spanish DID number | ~€3.60 | €0 |
| Zadarma PBX (included free) | €0 | €0 |
| Softphone (Zoiper free) | €0 | €0 |
| FacturaScripts plugin development | €0 (self-developed) | 8–16 hours of work |
| **Total** | **~€4/month** | **8–16 hours** |

### Solution A (Cloud PBX)

| Item | Monthly cost | One-time cost |
|---|---|---|
| Cloud PBX provider | €15–40 | €0 |
| Spanish DID number (usually included) | included | €0 |
| FacturaScripts plugin development | €0 (self-developed) | 16–24 hours of work |
| **Total** | **~€15–40/month** | **16–24 hours** |

### Solution B (Self-hosted PBX)

| Item | Monthly cost | One-time cost |
|---|---|---|
| SIP trunk provider | €5–10 | €0 |
| Spanish DID number | €3–5 | €0 |
| Cloud VPS for Asterisk | €4–10 | €0 |
| Softphone | €0 | €0 |
| FacturaScripts plugin development | €0 (self-developed) | 40+ hours |
| Asterisk setup and maintenance | ongoing time cost | 20+ hours initial |
| **Total** | **~€12–25/month** | **60+ hours** |

---

## 16. Recommendation

### Start with Solution D, upgrade to Solution C

#### Phase 1: Immediate (this week)

1. **Sign up at Zadarma** (zadarma.com) — create a free account
2. **Register a Spanish geographic number** from your province (~€3.60/month)
3. **Complete identity verification** (upload DNI/passport and address proof)
4. **Configure voicemail** in Zadarma's PBX panel:
   - Record a professional greeting in Spanish (and optionally English)
   - Set ring time to 20 seconds before voicemail
   - Enable email notification for voicemail (with MP3 attachment)
5. **Install Zadarma softphone** on your computer (or use their WebRTC browser client)
6. **Test the system** — call your new number from a friend's phone
7. **You now have a working phone system** — answer when you can, voicemail catches the rest

#### Phase 2: After 2–4 weeks (once you understand your call patterns)

1. **Configure Zadarma's webhooks** to point to your FacturaScripts server
2. **Develop a small CRM call log extension** for FacturaScripts:
   - A new `ecommerce_call_log` table
   - A controller to receive Zadarma webhooks
   - A list view in the admin panel showing call history
   - Auto-matching of phone numbers to existing customers
3. **Set up time-based routing** — business hours vs. after-hours greetings

#### Phase 3: Optional enhancements (as needed)

- Enable **call recording** for important conversations
- Add **voicemail transcription** (Zadarma offers speech-to-text)
- Create **click-to-call** links in FacturaScripts order views (click a customer's phone number to call them)
- Build **call statistics** dashboard (calls per day, average response time, missed call rate)

### Why this phased approach

1. **Zero risk** — you start with a working system on day one
2. **Low cost** — ~€4/month to begin, no development needed initially
3. **Learn first** — understand your call patterns before building automation
4. **Incremental investment** — only build the CRM integration if you actually need it
5. **No lock-in** — Zadarma's API means you can switch providers later

---

## 17. Glossary

| Term | Definition |
|---|---|
| **ATA** | Analogue Telephone Adapter — device that connects a regular phone to the internet for VoIP |
| **CDR** | Call Detail Record — a log entry for each call (caller, callee, time, duration, status) |
| **CGNAT** | Carrier-Grade NAT — a double-NAT configuration used by Starlink that complicates direct SIP connections |
| **Cloud PBX** | A phone system hosted in the cloud by a provider, configured via a web interface |
| **Codec** | Algorithm that compresses/decompresses audio. Common VoIP codecs: G.711 (uncompressed), G.729 (compressed), Opus (modern, adaptive) |
| **DID** | Direct Inward Dialling — a virtual phone number that routes calls to a VoIP system |
| **DTMF** | Dual-Tone Multi-Frequency — the tones when you press phone keys ("Press 1 for sales") |
| **FreePBX** | Free, open-source web interface for managing the Asterisk PBX server |
| **Full-duplex** | Both parties can speak and hear simultaneously (like a normal phone call) |
| **IVR** | Interactive Voice Response — "Press 1 for sales, 2 for support" automated menus |
| **Jitter** | Variation in packet arrival times — causes choppy audio if too high |
| **Latency** | Time for a data packet to travel from you to the other person (round trip). Below 150 ms is acceptable for voice |
| **NAT** | Network Address Translation — allows multiple devices to share one public internet address |
| **PBX** | Private Branch Exchange — a phone system that manages call routing, voicemail, extensions |
| **RTP** | Real-time Transport Protocol — carries the actual audio data during a VoIP call |
| **SIP** | Session Initiation Protocol — the protocol that sets up, manages, and ends VoIP calls |
| **SIP Trunk** | A direct SIP connection from a provider to your own PBX — a "raw phone line" over the internet |
| **Softphone** | Software application that turns a computer/tablet/phone into a VoIP phone |
| **SRTP** | Secure RTP — encrypted audio stream |
| **STUN** | Session Traversal Utilities for NAT — helps VoIP devices discover their public IP address when behind NAT |
| **TURN** | Traversal Using Relays around NAT — relays VoIP traffic through a server when direct connection fails |
| **VoIP** | Voice over Internet Protocol — phone calls over the internet |
| **WebRTC** | Web Real-Time Communication — built-in browser technology for voice/video calls, avoids SIP NAT issues |
| **Webhook** | An HTTP request sent from one server to another automatically when an event occurs |
