# WooCommerce Test Data - Complete Store Setup

These CSV files create a realistic WooCommerce store for testing plugins. After importing, your store will look and feel like a real operating ecommerce business.

## Files Included

| File | What It Creates | Count |
|------|----------------|-------|
| `products.csv` | Products with full descriptions, images, categories, prices, variations | 25 products |
| `reviews.csv` | Product reviews with ratings, verified buyers | 50+ reviews |
| `orders.csv` | Order history with various statuses and payment methods | 50 orders |
| `customers.csv` | Customer accounts with addresses and purchase history | 35 customers |
| `coupons.csv` | Discount coupons of various types | 12 coupons |

## Import Order (Important!)

Import in this exact order to avoid dependency issues:

1. **Products** → WooCommerce > Products > Import
2. **Customers** → Use "Import Export Users" plugin or WP All Import
3. **Orders** → Use "Import Export Order" plugin or WP All Import
4. **Reviews** → Use "WP All Import" or manual SQL import
5. **Coupons** → Use "Import Export Coupons" plugin or WP All Import

## Products CSV - Import via WooCommerce Built-in

Go to: **WooCommerce > Products > Import** (native WooCommerce feature)

The `products.csv` uses the standard WooCommerce product import format. Simply:
1. Go to Products > All Products > Import
2. Select `products.csv`
3. Map columns (should auto-detect)
4. Run import

### Products Include:
- Electronics (MacBook, iPhone, Sony headphones, Samsung TV, etc.)
- Home & Kitchen (Dyson, KitchenAid, Ember, AeroPress)
- Clothing & Apparel (Nike, Lululemon, Patagonia, Allbirds)
- Health & Fitness (Theragun, Apple Watch)
- Furniture (Secretlab gaming chair)
- Price range: $38 - $3,499
- Images from Unsplash (free, no attribution needed for testing)
- Categories, tags, upsells, cross-sells all configured

## Reviews CSV - Import Options

**Option A: WP All Import Pro (recommended)**
- Map `product_id` to the imported product
- Map other fields directly

**Option B: Manual SQL** (for developers)
```sql
-- Import directly into wp_comments table
-- review_rating goes into wp_commentmeta with meta_key = 'rating'
```

## Orders CSV - Import Options

**Recommended Plugin:** "Order Import Export for WooCommerce" (free on WordPress.org)

Orders include:
- Completed, Processing, On-hold, Cancelled, Refunded, Pending, Failed statuses
- Stripe, PayPal, and Direct Bank Transfer payments
- Multi-item orders with line items
- Tax calculations
- Coupon usage
- Customer notes
- Free shipping and flat rate methods

## Customers CSV - Import Options

**Recommended Plugin:** "Import Export WordPress Users" or WP All Import

Customers include:
- Full billing + shipping addresses (real US cities)
- Phone numbers
- Company names (some)
- Registration dates
- Purchase history stats

## Coupons CSV - Import Options

**Recommended Plugin:** "Import Export for WooCommerce" or WP All Import

Coupons include:
- Percentage discounts (10%, 15%, 20%, 25%, 30%)
- Fixed cart discounts ($10, $25, $50)
- Free shipping coupon
- Category-restricted coupons
- Email-restricted (VIP/loyalty) coupons
- Usage limits and expiry dates
- Minimum/maximum order amounts

## After Import - Store Stats

Your test store will have:
- **~$28,000** in total revenue (last 5 months)
- **~$560** average order value
- **50 orders** across 5 months
- **35 registered customers** (10 repeat buyers)
- **25 products** across 6+ categories
- **50+ verified reviews** (4-5 star ratings)
- **12 active coupon codes**
- Multiple payment gateways used
- Mix of free shipping and paid shipping

This gives the Revenue Leak Scanner plugin realistic data to calculate revenue impacts accurately.
