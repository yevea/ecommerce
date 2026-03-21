# Añadir Tablón — Mobile PWA Manual

A step-by-step guide for mobile users to install and use the **Añadir Tablón** (Add Slab) Progressive Web App. This app lets field operators photograph wood slabs, enter their dimensions, and publish them to the product catalogue — even without an internet connection.

---

## Table of Contents

1. [What Is the Add Slab App?](#1-what-is-the-add-slab-app)
2. [Requirements](#2-requirements)
3. [Installing the App](#3-installing-the-app)
   - [Android — Chrome](#android--chrome)
   - [Android — Samsung Internet](#android--samsung-internet)
   - [Android — Firefox](#android--firefox)
   - [iPhone / iPad — Safari](#iphone--ipad--safari)
4. [Opening the App for the First Time](#4-opening-the-app-for-the-first-time)
5. [Logging In](#5-logging-in)
6. [Adding a Slab](#6-adding-a-slab)
   - [Step 1 — Take a Photo](#step-1--take-a-photo)
   - [Step 2 — Choose Type](#step-2--choose-type)
   - [Step 3 — Enter Dimensions](#step-3--enter-dimensions)
   - [Step 4 — Review Price](#step-4--review-price)
   - [Step 5 — Publish](#step-5--publish)
7. [Offline Mode](#7-offline-mode)
8. [Syncing Pending Slabs](#8-syncing-pending-slabs)
9. [Logging Out](#9-logging-out)
10. [Troubleshooting](#10-troubleshooting)
11. [Quick-Reference Summary](#11-quick-reference-summary)

---

## 1. What Is the Add Slab App?

The **Añadir Tablón** app is a lightweight web application that installs on your phone's home screen and behaves like a native app. It is designed for sawmill operators who need to quickly:

- Photograph a wood slab with the phone camera.
- Select the wood type and slab type.
- Enter length, width, and thickness (in cm).
- See the calculated price in real time.
- Publish the slab to the online catalogue with one tap.

The app works **offline**: if you have no signal, slabs are saved locally on your device and automatically uploaded when the connection returns.

---

## 2. Requirements

| Requirement | Details |
|---|---|
| **Phone** | Any modern smartphone (Android 5+ or iOS 12+) |
| **Browser** | Chrome, Samsung Internet, Firefox, or Safari |
| **Internet** | Needed for first install and sync; slabs can be added offline |
| **Camera** | Required for taking slab photos |
| **Account** | A user account with **AddTablon** permission (ask your administrator) |

---

## 3. Installing the App

### Access the App Page

Before installing, open your phone's browser and navigate to the Add Slab page. There are two ways to reach it:

- **Direct URL:** Open `https://your-site.com/AddTablon` in your browser.
- **From the catalogue:** Browse to the **Wood Planks** (Tablones) category on the store. On mobile, you will see an **"Add Slabs App"** button at the bottom of the page. Tap it to open the Add Slab page.

Once the page is loaded, follow the steps below for your device.

---

### Android — Chrome

1. Open **Chrome** and go to `https://your-site.com/AddTablon`.
2. Tap the **⋮** menu (three vertical dots) in the top-right corner.
3. Tap **"Install app"** or **"Add to Home screen"**.
4. A dialog appears showing the app name **"Añadir Tablón"** (or **"Tablón"**). Tap **Install** / **Add**.
5. The app icon (a white **+** on a dark blue background) appears on your home screen.
6. Tap the icon to launch the app in fullscreen standalone mode (no browser address bar).

> **Tip:** Chrome may also show an **"Install"** banner at the bottom of the page automatically. Tap it for a shortcut.

---

### Android — Samsung Internet

1. Open **Samsung Internet** and go to `https://your-site.com/AddTablon`.
2. Tap the **☰** menu (three horizontal lines) at the bottom-right.
3. Tap **"Add page to" → "Home screen"**.
4. Confirm the name and tap **Add**.
5. The app icon appears on your home screen.

---

### Android — Firefox

1. Open **Firefox** and go to `https://your-site.com/AddTablon`.
2. Tap the **⋮** menu (three vertical dots).
3. Tap **"Install"** or **"Add to Home screen"**.
4. Confirm by tapping **Add**.
5. The app icon appears on your home screen.

---

### iPhone / iPad — Safari

> **Important:** On iOS, you **must** use **Safari** to install a PWA. Chrome and Firefox on iOS do not support the "Add to Home Screen" feature for PWAs.

1. Open **Safari** and go to `https://your-site.com/AddTablon`.
2. Tap the **Share** button (the square with an upward arrow, ⬆︎) at the bottom of the screen.
3. Scroll down in the share sheet and tap **"Add to Home Screen"**.
4. The app name **"Añadir Tablón"** is pre-filled. Tap **Add** in the top-right corner.
5. The app icon appears on your home screen.
6. Tap the icon to launch the app in fullscreen standalone mode.

> **Note:** If you don't see "Add to Home Screen", scroll further down in the share sheet — it may be hidden below other options. You can also tap **"Edit Actions…"** to move it higher in the list.

---

## 4. Opening the App for the First Time

When you tap the app icon on your home screen:

1. The app opens in **standalone mode** — it looks like a native app, without the browser address bar.
2. You see the **Add Slab** form with:
   - A dark blue header bar showing **"Add Slab"** (or **"Añadir Tablón"** in Spanish).
   - A camera card for taking a photo.
   - Type selectors for wood type and slab type.
   - Dimension fields for length, width, and thickness.
   - A price display showing the calculated price.
   - A green **"Publish Slab"** button at the bottom.

You can browse the form before logging in. The login prompt only appears when you try to publish.

---

## 5. Logging In

The app uses **deferred login** — you can fill out the entire form without logging in. The login dialog only appears when you tap **"Publish Slab"** for the first time.

1. Fill in the slab details (photo, type, dimensions).
2. Tap the green **"Publish Slab"** button.
3. A **login dialog** appears with a lock icon.
4. Enter your **User** (username) and **Password**.
5. Tap **"Log In"**.
6. On success, the login dialog closes and the slab is automatically submitted.

After logging in, your session is remembered — you won't need to log in again until you explicitly log out or the session expires.

> **Login problems?** See the [Troubleshooting](#10-troubleshooting) section below.

---

## 6. Adding a Slab

### Step 1 — Take a Photo

1. Tap the **camera area** (the dashed box with the camera icon).
2. Your phone's camera opens automatically (using the rear camera).
3. Take a photo of the slab.
4. The photo preview appears inside the camera area.

> **Tip:** Make sure there is good lighting and the full slab is visible. You can tap the camera area again to retake the photo.

---

### Step 2 — Choose Type

1. Under **"Type"**, tap the **"Wood Type"** dropdown and select the wood species (e.g., Olivo, Roble).
2. Tap the **"Slab Type"** dropdown and select the slab type (e.g., Tablero, Tablón).

Both fields are required. The available options are configured by your administrator.

---

### Step 3 — Enter Dimensions

Under **"Dimensions"**, enter the three measurements in centimetres:

| Field | Description | Example |
|---|---|---|
| **Length** (Largo) | Length of the slab in cm | `120` |
| **Width** (Ancho) | Width of the slab in cm | `45` |
| **Thickness** (Espesor) | Thickness of the slab in cm | `5` |

All three fields accept decimal values (e.g., `120.5`). All three must be greater than zero.

---

### Step 4 — Review Price

As soon as all fields are filled in, the app **automatically calculates and displays the price**:

- The **green price box** shows the total price in euros (e.g., **27.00 €**).
- Below the price, a detail line shows the breakdown: **50.00 €/m² × 0.5400 m²**.

The price is computed as:

```
area (m²) = (length / 100) × (width / 100)
total price = price per m² × area
```

The price per m² is looked up from a price table configured by the administrator based on wood type, slab type, and thickness.

> **"No price found"** — If you see this message, the combination of wood type, slab type, and thickness has no configured price. Contact your administrator to add the pricing entry.

---

### Step 5 — Publish

1. Once the price is displayed, the green **"Publish Slab"** button becomes active.
2. Tap **"Publish Slab"**.
3. If you are not yet logged in, the [login dialog](#5-logging-in) appears. After login, the slab is submitted automatically.
4. On success, a **green success banner** appears: *"Slab added and published successfully!"*
5. The form resets automatically so you can add the next slab immediately.

---

## 7. Offline Mode

The app is designed to work in areas with poor or no internet connection (e.g., in a sawmill yard or warehouse).

### How It Works

- When your phone has **no internet**, an **orange banner** appears at the top: *"Offline — slabs will be saved locally"*.
- You can continue adding slabs normally. When you tap **"Publish Slab"**, the slab data (including the photo) is saved **locally on your device** in an offline queue.
- A green message confirms: *"Slab saved locally. It will be published automatically when back online."*
- A **red badge** with a number appears in the header showing how many slabs are pending.

### Automatic Sync

When your phone **reconnects to the internet**:

1. The orange offline banner disappears.
2. The app **automatically starts syncing** all pending slabs in the background.
3. A message shows progress: *"Syncing N pending slab(s)…"*
4. When complete: *"N slab(s) synced successfully."*

You do not need to do anything — sync happens automatically.

---

## 8. Syncing Pending Slabs

### Pending Badge

When there are slabs waiting to be uploaded, the header shows:

- A **red circle badge** with the number of pending slabs.
- A **"Sync"** button next to the badge.

### Manual Sync

If you want to force a sync immediately (e.g., you just connected to Wi-Fi):

1. Tap the **"Sync"** button in the header.
2. The app attempts to upload all pending slabs.
3. On success, the badge count decreases and eventually disappears.
4. If some slabs fail (e.g., session expired), you will see: *"X synced, Y failed. Will retry."*

> **Tip:** If sync fails repeatedly, try logging out and back in to refresh your session, then tap Sync again.

---

## 9. Logging Out

1. Tap the **logout icon** (⎋) in the top-right corner of the header.
2. Your session ends and you return to the form.
3. You will need to log in again the next time you publish a slab.

> **Note:** The logout button is only visible when you are logged in.

---

## 10. Troubleshooting

### "Install app" option doesn't appear (Android)

- Make sure you are using **Chrome**, **Samsung Internet**, or **Firefox**.
- Revisit the page `https://your-site.com/AddTablon` — the install prompt may appear after the page finishes loading.
- You may have already installed the app. Check your home screen.

### "Add to Home Screen" not available (iPhone)

- You **must** use **Safari**. Chrome and Firefox on iOS do not support PWA installation.
- Tap the **Share** button (⬆︎) at the bottom, then scroll down to find **"Add to Home Screen"**.

### Login fails with "Incorrect user or password"

- Double-check your username and password.
- Your account must have **AddTablon** permission. Ask your administrator to verify.
- Check that caps lock is not accidentally enabled.

### "No price found for this combination"

- The selected combination of wood type, slab type, and thickness has no configured price.
- Contact your administrator to add the appropriate pricing entry in the **Slab Prices** admin section.

### Slabs not syncing after reconnecting

- Make sure you have a stable internet connection (try opening a website in the browser).
- Tap the **"Sync"** button manually to force a retry.
- If the error persists, log out and log back in to refresh your session.

### App shows old data or layout

- The app caches its interface for offline use. To force an update:
  - **Android:** Open the app, pull down to refresh, or clear the app data in Settings → Apps → Tablón → Clear Cache.
  - **iPhone:** Delete the app from the home screen (long-press → Remove App), then reinstall it from Safari.

### Camera doesn't open when tapping the photo area

- Make sure you have granted **camera permissions** to the browser or the app.
  - **Android:** Settings → Apps → Chrome → Permissions → Camera → Allow.
  - **iPhone:** Settings → Safari → Camera → Allow.

### The "Publish Slab" button stays greyed out

- All fields must be filled in: wood type, slab type, and all three dimensions (length, width, thickness).
- A valid price must be calculated. Check that the price box does not show "No price found".

---

## 11. Quick-Reference Summary

| Action | How |
|---|---|
| **Install (Android)** | Chrome ⋮ → Install app |
| **Install (iPhone)** | Safari Share ⬆︎ → Add to Home Screen |
| **Take photo** | Tap the camera area |
| **Enter dimensions** | Fill in Length, Width, Thickness (cm) |
| **Check price** | Calculated automatically in the green box |
| **Publish** | Tap the green "Publish Slab" button |
| **Offline use** | Add slabs as usual — they queue locally |
| **Sync pending** | Happens automatically, or tap "Sync" |
| **Log in** | Prompted on first publish |
| **Log out** | Tap ⎋ icon in header |
