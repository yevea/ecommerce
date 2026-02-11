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
│   ├── ShoppingCartView.php       # Shopping cart (frontend)
│   └── StoreFront.php             # Product catalog (frontend)
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

## Usage

### Admin Panel
- Access the **ecommerce** menu in the admin panel to manage categories, products, and orders
- Create categories first, then add products assigned to those categories
- Orders are created automatically when customers complete the checkout process

### Storefront
- Access the storefront at `/StoreFront`
- Browse products, filter by category, add items to cart
- Access the shopping cart at `/ShoppingCartView`
- Complete checkout by entering customer details and placing the order
