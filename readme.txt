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

Calorie, TDEE and BMI calculators that work without an account, plus a members' calorie tracker, barcode scanner, Nutrition Facts block, meal plan generator and recipe browser that run on a Your Move API key.

**Works out of the box, no account needed:**

* **Calorie Calculator block** - BMR (Mifflin-St Jeor, Harris-Benedict or Katch-McArdle), TDEE, goal calories for losing, maintaining or gaining, and a macro split. Metric and imperial, optional instant results while typing, custom title. Activity multipliers and goals are editable; per block you can hide the unit switch or the goal selector and show BMI alongside the calories.
* **Five styles** - Classic, iOS, Minimal, Gradient or Neo-brutalist, set for the whole site under Settings or per block. Gradient comes in six palettes (Glacier, Ocean, Slate, Aurora, Mint, Sunset). Classic, iOS and Neo-brutalist take your accent colour, with text contrast handled automatically; Classic also has light, dark, soft and bold schemes.
* **Five templates** - card, plain, split (live results beside the form), a multi-step wizard, and a chat that asks one question at a time. The chat sits in the page (growing, or at a fixed height that scrolls inside) or floats as a button in the bottom-right corner that opens a chat window: `[ymove_calculator layout="chat" chatdisplay="floating" chatlabel="How many calories do I need?"]`.
* **Lead capture** - optional or required email capture. Visitors receive a results email; you get a notification, a Leads screen with CSV export, a JSON webhook for Zapier, Make, n8n or your own endpoint, optional Turnstile or reCAPTCHA, and a `ymove_calculator_lead` action.
* **Branded results email** - your logo, subject, intro and call-to-action button, with a GDPR consent checkbox in your own wording.
* **BMI Calculator block** - adult BMI with WHO categories and a healthy weight range.

Both calculators are Gutenberg blocks and shortcodes (`[ymove_calculator]`, `[ymove_bmi]`), style themselves from your theme, and work on any page.

**Connect a Your Move API key and logged-in users also get:**

