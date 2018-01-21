# PluginDx for WordPress / WooCommerce

If you build extensions or themes for WordPress, you know support can be tricky. With PluginDx, you can provide incredible support for your customers and save hours of time:

- Provide support directly inside the WordPress admin panel where your configuration lives.
- Reduce ticket volume by giving your customers answers instantly with a built-in knowledge base.
- Eliminate the annoying back-and-forth emails with store diagnostics automatically sent back to you.
- Quickly determine common issues and point them out inside your extension with diagnostic rules.
- Get valuable usage data and support analytics directly inside our app.

## Getting Started

To get started, you'll need to [sign up for PluginDx](https://app.plugindx.com/register) and add WordPress or WooCommerce as your first integration. From there, you'll be given specific instructions on how to add PluginDx to your plugin. At a bare minimum, you'll add a button to open a support panel:

```php
<script src="https://app.plugindx.com/embed.js" async></script>
<div class="plugindx" data-label="Support" data-key="YOUR INTEGRATION KEY" data-platform="woocommerce" data-report="' . admin_url('admin-ajax.php') . '"></div>
```

In WooCommerce, you could add the following to your array of form fields:

```php
'support' => array(
    'title'             => __( 'Support', 'wc' ),
    'type'              => 'hidden',
    'default'           => 'no',
    'description'       => __( '<div class="plugindx" data-label="Support" data-key="YOUR INTEGRATION KEY" data-report="' . admin_url('admin-ajax.php') . '"></div>', 'wc' ),
)
```

This should be placed where you'd like the PluginDx button to be shown to your users. To generate diagnostic reports, you'll need to copy over the code from this framework into your plugin.

## How It Works

This class is a small library to assist PluginDx with providing WordPress / WooCommerce diagnostics and server configuration info. You'll need to include it with your plugin or theme. Out of the box, we'll return high-level data about your customer's WordPress / WooCommerce instance such as the current version and store URL. We'll also return server info such as the PHP version. If you'd like to see more data, you can customize the configuration JSON in your [PluginDx account](https://app.plugindx.com). This JSON configuration allows you to pull the following data from WordPress / WooCommerce:

- Configuration data specific to your plugin or theme
- Native configuration data for a WordPress / WooCommerce instance
- Currently installed plugins

We recommend only pulling data that you need to answer support requests. You should be upfront with your customers and tell them explicitly what kind of data you're collecting for support.

## Supported Versions

- WordPress 4.5+
- WooCommerce 3.0+