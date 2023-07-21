# Instructions for 3rd party libraries for this plugin

Running the install command with the flags provided should ensure you download
the relevant distribution versions (if available) of the packages used.

### Installing
To install, run the following:
```
composer install \
  --ignore-platform-reqs \
  --no-interaction \
  --no-plugins \
  --no-scripts \
  --no-dev \
  --prefer-dist \
  && composer dump-autoload --no-scripts
```

### Small, safer updates
To update the packages, you should run:
```
composer update
```

### Bigger updates
Alternatively, you may delete the package you want to upgrade from the
composer.json file, and run `composer require :package-name` (where
`:package-name` is the full name of the package removed)

The initial version had been generated using this command and running the
install command as shown above:
```
composer require symfony/expression-language symfony/yaml
# Then run the install command as shown above
```

Note: to pin it to a specific version, this is the format required: `composer
require vendor/package:version`

### References

Full a full list of instructions for 3rd party plugins, please check out
https://docs.moodle.org/dev/Plugin_with_third_party_libraries
