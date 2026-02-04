# Installed Packages
Here is a list of additionally installed [Composer packages](../src/composer.json) and [NPM](../src/package.json) in this project:

### Composer
- brianium/paratest
- inertiajs/inertia-laravel
- beyondcode/laravel-er-diagram-generator

To install/update packages:

```shell
docker compose exec app composer require ...
docker compose exec app composer install
docker compose exec app composer update
```

### NPM
- @inertiajs/vue3
- @eslint/js
- @vue/eslint-config-prettier
- eslint
- eslint-plugin-vue
- stylelint
- stylelint-config-standard

To install/update packages:

```shell
docker compose exec app npm add ...
docker compose exec app npm update
```

### PHP
The used PHP version can be found/changed in [Dockerfile](../docker/Dockerfile).
