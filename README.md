# Your Move Nutrition - WordPress plugin

Free calorie, TDEE and BMI calculator blocks for WordPress. Connect a
[Your Move](https://ymove.app/nutrition-api/) API key and your logged-in
members also get a calorie tracker with food search, barcode scanning and AI
photo logging, a meal plan generator and a Recipes block.

- Plugin page: https://ymove.app/nutrition-api/wordpress-plugin/
- How the calculators work: https://ymove.app/nutrition-api/calorie-calculator-methodology/
- User docs, FAQ and the list of external services: [`readme.txt`](readme.txt) (wordpress.org format)

## Features

| Needs a key? | Block / shortcode | What it does |
|---|---|---|
| No | Calorie Calculator `[ymove_calculator]` | Mifflin-St Jeor, Harris-Benedict or Katch-McArdle; goals and macro splits. Five styles; card, plain, split, steps or chat templates (the chat can sit in the page or float bottom-right). Optional email-the-results lead capture. |
| No | BMI Calculator `[ymove_bmi]` | Adult BMI with WHO categories and a healthy weight range. |
| Yes | Calorie Tracker `[ymove_tracker]` | Members' food diary: search, barcode scanner, recent foods, daily and weekly totals against their target. AI photo / text logging on the Pro plan. |
| Yes | Barcode Lookup `[ymove_barcode]` | Scan a product, get its nutrition label. |
| Yes | Nutrition Facts `[ymove_nutrition]` | FDA-style label for a food, with optional schema.org markup. |
| Yes | Meal Plan Generator `[ymove_meal_plan]` | 1-7 day plans with recipes for any calorie target and diet; swap meals, email or save as PDF. |
| Yes | Recipes `[ymove_recipes]` | Embed one recipe (with schema.org `Recipe` markup) or a searchable recipe browser. Data from the [Your Move Recipe API](https://ymove.app/recipe-api/). |

The API key stays on the server: the browser only talks to the plugin's own
REST routes under `/wp-json/ymove/v1/`, which proxy to the API with caching and
per-user / per-IP limits.

## Layout

```
ymove-nutrition.php     bootstrap, constants
uninstall.php           removes data only when the owner opted in
includes/
  class-plugin.php      wiring, capabilities, daily cron (retention)
  class-settings.php    options + encrypted API key
  class-api-client.php  server-side HTTP to the Your Move API, caching, counters
  class-rest.php        /wp-json/ymove/v1/* proxy, diary CRUD, leads, admin
  class-access.php      who may track + per-user / per-IP throttles
  class-db.php          custom tables (log, targets, food_cache, leads)
  class-leads.php       calculator lead capture, captcha, CSV export
  class-mailer.php      results / meal plan emails via wp_mail
  class-meal-plans.php  stored plans, meal swaps, plan emails
  class-recipes.php     recipe shaping, licence attribution, schema.org Recipe
  class-blocks.php      block + shortcode registration and PHP renderers
  class-admin.php       settings / usage / members / leads screens
  class-privacy.php     WP privacy exporter + eraser
  helpers.php           source links, defaults, frontend config
blocks/<name>/          block.json + editor index.js (no build step; wp.element)
assets/js/              calculator, scanner, tracker, barcode, mealplan, recipes, admin
assets/css/             ymove.css (public), admin.css, fonts.css
assets/fonts/           DM Sans, Space Grotesk, Archivo Black (SIL OFL 1.1)
assets/vendor/          @zxing/library UMD (barcode fallback, Apache-2.0)
```

No build step: editor scripts use `wp.element.createElement` and the frontend
is plain JavaScript, so what is in the repo is what ships.

## Local development

```
docker compose up -d          # WordPress + MariaDB on http://localhost:8080
docker compose run --rm cli plugin activate ymove-nutrition
```

The calculators work without a key. For everything else, get a key at
https://ymove.app/nutrition-api/signup and paste it under
**Settings > Your Move Nutrition**.

PHP lint without a local PHP:

```
docker run --rm -v "$PWD":/app php:8.2-cli sh -c 'find /app -name "*.php" -not -path "*/vendor/*" -print0 | xargs -0 -n1 php -l'
```

Before a release, run [Plugin Check](https://wordpress.org/plugins/plugin-check/)
on the plugin.

## Releasing

1. Bump `Version` in `ymove-nutrition.php` and `YMOVE_NUTRITION_VERSION`, and `Stable tag` + changelog in `readme.txt`.
2. Build the zip from a clean tree: `git archive --format=zip --prefix=ymove-nutrition/ -o ymove-nutrition.zip HEAD`. Files marked `export-ignore` in `.gitattributes` (this README, docker-compose.yml, dotfiles) stay out of it.
3. wordpress.org SVN: copy the zip contents into `trunk/` and tag the version.

## Contributing

Issues and pull requests are welcome. Please keep the no-build-step approach,
follow the WordPress coding standards, and run Plugin Check before opening a PR.

## License

GPL-2.0-or-later, see [LICENSE](LICENSE). Bundled third-party code keeps its
own license: ZXing (Apache-2.0) and the web fonts (SIL OFL 1.1).
