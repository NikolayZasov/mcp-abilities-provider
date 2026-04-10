# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Single-file WordPress plugin (`mcp-abilities-provider.php`) that bridges WordPress sites with AI agents via the Model Context Protocol. It registers 60+ abilities using WordPress's Abilities API, making them available to MCP clients (Claude, Cursor, etc.).

## Requirements

- WordPress 6.9+ (Abilities API must be present — detected via `function_exists('wp_register_ability')`)
- MCP Adapter plugin v0.4.0+
- PHP 8.1+
- WooCommerce 8.0+ (optional, for WooCommerce abilities)
- Classified Listing Pro/Free (optional, for Classified Listing abilities)
- Classified Listing Store Addon (optional, for Store/Membership abilities)

## Architecture

The entire plugin lives in one file with no build system. Structure:

1. **Dependency check** — bails early if `wp_register_ability` doesn't exist
2. **Core abilities exposure** — `wp_register_ability_args` filter marks `core/get-site-info`, `core/get-user-info`, `core/get-environment-info` as `mcp.public`
3. **Category registration** — hooked to `wp_abilities_api_categories_init`
4. **Ability registration** — hooked to `wp_abilities_api_init`, calls grouped functions per domain:
   - `mcpap_register_post_abilities()`
   - `mcpap_register_page_abilities()`
   - `mcpap_register_media_abilities()`
   - `mcpap_register_taxonomy_abilities()`
   - `mcpap_register_user_abilities()`
   - `mcpap_register_settings_abilities()`
   - `mcpap_register_menu_abilities()`
   - `mcpap_register_plugin_abilities()`
   - `mcpap_register_comment_abilities()`
   - WooCommerce group (only when `class_exists('WooCommerce')`):
     - `mcpap_register_wc_product_abilities()`
     - `mcpap_register_wc_product_taxonomy_abilities()`
     - `mcpap_register_wc_order_abilities()`
     - `mcpap_register_wc_coupon_abilities()`
   - Classified Listing group (only when `defined('RTCL_VERSION')`):
     - `mcpap_register_rtcl_listing_abilities()`
     - `mcpap_register_rtcl_taxonomy_abilities()`
     - `mcpap_register_rtcl_config_abilities()`
   - Classified Listing Store Addon (only when `class_exists('RtclStore')`):
     - `mcpap_register_rtcl_store_abilities()`

## Ability Registration Pattern

Every ability follows this structure when calling `wp_register_ability()`:

```php
wp_register_ability( 'mcpap/ability-name', [
    'label'               => __( 'Label', 'mcp-abilities-provider' ),
    'description'         => __( 'Description', 'mcp-abilities-provider' ),
    'category'            => 'content', // content | taxonomy | settings | classified
    'readonly'            => true,      // omit or false for write operations
    'meta'                => [ 'mcp' => [ 'public' => true, 'type' => 'tool' ] ],
    'input_schema'        => [ 'type' => 'object', 'properties' => [ ... ] ],
    'execute_callback'    => function ( array $input ): array { ... },
    'permission_callback' => function (): bool {
        return current_user_can( 'edit_posts' );
    },
] );
```

## Security Conventions

- All input sanitized: `sanitize_text_field()`, `absint()`, `wp_kses_post()`
- Every ability has a `permission_callback` using `current_user_can()`
- Destructive operations (delete) require elevated caps (e.g., `delete_posts`, `delete_products`)
- WooCommerce abilities use native WC objects (`WC_Product`, `WC_Order`, `WC_Coupon`) — never raw DB queries
- Classified Listing abilities use standard WP API (`WP_Query`, `get_terms`, `get_post_meta`) with `_rtcl_` meta prefix
- Store abilities auto-detect CPT name (`store` or `rtcl_store`)

## Versioning

Plugin version is defined as `MCPAP_VERSION` constant and must match the `Version:` header in the plugin file and `Stable tag:` in `readme.txt`.
