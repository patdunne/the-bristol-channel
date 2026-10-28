# Bristol Channel Social

Queues a post for each new story to Buffer (Facebook, LinkedIn, X).

- Settings > Social posting: Buffer API key (create at publish.buffer.com > Settings > API), templates, channels, "promote new stories" switch.
- Each story has a "Social posts (Buffer)" box: tick/untick the story and each channel, and override the text per channel. Blank = template.
- Sent about 90 seconds after publish/update via WP-Cron, once per channel (`_bcsoc_sent`). WTF posts are never sent. Stories promoted by default only go out within 3 days of publishing.
- Facebook/LinkedIn use a link attachment (card from the SEO plugin's Open Graph tags); X puts the link in the text and is trimmed to 280 characters (links count 23).
- The API key is stored in the `bcsoc_api_key` option, never in the repo.
