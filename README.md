# Super Calendar by NORD

Elegant WordPress booking calendar in **#66BB6A** with a 70/30 two‑column layout (monthly calendar left, time + form right).
- **1-hour slots** from **08:00 → 19:00**, including weekends
- **Grays out taken slots** and **disables past dates**
- **WP Admin log** of bookings + **email notifications** to a configurable address
- **Shortcode:** `[super_calendar]`

## Repo Layout
```
.
├─ super-calendar-by-nord/        # Plugin source (drop this folder into wp-content/plugins/)
│  ├─ super-calendar-by-nord.php
│  ├─ public/css/style.css
│  ├─ public/js/app.js
│  └─ readme.txt                  # WordPress readme
├─ dist/                           # Built ZIP releases (upload via WP → Plugins → Upload)
├─ CHANGELOG.md
├─ LICENSE
└─ .gitignore
```

## Requirements
- WordPress 5.2+ (tested on 6.x)
- PHP 7.2+ (8.x recommended)

## Quick Install (from source)
1. Copy the `super-calendar-by-nord` folder into `wp-content/plugins/` on your site.
2. In WP Admin → **Plugins**, **Activate** “Super Calendar by NORD”.
3. Go to **Super Calendar** (left menu) to set the **notification email**.
4. Place the shortcode on any page:  
   ```
   [super_calendar]
   ```

## Quick Install (from ZIP release)
1. In WP Admin → **Plugins → Add New → Upload Plugin**.
2. Upload one of the ZIPs in `/dist`, e.g. `super-calendar-by-nord-1.0.3.zip` and **Activate**.

## Elementor Tips
- Use a **Shortcode** or **HTML** widget to render `[super_calendar]`.
- Avoid applying global “Button” styling to the widget wrapper; time-slot buttons already style themselves.
- v1.0.3 disables past dates and makes the selected time clearly highlighted under Elementor.

## Development Notes
- REST endpoints:
  - `GET /wp-json/super-calendar/v1/bookings?year=YYYY&month=M`
  - `POST /wp-json/super-calendar/v1/bookings` with JSON: `{ name, phone, email, date:"YYYY-MM-DD", hour:8..18 }`
- Database table: `wp_scbn_bookings` (prefix varies). Unique constraint `(booking_date, slot_hour)` prevents double booking.

## License
GPL-2.0-or-later. See `LICENSE`.

---

### GitHub: First Push
```bash
# From this repo root
git init
git add .
git commit -m "Initial commit: Super Calendar by NORD"
git branch -M main
git remote add origin https://github.com/<your-username>/super-calendar-by-nord.git
git push -u origin main
```
