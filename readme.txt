=== Your Move Nutrition - Calorie Calculator, Tracker & Barcode Scanner ===
Contributors: ymove
Tags: calorie calculator, calorie tracker, meal plan, barcode scanner, nutrition
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free calorie, TDEE and BMI calculators. Add a Your Move API key for a member calorie tracker, barcode scanner, recipes and meal plans.

== Description ==

**Works out of the box, no account needed:**

* **Calorie Calculator block** - BMR (Mifflin-St Jeor, Harris-Benedict or Katch-McArdle), TDEE, goal calories for losing, maintaining or gaining, and a macro split. Metric and imperial, instant results while typing, custom title.
* **Five styles, five templates** - pick Classic, iOS, Minimal, Gradient or Neo-brutalist for the whole site under Settings, or per block. Templates: card, plain, split (live results beside the form), a multi-step wizard and a chat that asks one question at a time. The chat sits in the page (growing, or at a fixed height that scrolls inside) or floats as a button in the bottom-right corner that opens a chat window: `[ymove_calculator layout="chat" chatdisplay="floating" chatlabel="How many calories do I need?"]`. Gradient comes in six palettes (Glacier, Ocean, Slate, Aurora, Mint, Sunset); Classic, iOS and Neo-brutalist take your accent colour, with text contrast handled automatically; Classic also has light, dark, soft and bold schemes. Editable activity multipliers and goals, hide units or goals, show BMI alongside calories.
* **Meal plans to take home** - visitors swap any single recipe they don't like, then save the whole plan as a PDF or get it by email with every recipe in full (photo, ingredients, method). You choose email, PDF, both or neither; emailed plans become leads.
* **Lead magnet built in** - optional or required email capture. Visitors receive a results email; you get a notification, a Leads screen with CSV export, a JSON webhook for Zapier / Make / n8n / any CRM, optional Turnstile / reCAPTCHA, and a `ymove_calculator_lead` action. GDPR consent checkbox with your own wording. The results email carries your logo, subject, intro and call-to-action button.
* **BMI Calculator block** - adult BMI with WHO categories and a healthy weight range.

Both are Gutenberg blocks and shortcodes (`[ymove_calculator]`, `[ymove_bmi]`), style themselves from your theme, and work on any page.

**Connect a Your Move API key and unlock, for logged-in users:**

