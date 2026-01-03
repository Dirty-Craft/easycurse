module.exports = {
    extends: ['stylelint-config-standard'],
    ignoreFiles: [
        'node_modules/**',
        'vendor/**',
        'public/build/**',
        'storage/**',
        'bootstrap/cache/**',
    ],
    rules: {
        // Allow our BEM-style class naming (e.g. .bill-card__icon--paid, .app-button--primary)
        // while still enforcing a consistent lowercase/kebab base.
        'selector-class-pattern': [
            '^[a-z0-9]+(?:-[a-z0-9]+)*(?:__(?:[a-z0-9]+(?:-[a-z0-9]+)*))?(?:--(?:[a-z0-9]+(?:-[a-z0-9]+)*))?$',
            {
                resolveNestedSelectors: true,
                message:
                    'Expected class selector "%s" to be in our BEM-style (block[-block]__element--modifier) using lowercase letters and hyphens.',
            },
        ],

        // Our animation names often use camelCase / PascalCase – don't enforce kebab-case.
        'keyframes-name-pattern': null,

        // The design system intentionally re-declares selectors for overrides,
        // so these rules generate a lot of noisy, non-actionable warnings.
        'no-descending-specificity': null,
        'no-duplicate-selectors': null,

        // Allow Vue-specific pseudo-class selectors like :deep()
        'selector-pseudo-class-no-unknown': [
            true,
            {
                ignorePseudoClasses: ['deep'],
            },
        ],
    },
};



