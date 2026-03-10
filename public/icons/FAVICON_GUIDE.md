# Favicon Generation Guide

## Existing Favicon Files

The project already has the following favicon files:

- `/favicon.png` - Main favicon (512x512 recommended)
- `/icons/` - PWA icons in various sizes

## Required Icon Sizes

For complete favicon support across all devices, you need:

### Browser Tab Icons
- `favicon-16x16.png` (16x16)
- `favicon-32x32.png` (32x32)
- `favicon-96x96.png` (96x96)

### Apple Touch Icons
- `apple-touch-icon.png` (180x180) - For iPhone/iPad home screen

### Android/Chrome Icons
- `icon-72x72.png`
- `icon-96x96.png`
- `icon-128x128.png`
- `icon-144x144.png`
- `icon-152x152.png`
- `icon-192x192.png`
- `icon-384x384.png`
- `icon-512x512.png`

### Microsoft Tiles
- `smalltile.png` (150x150)
- `mediumtile.png` (310x150)
- `widetile.png` (310x310)
- `largetile.png` (558x270)

## Quick Generation

### Using RealFaviconGenerator

1. Go to https://realfavicongenerator.net/
2. Upload your main favicon (512x512 PNG)
3. Configure for all platforms
4. Download the generated package
5. Place files in `/public/` directory

### Using Node.js (sharp)

```bash
npm install -g sharp-cli

# Generate all sizes from main icon
sharp public/favicon.png -o public/icons/icon-192x192.png -r 192x192
sharp public/favicon.png -o public/icons/icon-512x512.png -r 512x512
sharp public/favicon.png -o public/icons/apple-touch-icon.png -r 180x180
sharp public/favicon.png -o public/icons/favicon-32x32.png -r 32x32
sharp public/favicon.png -o public/icons/favicon-16x16.png -r 16x16
```

### Using ImageMagick

```bash
# Convert to different sizes
convert favicon.png -resize 16x16 icons/favicon-16x16.png
convert favicon.png -resize 32x32 icons/favicon-32x32.png
convert favicon.png -resize 180x180 icons/apple-touch-icon.png
convert favicon.png -resize 192x192 icons/icon-192x192.png
convert favicon.png -resize 512x512 icons/icon-512x512.png
```

## HTML Implementation

The base template (`templates/base.html.twig`) already includes all necessary meta tags:

```html
<link rel="icon" type="image/png" href="/favicon.png?v=2">
<link rel="shortcut icon" type="image/png" href="/favicon.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
<link rel="manifest" href="/manifest.json">
```

## Testing

Test your favicon implementation:

1. https://realfavicongenerator.net/favicon_checker
2. https://www.simicart.com/faq/google-favicon-checker/
3. Browser DevTools → Application → Manifest

## Browser Support

| Browser | Format | Size |
|---------|--------|------|
| Chrome | PNG | 192x192, 512x512 |
| Firefox | PNG | 192x192, 512x512 |
| Safari | PNG | 180x180 (touch icon) |
| Edge | PNG | Various |
| IE 11 | PNG | 310x310 (tile) |

## Best Practices

1. **Use PNG format** - Better quality than ICO
2. **Keep it simple** - Recognizable at small sizes
3. **High contrast** - Visible on different backgrounds
4. **No transparency** - Some browsers don't support it
5. **Version parameter** - Add `?v=2` to force cache refresh

## Current Status

✅ Main favicon exists: `/favicon.png`
✅ PWA manifest configured: `/manifest.json`
✅ Meta tags in base template
⚠️ Additional icon sizes should be generated for full support