* **Calorie Tracker block** - a food diary with daily targets, macro bars and a weekly overview. Members add food by searching 1M+ foods (USDA FoodData Central + Open Food Facts), by **scanning a barcode** with their phone camera, from their recent foods, or - on the Pro plan - by **taking a photo of their plate** or **describing the meal in words**.
* **Barcode Lookup block** - scan a product, see its nutrition label.
* **Nutrition Facts block** - search a food in the editor and embed an FDA-style label in posts and recipes, with optional NutritionInformation schema.
* **Meal Plan Generator block** - a 1-7 day plan with recipes, ingredients and method for any calorie target and diet (balanced, high protein, low carb, keto, vegan, vegetarian, mediterranean, paleo). Members add meals straight to their diary; you can open it to visitors as a lead magnet, rate-limited per IP.
* **Recipes block** - embed any of thousands of recipes in a post with per-serving nutrition, ingredients, method and Google recipe markup (schema.org Recipe), or drop in a recipe browser visitors can search by ingredient, meal, diet and calories. Members add a recipe to their diary in one tap. Powered by the [Your Move Recipe API](https://ymove.app/recipe-api/).

The tracker is built for coaches, gyms and creators who already run memberships on WordPress: put the block on a members-only page and it works with MemberPress, Paid Memberships Pro, WooCommerce Memberships or any plugin that restricts pages. A **Nutrition logs** screen under Users lets coaches see how their members are doing.

= Why a key? =

Food data, barcode coverage for 180+ countries and AI meal recognition come from the [Your Move Nutrition API](https://ymove.app/nutrition-api/?utm_source=wordpress-org). One key covers your whole site. Basic ($19/mo) includes food search and barcodes; Pro ($29/mo) adds AI photo, text and voice logging. The calculators stay free forever.

= Built to protect your quota =

Your key is stored encrypted and used only server-side. Lookups are cached (barcodes for 30 days, searches for a day), and every member has hourly/daily limits you control, so a curious member cannot run up your bill.

= Made for coaches who sell =

Gate the calculator behind an email address and every visitor who wants their number becomes a lead - with the numbers already in your inbox and theirs. Point the results email's button at your booking page, push the lead into Mailchimp, ConvertKit, HubSpot or ActiveCampaign through the webhook, and follow up knowing their goal and target calories.

= Developer friendly =

* `ymove_user_can_track` filter - tie tracker access to a membership level in one line.
* `ymove_show_source_links` filter, `ymove_calculator_lead` action, `ymove_lead_email` filter (structured results email) and `ymove_email_html` filter (HTML of mail sent through wp_mail).
* `define( 'YMOVE_API_KEY', '...' )` in wp-config.php.
* All routes under `/wp-json/ymove/v1/`, custom tables, WordPress privacy exporter and eraser included.

== External services ==

This plugin connects to the **Your Move Nutrition API** at `https://exercise-api.ymove.app` when a site owner has entered an API key. The calculators never contact it.

What is sent, and when:

* **Food search** - the search text and the site's country setting, when a member or editor searches for a food.
* **Barcode lookup** - the barcode digits and the country setting, when a member scans or types a barcode.
* **AI photo logging** (Pro) - the meal photo, resized in the browser, when a member chooses to analyse a photo. Members confirm a consent notice the first time. Photos are analysed and not stored by this plugin.
* **AI text logging** (Pro) - the meal description a member typed.
* **Calculator lead emails** are sent by your own WordPress (wp_mail), not by Your Move. The optional webhook posts leads only to the URL you enter.
* **Spam protection (optional, off by default)** - if you enable Cloudflare Turnstile or Google reCAPTCHA v3 under Settings, their script (challenges.cloudflare.com or www.google.com/recaptcha) loads on pages with an email form and the token is verified server-side with the provider. See Cloudflare's and Google's privacy policies.
* **Meal plan generation** - the calorie target, diet, meals per day, days and macro focus chosen in the form. No personal data.
* **Recipes** - the search text and filters (meal, diet, calories) a visitor picks, or the recipe chosen in the editor. No personal data. Recipe images load from Your Move's image CDN (ymove-recipes.b-cdn.net).
* **Usage check** - no personal data; fetches the plan and quota for the settings screen.

Every request carries your API key and a User-Agent with the plugin version, WordPress version, PHP version and a hash of the site URL (not the URL itself) so Your Move can count active installations.

Your Move terms of service: https://ymove.app/terms-of-service
Your Move privacy policy: https://ymove.app/privacy

The calorie and BMI calculators always show a "Calorie formula explained" / "How BMI is calculated" link to a page on ymove.app describing the formulas, so visitors can see how a health estimate was worked out. The data-source lines under the other blocks ("Data via Your Move Nutrition API", "Recipes and nutrition by Your Move Recipe API") and the "Nutrition API by Your Move" credit are off by default; you can switch them on in Settings > Your Move Nutrition. No other external requests are made. No tracking scripts are loaded.

== Installation ==

1. Install and activate the plugin.
2. Add the **Calorie Calculator** or **BMI Calculator** block to any page. Done - they work immediately.
3. To enable the tracker: get a key at https://ymove.app/nutrition-api/signup, paste it under **Settings > Your Move Nutrition**, and add the **Calorie Tracker** block to a page your members can reach when logged in.

== Frequently Asked Questions ==

= How do I add a calculator with Elementor, Divi or another page builder? =

Use the shortcode. In Elementor drag in the **Shortcode** widget (free version is fine) and paste `[ymove_calculator]`; in Divi use a Code or Text module; in Beaver Builder, Bricks or WPBakery use the HTML/Shortcode module; in the classic editor paste it into the content. All block options are available as attributes, e.g. `[ymove_calculator layout="split" theme="gradient" formula="harris" leadmode="required" showbmi="1"]`. The full list is under Settings > Your Move Nutrition > Blocks & shortcodes.

= Which templates and colours are there? =

Four layouts - card, plain (inherits your theme), split (form left, live results right) and steps (a three-step wizard) - five styles (Classic, iOS, Minimal, Gradient, Neo-brutalist - set a site-wide default under Settings > Your Move Nutrition, override per block or with theme="...") and, for Classic, four colour schemes (light, dark, soft, bold) plus a free accent colour. Activity levels, their multipliers, goals and their kcal deltas are editable for the whole site; per block you can hide the unit switch or the goal selector and show BMI alongside the calories.

= Is there spam protection on the email form? =

A honeypot field and a per-IP rate limit are always on and need no third-party script. If you want more, enable Cloudflare Turnstile or Google reCAPTCHA v3 under Settings; the script then loads only on pages with an email form.

= What needs a Your Move key? =

Only the features that run on the Your Move API: the tracker, barcode scanner, Nutrition Facts block, meal plan generator and Recipes block. Everything else is free and fully unlocked: all calculators and styles, gated or optional lead capture, the branded results email, the leads screen, CSV export and the webhook.

= My host does not send email. What now? =

Install an SMTP plugin such as WP Mail SMTP or FluentSMTP; the plugin sends through wp_mail, so it uses whatever you set up. Settings > Your Move Nutrition has a test button and a log of the last sends.

= Do I need an API key? =

Not for the calculators. The tracker, barcode scanner and Nutrition Facts block need one because they use live food data.

= Which plan do I need? =

Basic for food search and barcodes. Pro for AI photo and text logging. The plugin shows your current plan under Settings and tells members when a feature needs an upgrade instead of hiding it.

= Can guests use the tracker? =

No. The diary belongs to a WordPress user account. Visitors see a login prompt. You can additionally limit it to specific roles, or use the `ymove_user_can_track` filter to tie it to a membership.

= Does the barcode scanner work on iPhone? =

Yes, in Safari 17 and later using the built-in detector, and in older browsers using the bundled ZXing library. The site must be served over HTTPS for the camera to be available. A manual entry field is always shown.

= Where is the data stored? =

Diaries, targets, food cache and calculator leads are in custom tables in your WordPress database. Nothing about your members is stored on Your Move servers beyond the request itself.

= How do I show who the numbers come from? =

By default each block carries a short source line linking to the formula explanation or to the data source. Turn it off under Settings if you prefer.

== Screenshots ==

1. Calorie calculator with goal calories and macro split.
2. Calorie tracker: today's diary with targets.
3. Adding food by barcode scan.
4. AI photo logging (Pro).
5. Nutrition Facts block in the editor.
6. Settings: connect your key, see your plan and limits.

== Changelog ==

= 0.1.0 =
* First release: calorie and BMI calculators (five styles, five templates including an embeddable or floating chat), tracker with search, barcode, recent foods, AI photo and text logging, Nutrition Facts block, meal plan generator, Recipes block, coach screen, privacy tools.

== Third-party libraries ==

* [ZXing](https://github.com/zxing-js/library) (Apache-2.0) - barcode scanning fallback, bundled in assets/vendor.
* DM Sans, Space Grotesk and Archivo Black (SIL Open Font License 1.1) - web fonts for the Gradient and Neo-brutalist styles, bundled in assets/fonts.
