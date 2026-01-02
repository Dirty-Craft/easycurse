# Testing
All the features should have feature tests written for them in this project. We do not use Unit tests.

To run the tests: 

```shell
$ docker compose exec app php artisan test
```

You can also get coverage report or run in parallel mode for more efficiency:

```shell
$ docker compose exec app php artisan test --parallel --coverage
```

For an easier and quick command, you can use this:

```shell
$ docker compose exec app composer test
# It runs all the necessary setup commands too before testing
```
