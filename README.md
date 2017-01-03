# dingo-query-mapper
Uses Dingo/API Request Query Parameters to filter Laravel Collections.
For example, you are able to automatically filter, sort, and limit collections based on query parameters of the URI.

# Installation
First, add the respective line to your composer file
``` json
"require" : {
   ... ,
   "johannesschobel/dingoquerymapper": "dev-master" ,
}
```

and run `composer install` to install the new component.

Then add respective `ServiceProvider` from the package to your `config/app.php` configuration file, like this:

``` php
'providers' => [
   ... ,
   JohannesSchobel\DingoQueryMapper\DingoQueryMapperServiceProvider::class,
],
```

If you want, you can overwrite the basic configuration using the following command:

```php
php artisan vendor:publish --provider="JohannesSchobel\DingoQueryMapper\DingoQueryMapperServiceProvider" --tag="config"
```

This will copy the `dingoquerymapper` configuration file to your `config` folder. Using this file, you can 
customize the `limit` parameter or the query parameters to be excluded from the service provider.
This is handy, if you need to omit parameters from automatically parsed for filtering (e.g., you may want to 
exclude the `token` parameter if you are using `JWT`).

# Usage
## Example
In order to use the plugin, simply create a new instance from `DingoQueryMapper` and pass the request.
Furthermore, you want to call respective `createFromXXX()` method from the `DingoQueryMapper`, which basically
allows to create the mapper from various inputs (e.g., `Collection`, `Builder`, ...)

Consider the following example:

```php
public function getAllUsers(Request $request) {

    $users = User::all()->get();
    $qm = new DingoQueryMapper($request);
    $users = $qm->createFromCollection($users)->paginate();
        
    // now return the result
    return response->json($users);
}
```

If you call the respective URI, for example like so:
```php
/index?name=j*&age>=18&limit=10&page=3
```

it will output (a maximum of) `10 User`s per page, where the `name` attribute starts with `j` and the `age` is 
greater or equal than `18` starting on `page 3`.

## Dingo/API Example
If you use [Dingo/API](https://github.com/dingo/api) as your preferred API framework, you can use this package right 
away. If you are not using Dingo/API, you should really consider using it - it is awesome!

However, all of the information described above still remains when using Dingo/API. Only, the returning the results 
varies because you need to use Dingo's response objects.

You can simply return your results using
```php 
public function getAllUsers(Request $request) {

    $users = User::all()->get();
    $qm = new DingoQueryMapper($request);
    $users = $qm->createFromCollection($users)->paginate();
        
    // now return the result
    
    return $this->response
       ->paginator($users, new UserTransformer());
}
```

That's all - really!

## Parameters
This plugin provides some pre-defined parameter names to be automatically filled.

### `limit` and `page`
In order to limit the amount of response elements, simply add respective `limit` query parameter, like this:

```php
/index?limit=20
```

This will only return the (first) `20` entries of the result.
In order to request the next `20` entries, simply add a `page` parameter to the query, like this:
```php
/index?limit=20&page=2
```
will return the next `20` entries that are located on `page 2`.


### `sort`
In order to sort the results using different parameters, you can simply concatenate them using `,`. In order to provide `ASC` and `DESC` sorting, you may prepend a `-` before respective attribute.

For example
```php
/index?sort=age,-name
```
sorts the results by age (ascending), then by name (descending; note the `-` before the `name` field!)

### `custom filters`
Of course you may pass custom query parameters to the builder in order to `filter` the requested data.
For example:

* `name=j*` : filters all elements, where the name starts with `j`,
* `age>=18` : filters all elements, where the age is `18` or higher,
* `city!=berlin` : filters all elements, where the city is not `berlin`

If you try to filter using a column that does not exist in the respective model, it will be ignored.

At the moment, this plugin offers the following operators:
* `=` --> compare equality
* `!=` --> compare unequality
* `<` --> smaller than 
* `<=` --> smaller or equal than
* `=>` --> greater or equal than
* `>` --> greater than

Of course you can combine the filters with the other query parameters:
```php
/index?name=j*&age>=18&sort=age,-name&limit=20
```
would return the first `20` elements, where the `name` starts with `j`, the `age` is `>= 18` 
sorted by `age ASC` and `name DESC`.