* **Calorie Tracker block** - a food diary with daily targets, macro bars and a weekly overview. Members add food by searching 1M+ foods (USDA FoodData Central + Open Food Facts), by **scanning a barcode** with their phone camera, from their recent foods, or - on the Pro plan - by **taking a photo of their plate** or **describing the meal in words**.
* **Barcode Lookup block** - scan a product, see its nutrition label.
* **Nutrition Facts block** - search a food in the editor and embed an FDA-style label in posts and recipes, with optional NutritionInformation schema.
* **Meal Plan Generator block** - a 1-7 day plan with recipes, ingredients and method for any calorie target and diet (balanced, high protein, low carb, keto, vegan, vegetarian, mediterranean, paleo). Visitors swap any recipe they don't like, then save the plan as a PDF or get it by email with every recipe in full; you choose email, PDF, both or neither, and emailed plans become leads. Members add meals straight to their diary. You can open it to visitors as a lead magnet, rate-limited per IP.
* **Recipes block** - embed any of thousands of recipes in a post with per-serving nutrition, ingredients, method and Google recipe markup (schema.org Recipe), or drop in a recipe browser visitors can search by keyword, meal, diet and calories. Members add a recipe to their diary in one tap. Powered by the [Your Move Recipe API](https://ymove.app/recipe-api/).

The tracker is built for coaches, gyms and creators who already run memberships on WordPress: put the block on a members-only page and it works with MemberPress, Paid Memberships Pro, WooCommerce Memberships or any plugin that restricts pages. A **Nutrition logs** screen under Users lets coaches see how their members are doing.

= Why a key? =

Food data, barcode coverage for 180+ countries, recipes and AI meal recognition come from the [Your Move Nutrition API](https://ymove.app/nutrition-api/?utm_source=wordpress-org). One key covers your whole site. Basic covers food search and barcodes; Pro adds AI photo and text logging. Current prices are on the Your Move Nutrition API page. The calculators never need a key.

= Built to protect your quota =

Your key is stored encrypted with your site's salts (or set in wp-config.php) and used only server-side. Lookups are cached (barcodes for 30 days, searches for a day), and every member has hourly and daily limits you control, so a curious member cannot run up your bill.

= Lead capture =

Gate the calculator behind an email address and every visitor who wants their number becomes a lead, with the numbers already in your inbox and theirs. Point the results email's button at your booking page, forward each lead through the webhook to your CRM, and follow up knowing their goal and target calories.

= Developer friendly =

* `ymove_user_can_track` filter - tie tracker access to a membership level in one line.
* `ymove_show_source_links` filter, `ymove_calculator_lead` action, `ymove_lead_email` filter (structured results email) and `ymove_email_html` filter (HTML of mail sent through wp_mail).
* `define( 'YMOVE_API_KEY', '...' )` in wp-config.php.
* All routes under `/wp-json/ymove/v1/`, custom tables, WordPress privacy exporter and eraser included.

== External services ==

This plugin connects to the **Your Move Nutrition API** at `https://exercise-api.ymove.app` when a site owner has entered an API key. The calculators never contact it.

What is sent, and when:

* **Food search** - the search text and the site's country setting, when a member or editor searches for a food.
* **Food details** - the id of a food, when a Nutrition Facts block or diary entry needs the full nutrient list and it is not in the local cache.
* **Barcode lookup** - the barcode digits and the country setting, when a member scans or types a barcode.
* **AI photo logging** (Pro) - the meal photo, resized in the browser, when a member chooses to analyse a photo. Members confirm a consent notice the first time. Photos are analysed and not stored by this plugin.
* **AI text logging** (Pro) - the meal description a member typed.
* **Meal plan generation** - the calorie target, diet, meals per day, days and macro focus chosen in the form. No personal data.
* **Meal swap** - the meal type, diet and calorie range of the meal being replaced, when a visitor swaps one recipe in a meal plan.
* **Recipes** - the search text and filters (meal, diet, calories) a visitor picks, or the recipe chosen in the editor. No personal data. Recipe images load from the image URL the API returns (currently ymove-recipes.b-cdn.net).
* **Usage check** - no personal data; fetches the plan and quota for the settings screen.
* **Calculator results and meal plan emails** are sent by your own WordPress (wp_mail), not by Your Move; the address is stored as a lead in your database. The optional webhook posts leads only to the URL you enter.
* **Spam protection** (optional, off by default) - if you enable Cloudflare Turnstile or Google reCAPTCHA v3 under Settings, their script loads from challenges.cloudflare.com or www.google.com/recaptcha on pages with an email form, and when a visitor submits the form the plugin sends the captcha token and the visitor's IP address to the provider for verification. Cloudflare: https://www.cloudflare.com/website-terms/ and https://www.cloudflare.com/privacypolicy/. Google: https://policies.google.com/terms and https://policies.google.com/privacy.

Every request to the Your Move Nutrition API carries your API key and a User-Agent with the plugin version, WordPress version, PHP version and a hash of the site URL (not the URL itself) so Your Move can count active installations.

Your Move terms of service: https://ymove.app/terms-of-service
Your Move privacy policy: https://ymove.app/privacy

The calorie and BMI calculators always show a "Calorie formula explained" / "How BMI is calculated" link to a page on ymove.app describing the formulas, so visitors can see how a health estimate was worked out. The data-source lines under the other blocks (naming USDA FoodData Central, Open Food Facts and the Your Move Nutrition API or Your Move Recipe API) and the small "Nutrition API by Your Move" / "Recipe API by Your Move" credit are off by default; you can switch them on under Settings > Your Move Nutrition > Links and credit. No other external requests are made. No tracking scripts are loaded.

== Installation ==

1. Install and activate the plugin.
2. Add the **Calorie Calculator** or **BMI Calculator** block to any page. Done - they work immediately.
3. To enable the tracker: get a key at https://ymove.app/nutrition-api/signup, paste it under **Settings > Your Move Nutrition**, and add the **Calorie Tracker** block to a page your members can reach when logged in.

== Frequently Asked Questions ==

= How do I add a calculator with Elementor, Divi or another page builder? =

Use the shortcode. In Elementor drag in the **Shortcode** widget (free version is fine) and paste `[ymove_calculator]`; in Divi use a Code or Text module; in Beaver Builder, Bricks or WPBakery use the HTML/Shortcode module; in the classic editor paste it into the content. All block options are available as attributes, e.g. `[ymove_calculator layout="split" theme="gradient" formula="harris" leadmode="required" showbmi="1"]`. The full list is under Settings > Your Move Nutrition > Blocks & shortcodes.

= Which templates and colours are there? =

Five templates - card, plain (inherits your theme), split (form left, live results right), steps (a three-step wizard) and chat (one question at a time, in the page or as a floating button); the BMI calculator has card, plain and split. Five styles (Classic, iOS, Minimal, Gradient, Neo-brutalist): set a site-wide default under Settings > Your Move Nutrition, override per block or with theme="...". Classic also has four colour schemes (light, dark, soft, bold) plus a free accent colour. Activity levels, their multipliers, goals and their kcal deltas are editable for the whole site; per block you can hide the unit switch or the goal selector and show BMI alongside the calories.

= Is there spam protection on the email form? =

A honeypot field and a per-IP rate limit are always on and need no third-party script. If you want more, enable Cloudflare Turnstile or Google reCAPTCHA v3 under Settings; the script then loads only on pages with an email form.

= What needs a Your Move key? =

Only the features that run on the Your Move Nutrition API: the tracker, barcode scanner, Nutrition Facts block, meal plan generator and Recipes block. Everything else is free: all calculators and styles, gated or optional lead capture, the branded results email, the leads screen, CSV export and the webhook.

= My host does not send email. What now? =

Install an SMTP plugin such as WP Mail SMTP or FluentSMTP; the plugin sends through wp_mail, so it uses whatever you set up. Settings > Your Move Nutrition has a test button and a log of the last sends.

= Which plan do I need? =

Basic for food search and barcodes. Pro for AI photo and text logging. The plugin shows your current plan under Settings and tells members when a feature needs an upgrade instead of hiding it.

= Can guests use the tracker? =

No. The diary belongs to a WordPress user account. Visitors see a login prompt. You can additionally limit it to specific roles, or use the `ymove_user_can_track` filter to tie it to a membership.

= Does the barcode scanner work on iPhone? =

Yes. Browsers with the built-in BarcodeDetector API use it; all others, including Safari, use the bundled ZXing library. The site must be served over HTTPS for the camera to be available. A manual entry field is always shown.

= Where is the data stored? =

Diaries, targets, food cache and calculator leads are in custom tables in your WordPress database. The calculator also keeps a visitor's last result in their own browser (localStorage) so the meal plan generator can suggest a calorie target; it never leaves their device. This plugin stores nothing on Your Move servers; how requests are handled there is described in the Your Move privacy policy (https://ymove.app/privacy).

= How do I show where the numbers come from? =

The calorie and BMI calculators always carry a short "Calorie formula explained" / "How BMI is calculated" link so visitors can see how their estimate was worked out. The other blocks can show a data-source line (USDA FoodData Central, Open Food Facts, Your Move); it is off by default and you switch it on under Settings > Your Move Nutrition > Links and credit.

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
