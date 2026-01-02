# Styling Guide

We use CSS custom properties (variables) defined in `variables.css` for consistent theming across the application.

**Key principles:**
- Always use CSS variables from `variables.css` instead of hardcoded values
- Keep styles organized in separate files by section/feature (e.g., `landing.css`, `app.css`)
- Do not use inline styles in Vue components - use CSS classes instead

Styles are located in `src/resources/css/` and imported in `app.css`.

