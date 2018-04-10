This is a Laravel package for working with nested trees in MongoDB (https://github.com/jenssegers/laravel-mongodb).

Based on [kalnoy/nestedset](https://github.com/lazychaser/laravel-nestedset)

__Contents:__

- [Installation](#installation)
- [Usage](#usage)

Installation
------------

To install the package, in terminal:

```
composer require beyondlex/mongotree
```

#### The model

Your model should use `Lex\Mongotree\TreeTrait` trait to enable nested sets:

```php
use Lex\Mongotree\TreeTrait;

class Foo extends Model {
    use TreeTrait;
}
```

Usage
------------

For usage please refer to original library https://github.com/lazychaser/laravel-nestedset
