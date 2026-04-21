# Pielisen Pyörähuolto – Project Brief

## Overview

This is a hand-coded static website for a fictional small bicycle repair shop called **Pielisen Pyörähuolto**, based in **Joensuu, Finland**. The project is a school assignment simulating a real client project. The site is written in plain HTML, CSS, and vanilla JavaScript — no frameworks, no build tools, no CMS.

The site is in **Finnish**.

---

## Business Context

- **Company:** Pielisen Pyörähuolto (fictional)
- **Location:** Kauppakatu 14, 80100 Joensuu
- **Phone:** 013 456 7890 (made-up number)
- **Email:** huolto@pielisenpyora.fi
- **Type:** Small local bicycle repair and maintenance shop
- **Customers:** Commuters, casual cyclists, e-bike users
- **Demand:** Seasonal — peaks in spring and summer (April–May)
- **Budget:** ~1000 € (fictional)

---

## Goals

- Help customers find the business online
- Make it easy to contact the shop (phone call or walk-in — no online booking system)
- Build trust with a clean, professional appearance
- Allow the owner to post news/updates without touching code
- Work well on mobile

---

## Design

| Property | Value |
|---|---|
| Primary color | Slate blue `#1E3A5F` |
| Accent color | Yellow `#F5C518` |
| Background | White / light gray `#f7f8fa` |
| Font | `system-ui, -apple-system, sans-serif` |
| Style | Clean, flat, minimal — no gradients, no shadows |
| Mobile | Fully responsive, hamburger menu on mobile |

---

## File Structure

```
/
├── index.html       # Main page (single scroll page)
├── faq.html         # FAQ page (separate page)
└── admin.html       # Admin panel (password protected)
```

No external dependencies, frameworks, or build tools. Everything is self-contained in each HTML file (HTML + CSS + JS in one file per page).

---

## Pages

### index.html — Main Page

Single scrolling page with the following sections in order:

1. **Nav** — sticky, logo left, links center, phone CTA button right, hamburger on mobile
2. **Hero** — background photo (Unsplash bike workshop image with dark overlay), tagline, two CTAs: "Soita ja varaa aika" (primary) and "Katso hinnasto" (secondary). Small note: "Tai pistäydy suoraan paikalle"
3. **Ajankohtaista** — news/updates section, immediately below hero. Pulls posts from localStorage (set by admin panel). Falls back to two hardcoded default posts if localStorage is empty.
4. **Palvelut** — services section, two-column layout: workshop photo left, service list right. 6 services listed.
5. **Hinnasto** — pricing in 4 cards: Perushuolto (39€), Täyshuolto (89€), Sähköpyörä (55€+), Rengaskorjaus (15€+). Note about hourly rate (45€/h) and free estimates.
6. **Yhteystiedot** — dark blue strip with opening hours, address + Google Maps link, contact info
7. **CTA strip** — yellow background, bold phone number, call to action
8. **Footer** — links to all pages including admin panel, copyright 2026

### faq.html — FAQ Page

- Same nav and footer as main page
- Search bar at the top — searches both question text and answer text, highlights matches in yellow, auto-expands matching items, hides empty category sections, shows result count
- 15 questions across 4 categories: Ajanvaraus ja asiointi, Huoltoajat ja hinnoittelu, Sähköpyörät, Muuta
- Accordion-style open/close per question
- Exists primarily for SEO value

### admin.html — Admin Panel

- Password login screen (password: `pyora2026`)
- After login: dashboard with post count stats (total / visible / hidden)
- Form to create a new post: title, body text, date
- Posts list showing all posts sorted by date, with publish/hide toggle and delete button
- Posts are stored in `localStorage` under key `pp_news_posts`
- Deleting requires a confirmation dialog
- Changes are immediately reflected on the main page (index.html reads same localStorage key)
- Link to open the live site in a new tab
- Logout button

---

## News / Posts System

Posts are stored as a JSON array in `localStorage` with key `pp_news_posts`. Each post object:

```json
{
  "id": 1700000000000,
  "title": "Otsikko",
  "body": "Julkaisun teksti tähän.",
  "date": "2026-04-01",
  "published": true
}
```

- `id` is a timestamp (`Date.now()`)
- `published: false` hides the post from the main page but keeps it in the admin panel
- Main page filters to only `published: true` posts, sorted newest first
- If localStorage is empty or has no posts, two default posts are shown as fallback

---

## Content

### Services (Palvelut)
Perushuolto, Täyshuolto, Sähköpyörät, Rengaskorjaus, Varaosien vaihto, Keväthuolto

### Pricing (Hinnasto)
- Perushuolto: 39 € (fixed)
- Täyshuolto: 89 € (fixed)
- Sähköpyörä: 55 € (from)
- Rengaskorjaus: 15 € (from)
- Labour rate: 45 €/h
- Parts billed separately

### Opening Hours (Aukioloajat)
- Mon–Fri: 9:00–17:00
- Sat: 10:00–14:00 (10:00–16:00 in April–May)
- Sun: Closed

---

## Key Decisions & Rationale

- **No booking system** — owner preference. Call or walk in only. One clear phone CTA.
- **No WordPress or CMS** — hand-coded per assignment requirements. Admin panel replaces CMS for news.
- **localStorage for posts** — simple, no backend needed for this scope. Works for a single-device admin use case.
- **Single page layout** — keeps navigation simple, works well on mobile, all key info reachable by scrolling.
- **FAQ as separate page** — better for SEO, keeps main page clean.
- **Photos** — one hero background photo and one workshop photo in services section. Both from Unsplash. Should be replaced with real photos before final delivery.
- **Hamburger menu** — visible only on mobile (max-width: 600px), closes automatically when a link is tapped.

---

## To Do / Before Final Delivery

- Separate css to styles.css
- Replace Unsplash placeholder photos with real photos of the shop (user will add them to images folder - create the folder)
- Consider moving localStorage to a real backend or service (e.g. Supabase, Firebase) if the site goes live on multiple devices
- Deploy to a real host 
- Test on real mobile devices
- Add basic meta tags for SEO (description, og:image, etc.)
