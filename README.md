# dingo-query-mapper
Uses Dingo/API Request Query Parameters to filter Laravel Models

# Installation
First, add the respective line to your composer file
```json
"require" : {
   ...
   "johannesschobel/dingoquerymapper": "0.1"
}
```
and run `composer install` to install the new component.


Then add the `ServiceProvider` to your `app.php` configuration file, like this:
```php
'providers' => [
   ...,
   JohannesSchobel\DingoQueryMapper\DingoQueryMapperServiceProvider::class,
],
```

If you want, you can overwrite the provided configuration with the following command:
```php
php artisan vendor:publish --provider="JohannesSchobel\DingoQueryMapper\DingoQueryMapperServiceProvider" --tag="config"
```
You can customize the `limit` parameter or the query parameters to be excluded from the service provider.

# Usage
## Example
In order to use the plugin, simply create a new instance from `DingoQueryMapperBuilder` and pass the request. The rest is handled by the `Builder`.

Consider the following example:
```php
    public function getAllUsers(Request $request) {

        $users = new DingoQueryMapperBuilder(new User, $request);
        $users = $users->build()->getAndPaginate();
        
        // now return the result
        return response->json($users);
    }
```

If you call the respective URI, for example like so:
```php
/index?name=j*&age>=18&limit=10
```
the `$users` will only contain (maximum) `10` users, where the `name` starts with `j` and the `age` is above `18`.

The corresponding eloquent request would look like this:
```php
$users = User::where('name', 'like', 'j%')->where('age', '>=', '18')->take(10);
``` 

Of course you can pass an existing `Builder` instead of a `Model`. This way, you can append to the builder.

Consider the following example:
```php
    public function getAllActiveUsers(Request $request) {

        $builder = User::where('is_active', '=', true);
        $users = new DingoQueryMapperBuilder($builder, $request);
        $users = $users->build()->getAndPaginate();
        
        // now return the result
        return response->json($users);
    }
```

would first filter all `active` users and then apply the custom filters (or sort order), like this:
```php
$users = User::where('is_active', '=', true)->where('name', 'like', 'j%')->where('age', '>=', '18')->take(10);
``` 

## Parameters
This plugin provides some pre-defined parameter names to be automatically filled.

### `Limit` and `Page`
In order to limit the amount of response elements, simply add respective `limit` query parameter, like this:

```php
/index?limit=20
```

This will only return the (first) `20` entries of the resultset.
In order to request the next `20` entries, simply add a `page` parameter, for example
```php
/index?limit=20&page=2
```
will return the next `20` entries, located on page `2`.


### `sort`
In order to sort the results using different parameters, you can simply concatenate them using `,`. In order to provide `ASC` and `DESC` sorting, you may prepend a `-` before respective attribute.

For example
```php
/index?sort=age,-name
```
sorts the results by age (ascending), then by name (descending; note the `-`before the `name` field!)

### `filter`
Of course you may pass custom query parameters to the builder in order to `filter` the requested data.
For example:

* `name=j*` : filters all elements, where the name starts with `j`,
* `age>=18` : filters all elements, where the age is `18` or higher,
* `city!=berlin` : filters all elements, where the city is not `berlin`

If you try to filter with a column that does not exist in the respective model, an `UnknownColumnException` will be thrown.

Thereby, this plugin offers the following operators: `=`, `!=`, `<`, `<=`, `=>`. `>`.

Of course you can combine the filters with the other query parameters:
```php
/index? name=j*&age>=18&sort=age,-name&limit=20
```
would return the first `20` elements, where the `name` starts with `j`, the `age` is `>= 18` sorted by `age ASC` and `name DESC`.
