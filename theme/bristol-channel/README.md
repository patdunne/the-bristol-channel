# The Bristol Channel — WordPress block theme (v1.0.1)

A block theme (needs WordPress 6.5 or newer, PHP 7.4+). No plugins required.

## What is in v1

| Template | Where it is used |
| --- | --- |
| `front-page.html` | Home page: lead story (photo on top) and rows of three cards on the left, tall "We the Furious" column on the right running beside the lead and all three cards |
| `single.html` | Article: category label, headline, standfirst, byline, image + caption, 680px text, tags, related stories, social column |
| `page.html` | Static pages (About, Contact): full width, 680px text column, no social column |
| `tag.html` (+ `archive`, `category`, `author`) | Section pages: Bristol, Bath, News, Politics, Business, Opinion |
| `search.html`, `404.html`, `index.html` | Required fallbacks |
| `parts/header`, `parts/footer`, `parts/social-rail` | Shared pieces. The social rail is a template part you edit by hand |

## Front page rules (automatic)

- **Lead story**: the newest post tagged `featured` (if none, the newest post).
- **Cards**: every other recent story (up to 60), newest first, three per row; the grid adds rows as stories arrive.
- No story appears twice. There is no Latest list on the front page.
- The `featured` tag is hidden from readers (it never shows as a label or link).
- The small label above every headline is the story's **category** (e.g. Bristol). The default "Uncategorized" category is hidden, so a story with no real category shows no label.
- The lead and all three cards always show the story's featured image (no tick box needed on the front page).

## Section pages (tags)

Tags drive the menu/section pages and the `featured` hero control; the label above headlines is the category, not a tag.

The menu links go to `/tag/bristol/`, `/tag/bath/`, `/tag/news/`, `/tag/politics/`, `/tag/business/`, `/tag/opinion/`. Create tags with exactly those slugs and tag stories with them. To change the menu, edit `patterns/header.php` and `patterns/footer.php`.

## Captions and photo credits

Type the caption and credit in the image's **Caption** field in the Media Library, for example `Harbourside at dusk. Photo: Name`. It shows under the featured image in bold sans at 15px.

## Social column ("We the Furious")

Edit it in **Appearance > Editor > Patterns > Template parts > Social rail**. Replace the sample cards with Embed blocks (paste an X or Facebook post link) or your own text cards. The column stretches to the bottom of the front page.

## Fonts

Playfair Display, Source Serif 4 and Libre Franklin are bundled in `assets/fonts` (open licence, SIL OFL) and declared in `theme.json`. Nothing is loaded from Google. The logo is outlined SVG in `assets/images`.

## Design tokens

Colours, type sizes and spacing live in `theme.json`; layout and component CSS in `assets/css/theme.css`. Keep functionality (social feeds, custom post types, ads) in a separate plugin so a design change cannot break it.

## Install (version 1)

WordPress admin > Appearance > Themes > Add New > Upload Theme > choose `bristol-channel-theme-v1.0.1.zip` > Activate. Then Settings > Permalinks > choose "Post name" and save.
