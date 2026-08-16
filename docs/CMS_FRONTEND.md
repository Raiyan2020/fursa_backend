# Fursa CMS — Frontend implementation brief (Cursor)

Paste this file into Cursor on the **Vue frontend** repo and implement it.

> Backend is already live. Admin edits in the dashboard do **not** appear on the site because the frontend currently uses **hardcoded i18n strings**. Replace those with these APIs.

---

## Goal

When an admin changes content in the dashboard, the public website must show the new content **without a frontend deploy**.

Dashboard sections in scope:

| Admin dashboard | What the website must show |
|-----------------|----------------------------|
| Why Fursa (`لماذا فرصة`) | Home “Why FORSA?” cards |
| Pages (`الشروط والأحكام` / about / privacy / terms) | Footer links + those pages |
| Footer & Contact (`الفوتر والتواصل`) | Footer copyright, social links, contact page details |
| Home add-ons (`الإضافات`) | Home hero title + “Share an idea” block |
| Banner images | Home hero slider |
| FAQs | FAQs page |

**Do not** keep fallback hardcoded copy that hides API data. If a field is empty, hide that UI piece — do not show old static English/Arabic text.

---

## Base config

```text
API_BASE = https://portal.fursa.raiyan.cc/api
```

All endpoints below are **public** (no Bearer token).

Send the UI language on every request:

```http
Lang: ar
```

or

```http
Lang: en
```

The JSON still returns **both** `_en` and `_ar` fields. Pick the field that matches the current UI locale:

```ts
const t = (en?: string | null, ar?: string | null, locale: 'en' | 'ar') =>
  (locale === 'ar' ? ar : en) || en || ar || ''
```

---

## Response envelope

Every success response looks like:

```json
{
  "key": "success",
  "msg": "...",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": {}
}
```

Read payload from **`data`**, not from the root.

---

## 1) Home CMS — one call for most of the homepage

```http
GET {API_BASE}/home/
Lang: ar
```

Use these keys from `data`:

### 1.1 Why FORSA?  ← dashboard “Why Fursa”

`data.why_fursa` is an **array** (ordered). Render every item. Do not hardcode the 5 default cards.

```ts
type WhyFursaItem = {
  id: number
  title_en: string
  title_ar: string
  icon: string | null // full image URL
}
```

UI mapping:

- Card title → `t(item.title_en, item.title_ar, locale)`
- Card icon → `item.icon` (`<img :src="item.icon">`)
- If `icon` is null, skip the image (do not use a bundled default icon unless there is no icon)

### 1.2 Footer + contact details  ← dashboard “Footer & Contact”

`data.footer`:

```ts
type Footer = {
  pages: { slug: string; title_en: string; title_ar: string }[]
  contact_email: string | null
  contact: {
    email: string | null
    phone: string | null
    whatsapp: string | null
    address_en: string | null
    address_ar: string | null
    page_text_en: string | null
    page_text_ar: string | null
  }
  social: {
    tiktok: string | null
    twitter: string | null
    youtube: string | null
    instagram: string | null
  }
  copyright_en: string | null
  copyright_ar: string | null
}
```

UI mapping:

| Website UI | API field |
|------------|-----------|
| Footer copyright | `footer.copyright_en` / `footer.copyright_ar` |
| Footer social icons | `footer.social.*` — hide an icon if the URL is null/empty |
| Footer page links | `footer.pages[]` — link to `/pages/{slug}` or your existing routes using `slug` |
| Contact page email | `footer.contact.email` |
| Contact page phone | `footer.contact.phone` |
| Contact page WhatsApp | `footer.contact.whatsapp` |
| Contact page address | `footer.contact.address_en` / `address_ar` |
| Contact page intro text | `footer.contact.page_text_en` / `page_text_ar` |

Footer page `slug` values you will typically get:

- `about` → About us / من نحن
- `privacy` → Privacy policy / سياسة الخصوصية
- `terms` → Terms of use / شروط الاستخدام

**Do not** hardcode those three labels. Use `title_en` / `title_ar` from `footer.pages`.

### 1.3 Hero title + banners  ← dashboard home add-ons + banner images

`data.hero`:

```ts
{
  title_en: string | null
  title_ar: string | null
  banners: { id: number; image: string | null; banner_url: string | null }[]
}
```

- Hero headline → `hero.title_en` / `hero.title_ar`
- Slider images → `hero.banners[].image`
- Optional click-through → `hero.banners[].banner_url`
- **Never** render raw i18n keys like `COMMON.BANNER_IMAGE_ALT`. Use a real alt from the title, or `"banner"`.

### 1.4 Share an idea block  ← dashboard home add-ons (`share_idea`)

`data.share_idea`:

```ts
{
  slug: "share_idea"
  title_en: string | null
  title_ar: string | null
  description_en: string | null
  description_ar: string | null
  image: string | null
}
```

Replace the hardcoded “Share an idea” copy with these fields.

### 1.5 Other home keys (already dynamic — keep using API, not mocks)

You can keep using these from the same `GET /home/` response:

- `statistics` (`volunteer_count`, `volunteer_team_count`, `organization_count`)
- `sponsors` (`id`, `name`, `logo`)
- `opportunities`
- `community`
- `learn_share`
- `events`
- `achievements`

---

## 2) Legal / CMS pages — dashboard “Pages”

List:

```http
GET {API_BASE}/pages/
```

One page (use this on About / Privacy / Terms routes):

