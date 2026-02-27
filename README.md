# Ecommerce Plugin for FacturaScripts

**Minimal version of FacturaScripts:** 2025.71  
**License:** LGPL v3  
**Web:** [facturascripts.com](https://facturascripts.com)

A simple ecommerce / shopping cart plugin for FacturaScripts. Provides product catalog management, shopping cart functionality, and order processing — all integrated into the FacturaScripts admin panel.

## Features

- **Product Management** — Create and manage products with name, reference, description, price, stock, and image
- **Category Management** — Organize products into categories
- **Storefront** — Public-facing product catalog with category filtering
- **Shopping Cart** — Session-based cart with add, update quantity, and remove functionality
- **Order Processing** — Checkout flow that converts cart items into orders with customer details
- **Order Management** — View and manage orders with line items, status tracking (pending, processing, completed, cancelled)
- **Translations** — English and Spanish language support

## Plugin Structure

```
ecommerce/
├── Controller/                    # Controllers
│   ├── EditEcommerceCategory.php  # Edit category (admin)
│   ├── EditEcommerceOrder.php     # Edit order (admin)
│   ├── EditEcommerceProduct.php   # Edit product (admin)
│   ├── ListEcommerceCategory.php  # List categories (admin)
│   ├── ListEcommerceOrder.php     # List orders (admin)
│   ├── ListEcommerceProduct.php   # List products (admin)
│   ├── Productos.php              # Product catalog (frontend, /Productos)
│   ├── ShoppingCartView.php       # Shopping cart (frontend)
│   └── StoreFront.php             # Storefront product catalog (frontend, /StoreFront)
├── Model/                         # Data models
│   ├── EcommerceCartItem.php      # Cart item model
│   ├── EcommerceCategory.php      # Category model
│   ├── EcommerceOrder.php         # Order model
│   ├── EcommerceOrderLine.php     # Order line item model
│   └── EcommerceProduct.php       # Product model
├── Table/                         # Database table definitions (XML)
│   ├── ecommerce_cart_items.xml
│   ├── ecommerce_categories.xml
│   ├── ecommerce_order_lines.xml
│   ├── ecommerce_orders.xml
│   └── ecommerce_products.xml
├── Translation/                   # i18n translations
│   ├── en_EN.json
│   └── es_ES.json
├── View/                          # Twig templates (frontend)
│   ├── Productos.html.twig
│   ├── ShoppingCartView.html.twig
│   └── StoreFront.html.twig
├── XMLView/                       # XML view definitions (admin)
│   ├── EditEcommerceCategory.xml
│   ├── EditEcommerceCategoryProducts.xml
│   ├── EditEcommerceOrder.xml
│   ├── EditEcommerceOrderLine.xml
│   ├── EditEcommerceProduct.xml
│   ├── ListEcommerceCategory.xml
│   ├── ListEcommerceOrder.xml
│   └── ListEcommerceProduct.xml
├── Init.php                       # Plugin initialization
├── composer.json                  # PHP dependencies
├── facturascripts.ini             # Plugin metadata
├── LICENSE
└── README.md
```

## Installation

1. Copy the `ecommerce` folder into your FacturaScripts `Plugins/` directory
2. Go to the FacturaScripts admin panel
3. Navigate to **Admin > Plugins** and enable the **ecommerce** plugin
4. The plugin will create the necessary database tables automatically

## Configuration

### Stripe Payment Gateway

To enable the Stripe payment gateway, you must set your Stripe API keys in the FacturaScripts settings panel:

1. Log in to the FacturaScripts admin panel
2. Navigate to **Admin > Settings** (or go to `/AppSettings`)
3. Select the **E-Commerce** tab
4. Enter your **Stripe Secret Key** (`sk_live_...` or `sk_test_...` for testing)
5. Enter your **Stripe Public Key** (`pk_live_...` or `pk_test_...` for testing)
6. Save the settings

You can obtain your API keys from the [Stripe Dashboard](https://dashboard.stripe.com/apikeys).

> **Note:** Use test keys (`sk_test_...` / `pk_test_...`) during development and switch to live keys for production.

## Usage

### Admin Panel
- Access the **ecommerce** menu in the admin panel to manage categories, products, and orders
- Create categories first, then add products assigned to those categories
- Orders are created automatically when customers complete the checkout process

### Storefront
- Access the storefront at `/StoreFront` or `/Productos`
- Browse products, filter by category, add items to cart
- Use the **Pay with Stripe** button for instant single-product checkout via Stripe
- Access the shopping cart at `/ShoppingCartView`
- Complete checkout by entering customer name, email, delivery/invoice address, and placing the order
