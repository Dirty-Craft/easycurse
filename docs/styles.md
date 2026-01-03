# Styling Guide

We use CSS custom properties (variables) defined in `variables.css` for consistent theming across the application.

**Key principles:**
- Always use CSS variables from `variables.css` instead of hardcoded values
- Keep styles organized in separate files by section/feature (e.g., `landing.css`, `app.css`)
- Do not use inline styles in Vue components - use CSS classes instead

Styles are located in `src/resources/css/` and imported in `app.css`.

## Theme System

The application supports both **dark** and **light** themes. The theme system is implemented using CSS custom properties and a data attribute on the root element.

### Theme Switcher

A theme switcher component (`ThemeSwitcher.vue`) is available in the navbar that allows users to toggle between dark and light themes. The theme preference is:
- Saved in `localStorage` and persists across sessions
- Automatically detected from system preferences if no manual selection has been made
- Synchronized across all pages via the `useTheme` composable

### Using Theme-Aware Styles

All color variables in `variables.css` automatically adapt to the current theme. The light theme overrides are defined using the `:root[data-theme="light"]` selector.

**Example:**
```css
/* This will use dark theme colors by default */
.my-component {
    background: var(--color-background);
    color: var(--color-text-primary);
}

/* For theme-specific overrides, use the data-theme attribute */
:root[data-theme="light"] .my-component {
    /* Light theme specific styles */
}
```

### Theme-Specific Overrides

Some components have theme-specific styling:

- **Primary Button**: In light mode, primary button text is white for better contrast against the primary color gradient background. In dark mode, it uses dark text (`var(--color-text-dark)`).

### Theme Variables

All color variables automatically switch between themes:
- `--color-background`: Main background color
- `--color-text-primary`: Primary text color
- `--color-primary`: Primary brand color
- And all other color variables defined in `variables.css`

See `src/resources/css/variables.css` for the complete list of theme-aware variables.
