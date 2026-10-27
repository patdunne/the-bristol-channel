# Bristol Channel SEO

Small, dependency-free SEO plugin for The Bristol Channel (no dashboard, no upsells).

- **Meta description** from the story's excerpt (falls back to the opening text). Optional overrides in the "Search & sharing" box on each post.
- **Open Graph and X (Twitter) cards**: headline, description, featured image, author, section, tags.
- **NewsArticle JSON-LD** (and WebSite for the home page), with publisher and logo (the site icon).
- **Google News sitemap** at `/news-sitemap.xml` (stories from the last 2 days), listed in `robots.txt`. Submit it in Google Search Console and Publisher Center.
- **WTF posts** (category `wtf`) are left out of the sitemaps and marked `noindex`. Search results and 404s are `noindex` too.

Install: copy this folder to `wp-content/plugins/` and activate. Activating adds the news sitemap rewrite rule.
