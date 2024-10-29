If you need to adjust the rendered html beyond what's possible with the standard parameters, you can
publish the addon's views and make them your own. To do that, run the following command in your
console. You'll then find the views in `resources/views/vendor/statamic-mux/`.

```sh
php artisan vendor:publish --tag=statamic-mux-views
```
