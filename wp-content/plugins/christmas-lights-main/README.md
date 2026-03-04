# Christmas Lights

A lightweight web component and WordPress plugin that adds a festive string of Christmas lights at the top of your website. Default palette is `classic`.

## Web Component (CDN)

Include the script and add the element:

```html
<script type="module" src="https://cdn.example.com/christmas-lights@0.1.0/christmas-lights.js"></script>
<christmas-lights count="24" colors="classic" twinkle="true" speed="0.6" size="1" offset="0" spacing="64" fit="true"></christmas-lights>
```

### Options

- `count`: number of bulbs (default 24). When `fit` is `true`, acts as a minimum and auto-fits to fill the viewport.
- `colors`: preset name (`classic`, `warm`) or comma-separated list of CSS colors. Default: `classic` (red, green, blue, orange, white).
- `twinkle`: `true` | `false`. Enables bulb twinkle and halo pulse.
- `speed`: animation speed multiplier. Lower values slow twinkle/halo (default 0.6).
- `size`: scale multiplier for bulbs and wire (default 1).
- `offset`: vertical offset in pixels from top of viewport (default 0).
- `spacing`: fixed pixel spacing between bulbs (default 64). Spacing stays constant on resize.
- `fit`: `true` | `false`. When `true`, auto-calculates `count` to stretch across the viewport while keeping spacing fixed (default `true`).

Examples:

```html
<!-- Warm palette, slower and larger -->
<christmas-lights colors="warm" speed="0.5" size="1.2"></christmas-lights>

<!-- Custom colors and fixed count without auto-fit -->
<christmas-lights colors="#ff0000,#00ff00,#0000ff" count="18" fit="false"></christmas-lights>

<!-- More space between bulbs -->
<christmas-lights spacing="80"></christmas-lights>
```

## WordPress Plugin

1. Copy this plugin folder to `wp-content/plugins/christmas-lights`.
2. Activate the plugin.
3. Lights appear at the top of your site.

### Filters

- `christmas_lights_component_src`: override script source, e.g. to point to your CDN.
- `christmas_lights_component_attrs`: provide attributes array to customize behavior.

Example (in theme’s `functions.php`):

```php
add_filter( 'christmas_lights_component_src', function( $src ) {
    return 'https://cdn.example.com/christmas-lights@0.1.0/christmas-lights.js';
});

add_filter( 'christmas_lights_component_attrs', function( $attrs ) {
    return array_merge( $attrs, array(
        'count' => 30,
        'colors' => 'classic',
        'twinkle' => 'true',
        'speed' => '0.6',
        'size' => '1',
        'offset' => '0',
        'spacing' => '64',
        'fit' => 'true',
    ) );
});
```

## Notes

- Pure web-component with Shadow DOM; no dependencies.
- Minimal animations for performance; pointer-events are disabled.
- Uses `wp_body_open` with footer fallback to ensure rendering.