```http
GET {API_BASE}/pages/about/
GET {API_BASE}/pages/privacy/
GET {API_BASE}/pages/terms/
```

`data` for a single page:

```ts
type Page = {
  id: number
  slug: string
  title_en: string
  title_ar: string
  content_en: string | null  // HTML is allowed
  content_ar: string | null  // HTML is allowed
  created_at: string | null
  updated_at: string | null
}
```

UI mapping:

- Page `<h1>` → `title_en` / `title_ar`
- Body → `content_en` / `content_ar` rendered as **HTML** (`v-html` with sanitization, or a safe HTML renderer)

If `GET /pages/{slug}/` returns `code: 404`, show a not-found page. Do not fall back to old static terms.

---

## 3) Contact form submit (messages from visitors)

Display data (email/phone/address) comes from `GET /home/` → `footer.contact`.

Submit the form with:

```http
POST {API_BASE}/contact-us/
Content-Type: application/json
Lang: ar

{
  "email": "user@example.com",
  "name_ar": "أحمد",
  "name_en": "Ahmed",
  "message_ar": "نص الرسالة",
  "message_en": "Message text",
  "primary_language": "ar"
}
```

Rules:

- `email` is **required**
- Send `name_ar` + `message_ar` when UI is Arabic
- Send `name_en` + `message_en` when UI is English
- `primary_language` = current UI locale (`ar` or `en`)
- Success = `key === "success"` and HTTP `201`
- Show the API `msg` to the user

`GET /contact-us/` is **not** for the public contact page (it lists submitted messages). Do not use it to render contact details.

---

## 4) FAQs page

```http
GET {API_BASE}/faqs/?limit=50
Lang: ar
```

`data` is an array:

```ts
type Faq = {
  id: number
  question_en: string
  question_ar: string
  answer_en: string
  answer_ar: string
}
```

Render accordion from this list. Do not hardcode FAQ entries.

---

## Vue implementation sketch

```ts
// services/cms.ts
const API_BASE = import.meta.env.VITE_APP_BACKEND_URL + '/api'

async function cmsGet<T>(path: string, locale: 'ar' | 'en'): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { Lang: locale, Accept: 'application/json' },
  })
  const json = await res.json()
  if (json.key !== 'success') throw new Error(json.msg || 'CMS request failed')
  return json.data as T
}

export const fetchHome = (locale: 'ar' | 'en') => cmsGet<any>('/home/', locale)
export const fetchPage = (slug: string, locale: 'ar' | 'en') =>
  cmsGet<any>(`/pages/${slug}/`, locale)
export const fetchFaqs = (locale: 'ar' | 'en') =>
  cmsGet<any>('/faqs/?limit=50', locale)
```

Suggested load strategy:

1. On app boot / layout mount: call `GET /home/` once, store in Pinia/provide.
2. Footer, Why Fursa, hero, share-idea, contact details all read from that store.
3. About / Privacy / Terms pages call `GET /pages/{slug}/` on enter (or reuse `footer.pages` titles + fetch content).
4. Cache `GET /home/` for the session, but refetch on language change (because some numbers are locale-shaped).

---

## Files / screens to change (typical Vue app)

Search the frontend for these strings and replace the source with API fields:

- `Why FORSA?` / `لماذا فرصة`
- `Volunteer recognition` / `Opportunity matching` / `Community engagement`
- `About us` / `Privacy policy` / `Terms of use` (footer)
- `© 2026 Forsa All rights reserved`
- `COMMON.BANNER_IMAGE_ALT` (bug: raw translation key showing on the live site)
- `Share an idea` block body copy
- Contact page phone / email / address

If those live in `locales/en.json` + `locales/ar.json`, stop using them for CMS content. Keep i18n only for chrome (buttons, validation, nav labels that are not admin-editable).

---

## Acceptance checklist

Cursor: implement until every box is done.

- [ ] `GET /api/home/` is called on the public layout (not mocked)
- [ ] Why FORSA cards come from `data.why_fursa` (title + icon URL)
- [ ] Changing a Why Fursa item in admin updates the homepage after refresh
- [ ] Footer copyright comes from `data.footer.copyright_*`
- [ ] Footer social links come from `data.footer.social` (hide empty)
- [ ] Footer page labels/links come from `data.footer.pages`
- [ ] `/about`, `/privacy`, `/terms` (or equivalent) render `GET /api/pages/{slug}/` HTML content
- [ ] Changing a page in admin updates that website page after refresh
- [ ] Contact page details come from `data.footer.contact`
- [ ] Contact form posts to `POST /api/contact-us/`
- [ ] FAQs page uses `GET /api/faqs/`
- [ ] Hero banners use `data.hero.banners` — **no** `COMMON.BANNER_IMAGE_ALT` on screen
- [ ] Share-an-idea block uses `data.share_idea`
- [ ] No hardcoded CMS paragraphs remain as the primary source

---

## Quick curl (verify before coding)

```bash
curl -s -H "Lang: ar" https://portal.fursa.raiyan.cc/api/home/ | jq '{why: .data.why_fursa, footer: .data.footer, hero: .data.hero, share: .data.share_idea}'

curl -s -H "Lang: ar" https://portal.fursa.raiyan.cc/api/pages/terms/

curl -s -H "Lang: ar" https://portal.fursa.raiyan.cc/api/pages/

curl -s -H "Lang: ar" https://portal.fursa.raiyan.cc/api/faqs/?limit=50
```

If these return the new admin content but the website still shows old copy, the frontend is not wired yet — that is this ticket.
