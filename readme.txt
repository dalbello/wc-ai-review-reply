=== WC AI Review Reply ===
Contributors: tinyship
Tags: woocommerce, reviews, ai, openai, customer-service
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI-powered draft replies for WooCommerce product reviews right from wp-admin.

== Description ==

WC AI Review Reply adds a one-click "Generate Reply" action to WooCommerce product reviews in the admin comments screen.

Features:
* One-click AI reply drafts for review moderation workflow
* Works for both positive and negative reviews
* Tone options: professional, friendly, apologetic
* Bring your own OpenAI API key (no SaaS lock-in)
* No customer data sent anywhere except your OpenAI request

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wc-ai-review-reply`, or install the zip from Plugins > Add New.
2. Activate the plugin.
3. Go to WooCommerce > AI Review Reply and add your OpenAI API key.
4. Open Comments and filter by `Comment type: Reviews`.
5. Click "Generate Reply" next to any review.

== Frequently Asked Questions ==

= Does this post replies automatically? =
No. It generates drafts only. You decide what to post.

= Which OpenAI model should I use? =
Default is `gpt-4o-mini` for speed/cost. You can change it in settings.

= Is this free? =
Yes. The plugin is free. You only pay OpenAI directly if your API usage exceeds free credits.

== Changelog ==

= 1.0.0 =
* Initial release
