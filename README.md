# Coffee4U on WordPress (POC)

A working WordPress implementation of the **Coffee4U** Figma prototype: a coffee shop site where customers pick a location, browse the take-out menu, build a cart, sign up, apply the `NEWSIGNUP` promo code, pay (simulated) and get an order confirmation.

It uses **no third-party plugins**. Everything is custom code in this repo:

| Part | Path | What it does |
|---|---|---|
| Theme `coffee4u` | `theme/coffee4u` | Block theme: `theme.json` design tokens (Figma colours, self-hosted Roboto), block templates, header/home patterns, CSS, carousel script |
| Plugin `coffee4u-core` | `plugin/coffee4u-core` | Content model, cart, promo codes, checkout, simulated payment, sign-up/sign-in, REST API, dynamic blocks, WP-CLI seeder |

## Quick start

Requires Docker.

```bash
./scripts/setup.sh
```

Open http://localhost:8080. Admin: http://localhost:8080/wp-admin (`admin` / `admin`).

`setup.sh` installs WordPress, removes the bundled sample plugins, activates the theme and plugin, and runs `wp coffee4u seed` to create the demo locations, menu, promo code and pages. Use another port with `WP_PORT=8888 ./scripts/setup.sh`. Reset with `docker compose down -v`.

## Demo walkthrough

1. **Home** – carousel, Cafe Locations (Kensington and Crowfoot are "Opening Shortly"), About Us, Contact Us. The header links scroll to each section.
2. **Downtown** – address, rating, hours, "Order Online for Pick-up".
3. **Take-Out Menu** (`/locations/downtown/menu/`) – click an item to open its card, choose a quantity, **Add to Cart**. The cart on the right supports +/- and shows the total.
4. **Confirm Order** – guests get the "Log-in or Register" dialog. **Sign-up** opens "Create User Profile". After signing up you go straight to checkout and your cart is kept.
5. **Checkout** – enter `NEWSIGNUP`. It takes the highest-priced beverage off (first paid order of a registered customer only). Totals show Order Total, Discounts, GST 5% and a $0.50 service fee.
6. **Secure Payment** – use `4242 4242 4242 4242`, any future expiry (e.g. `12/30`) and any 3-digit CVV. `4000 0000 0000 0002` simulates a declined card.
7. **Order Confirmed** – order number (starting at 12345) and pick-up time. Past orders are under **My Profile → My Orders**.

## How it works

**Content (wp-admin → Coffee4U)**
- *Locations* (`c4u_location`): address, phone, hours, rating, preparation time, status (Open / Opening Shortly).
- *Menu Items* (`c4u_product`) in *Menu Sections* (`c4u_menu_section`): price, description (excerpt), photo, section order. A section can be flagged as a beverage section.
- *Promo Codes* (`c4u_coupon`): percentage, fixed amount, or one free beverage, optionally limited to a customer's first order.
- *Orders* (`c4u_order`): read-only, with customer, location, line items, totals and payment reference.
- *Settings*: tax rate and service fee.

**Storefront**
- Pages are block templates built from dynamic blocks (`coffee4u/menu`, `coffee4u/checkout`, `coffee4u/payment`, ...), so they can be placed and rearranged in the Site Editor.
- `/locations/{slug}/menu/` is a rewrite endpoint that loads the `single-c4u_location-menu` template.
- The front end is plain JavaScript (`plugin/coffee4u-core/assets/js/storefront.js`) calling the plugin's REST API under `/wp-json/coffee4u/v1/`:

| Method | Route | Purpose |
|---|---|---|
| GET | `/cart` | Current cart with totals |
| POST | `/cart/items` | Add an item (one location per cart) |
| PUT | `/cart/items/{product_id}` | Change quantity (0 removes) |
| POST / DELETE | `/cart/coupon` | Apply / remove a promo code |
| POST | `/checkout` | Create an order from the cart (signed in) |
| POST | `/orders/{id}/pay` | Simulated card payment (order owner only) |
| POST | `/auth/register`, `/auth/login` | Customer sign-up / sign-in |

**Security and data**
- Amounts are integer cents and are always recalculated on the server. Promo rules are checked again at checkout.
- Guest carts live in a transient keyed by an HttpOnly cookie and are merged into the account on sign-in.
- The payment step checks the card number (Luhn and brand), expiry and CVV, then throws the card data away. Only the brand, last four digits and a reference are stored.
- Customers can only view or pay for their own orders. REST calls use the core cookie and nonce authentication.

## Design source

Figma file *Coffee4U* (desktop frames, 1024px wide). Colours, type sizes and assets come from the Figma frames through the Figma MCP server. The prototype has no mobile frames, so the mobile layout (hamburger menu, stacked cart, two-column menu grid) was added.

Out of scope for this POC: group/office ordering (the button shows "coming soon"), editing the user profile, saved payment cards, and real payment processing.

## Layout

```
docker-compose.yml               WordPress, MariaDB and WP-CLI services
scripts/setup.sh                 Install, configure, activate and seed
theme/coffee4u/                  Block theme
plugin/coffee4u-core/
  coffee4u-core.php              Bootstrap
  includes/post-types.php        Content model and lookups
  includes/cart.php              Cart storage
  includes/pricing.php           Totals and promo code rules
  includes/orders.php            Orders
  includes/payment.php           Simulated gateway
  includes/auth.php              Sign-up / sign-in
  includes/rest.php              REST API
  includes/blocks.php            Dynamic blocks, template routing, dialogs
  includes/admin.php, settings.php
  includes/cli.php               `wp coffee4u seed`
  seed/images/                   Menu photos used by the seeder
```

## Running in LocalWP

Copy `theme/coffee4u` into `wp-content/themes/` and `plugin/coffee4u-core` into `wp-content/plugins/`. Activate both, then run `wp coffee4u seed` from LocalWP's "Open site shell".
