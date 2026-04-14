# wAnswers - SEO-First Q&A

**SEO-first, GEO-optimised community Q&A for WordPress.** Every question gets its own URL, full QAPage JSON-LD schema, Open Graph tags, BreadcrumbList, and Speakable markup — out of the box, zero configuration required.

[![Version](https://img.shields.io/badge/version-3.0.0-orange)](https://wordpress.org/plugins/wanswers-seo-first-qa/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)

![wAnswers banner](https://ps.w.org/wanswers-seo-first-qa/assets/banner-772x250.png)

🔗 **Live demo:** [wbuild.dev/questions/](https://wbuild.dev/questions/)
📦 **WordPress.org:** [wanswers-seo-first-qa](https://wordpress.org/plugins/wanswers-seo-first-qa/)
🌐 **Plugin site:** [wbuild.dev/wanswers/](https://wbuild.dev/wanswers/)

---

## What It Does

wAnswers turns any WordPress site into a structured community Q&A platform. Think self-hosted Stack Overflow or Quora — but built for SEO and AI search from the ground up, not bolted on afterward.

Every question is a WordPress post with its own canonical URL. Every page ships with the structured data Google and AI engines need to surface your content in rich results, People Also Ask boxes, and AI Overviews.

---

## Screenshots

### Q&A Feed
Questions list with topic filters, search, and sort tabs.

![Q&A feed](https://ps.w.org/wanswers-seo-first-qa/assets/screenshot-1.png)

### Single Question Page
Answers, voting, accepted answer highlight, and threaded replies.

![Single question](https://ps.w.org/wanswers-seo-first-qa/assets/screenshot-2.png)

### User Profile
Badges, lifetime stats, 12-month activity chart, and recent questions.

![User profile](https://ps.w.org/wanswers-seo-first-qa/assets/screenshot-3.png)

### Leaderboard Sidebar
Top contributors shown beside the question feed.

![Leaderboard sidebar](https://ps.w.org/wanswers-seo-first-qa/assets/screenshot-4.png)

### Admin Settings
Complete settings page: homepage mode, archive content, leaderboard, SEO/schema, email notifications, rate limiting, weekly digest, and tools.

![Admin settings](https://ps.w.org/wanswers-seo-first-qa/assets/screenshot-5.png)

---

## Why It's Different: SEO & GEO First

Most Q&A plugins render everything via shortcode on a single page — one URL, no per-question structured data, invisible to crawlers. wAnswers works the opposite way.

| | Traditional Q&A Plugins | wAnswers |
|---|---|---|
| URLs | One page for all questions | Every question gets `/questions/my-question/` |
| Schema | None or basic | Full QAPage JSON-LD per question |
| Crawlability | JS-dependent feeds | Server-rendered HTML |
| AI citation signals | None | Speakable, Person, Organization, dateModified |
| Duplicate content | Unmanaged | Canonical tags + noindex controls |

### GEO (Generative Engine Optimisation)

AI search engines like Perplexity, ChatGPT Browse, and Google AI Overviews favour:

- Clear Q&A structure (`Question`/`Answer` schema)
- Explicit authorship (`Person` entities)
- Date signals (`dateCreated`, `dateModified`)
- Topic tagging (`about` → `Thing` entities)
- Speakable content markers

wAnswers implements all of these out of the box.

---

## Features

### Core Q&A
- Ask questions with title and optional body text
- Categorise by topic (custom taxonomy with AJAX filter)
- Answer questions inline, no page reload
- Accept an answer (question author or admin)
- Threaded replies on answers, one level deep
- Sort by Newest, Top Voted, Most Answered, Unanswered
- Live search within the feed
- Load-more pagination

### Voting & Scoring
- Upvote and downvote questions and answers
- Lifetime vote tracking persists across leaderboard resets
- Historical vote backfill for pre-existing users

### Leaderboard
- 4 tabs: Top Score, Most Questions, Most Answers, Most Accepted
- 5 position options: none, above, below, sidebar left, sidebar right
- Optional sticky sidebar, configurable max users (3 to 50)
- Transient-cached, busted on every interaction

### User Profiles
- Unique URLs: `/questions/author/username/`
- Gravatar with letter-initial fallback
- Lifetime score, coloured up/downvote counts
- 10 badges across 4 tiers (Bronze, Silver, Gold, Diamond)
- SVG 12-month activity chart
- Recent questions and answers list
- Topic badge links navigate to filtered archive

### Email & Digest
- Notifications on new answer and new reply
- Weekly digest via WP-Cron (configurable day)
- Token-based one-click unsubscribe
- Admin "Send Digest Now" for testing

### Settings
- **Homepage Mode:** serve Q&A at `/` with automatic 301 from `/questions/`
- Custom heading, subtitle, SEO title, and meta description
- Rate limiting per user (questions, answers, votes)
- Question moderation mode
- Noindex shortcode pages to prevent duplicate content
- Footer credit toggle (off by default, freely removable)

---

## SEO & GEO Schema Reference

| Schema Signal | Applied On |
|---|---|
| `QAPage` JSON-LD | Every single question page |
| `Question` + `Answer` + `acceptedAnswer` | Per-question with author, dates, vote count |
| `BreadcrumbList` | Single, archive, and taxonomy pages |
| `CollectionPage` | Archive and topic taxonomy pages |
| `Organization` | Sitewide brand entity for AI citation |
| `WebSite` + `SearchAction` | Sitewide Sitelinks Searchbox eligibility |
| `Speakable` | Single question pages, AI and voice signal |
| Open Graph + Twitter Card | Every single question page |
| Canonical `<link>` | Every page type, no ambiguity |
| `dateModified` | Updated on every edit, answer, and reply |
| Microdata (`itemscope`/`itemprop`) | Question cards in the feed |

---

## Installation

**From WordPress Admin:**
1. Plugins → Add New → Search "wAnswers"
2. Install and Activate
3. Visit **Questions → Settings**
4. Go to **Settings → Permalinks** and click Save Changes (once, to flush rewrite rules)

**Manual:**
1. Download from [wordpress.org/plugins/wanswers-seo-first-qa](https://wordpress.org/plugins/wanswers-seo-first-qa/)
2. Unzip into `/wp-content/plugins/`
3. Activate from Plugins screen

---

## Shortcodes

```
[wanswers_qa]                         Embed the Q&A feed on any page
[wanswers_leaderboard]                Embed a standalone leaderboard
[wanswers_leaderboard limit="5"]      Show top 5 per tab (default 10)
```

---

## File Structure

```
wanswers/
├── wanswers.php                  Plugin entry, constants, hooks
├── README.md                     GitHub readme
├── readme.txt                    WordPress.org readme
├── uninstall.php                 Clean uninstall (drops tables, deletes options)
├── assets/
│   ├── css/wanswers.css          All styles, no external dependencies
│   ├── css/admin.css             Settings page styles
│   └── js/wanswers.js            Vanilla JS, no jQuery
├── includes/
│   ├── class-admin.php           Settings page, sanitizers
│   ├── class-ajax.php            AJAX handlers, rate limiting
│   ├── class-badges.php          Badge system, activity chart
│   ├── class-database.php        Custom DB tables, vote recording, migration
│   ├── class-digest.php          Weekly digest, WP-Cron
│   ├── class-email.php           Notifications, token unsubscribe
│   ├── class-leaderboard.php     Leaderboard stats, caching
│   ├── class-post-types.php      CPT, taxonomy, rewrite rules, routing
│   ├── class-schema.php          All JSON-LD, OG, Twitter Card, Speakable
│   └── class-shortcode.php       [wanswers_qa] shortcode, card rendering
└── templates/
    ├── archive-wanswers_question.php   /questions/
    ├── author-wanswers_question.php    /questions/author/{username}/
    └── single-wanswers_question.php    /questions/{slug}/
```

---

## External Services

wAnswers uses the **Gravatar** service to display user profile images on member profile pages. An MD5 hash of the user's email address is included in image URLs requested from Gravatar.

- Service provider: Automattic Inc.
- Terms of Service: https://automattic.com/tos/
- Privacy Policy: https://automattic.com/privacy/

---

## Changelog

### 3.0.0
- Full prefix rename: all internal names now use `wanswers_` / `WANSWERS_`
- Text domain set to `wanswers-seo-first-qa` to match WordPress.org slug
- Automatic data migration from pre-3.0 installs (tables, post types, meta keys, options)
- Removed custom CSS field per WordPress.org guidelines
- Gravatar external service documented in readme
- All output now uses `wp_kses_post()` and core escaping functions
- `wp_json_encode()` calls simplified
- Missing `handle_delete_reply` AJAX handler added
- Admin footer branding block removed

### 2.9.x
- Added "Disable built-in schema" toggle for compatibility with RankMath, Yoast
- Settings link on Plugins page
- Self-contained CSS prevents theme style bleed (Divi, Extra, Disputo tested)
- `wp_safe_redirect` and `gmdate` for security and timezone safety

### 2.8.x
- Settings page visual refresh, branded section headings
- Topic badges in user profiles now link to filtered archive
- Footer credit toggle, freely removable

### 2.7.x
- **Homepage Mode:** serve Q&A at `/` with automatic 301 redirect
- Explicit canonical tag on archive template

### 2.5.0 – 2.6.x
- User profiles at `/questions/author/{username}/`
- 10 badges, 4 tiers, SVG activity chart, Gravatar
- Leaderboard position, sticky sidebar, max users setting
- Load-more answer pagination

### 2.0.0 – 2.4.x
- Weekly digest, threaded replies, leaderboard, rate limiting, moderation

### 1.0.0
- Initial release

---

## Contributing

Issues and pull requests welcome at [github.com/wbuilddev/wanswers](https://github.com/wbuilddev/wanswers).

---

## Credits

Built by [wBuild.dev](https://wbuild.dev) · [Live demo](https://wbuild.dev/questions/)
Licensed under [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Support wBuild

wBuild is independently developed with no VC funding, no ads, and no data collection. If this plugin saves you time or helps you earn, consider supporting future development:

[PayPal](https://paypal.me/wbuild) · [Cash App](https://cash.app/$wbuild) · BTC: `16cj4pbkWrTmoaUUkM1XWkxGTsvnywwS8C`

Every contribution helps keep wBuild tools updated and independent.
