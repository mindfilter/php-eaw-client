# PHP easy@work API client

 * [Installation](#installation)
 * [Configuration](#configuration)
 * [Usage](#usage)
   * [As a shell](#usage-shell)
   * [In stand-alone script](#usage-standalone)
   * [As a CLI](#usage-cli)

## <a name="installation"></a>Installation

```sh
git clone https://github.com/easyatworkas/php-eaw-client.git
cd php-eaw-client
composer install
```

## <a name="configuration"></a>Configuration

Copy `.env.example` to `.env`, then edit `.env` and add your authentication details.

## <a name="usage"></a>Usage

### <a name="usage-shell"></a>As a shell
```sh
./e <PHP file>
```

#### Using models
```php
// Create new customer.
$customer = Customer::newInstance([
    'name' => 'Easy at Work AS',
    'number' => 1,
])->save();

// Fetch customer with lowest ID.
$customer = Customer::newQuery()
    ->orderBy('id')
    ->direction('asc')
    ->perPage(1)
    ->getAll() // Returns a Paginator
    ->next(); // Return first item.

// Fetch customer 1.
$customer = Customer::get(1);

// Update customer 1's number.
Customer::get(1)->update([ 'number' => 1337 ]);

// Delete customer 1.
Customer::get(1)->delete();

// Handle paginated responses.
$employees = Customer::get(1)->employees()->getAll();
foreach ($employees as $employee) {
    echo $employee->name, PHP_EOL;
}
```

#### Using API client directly:
```php
// Upload new profile picture.
eaw()->create('/users/1/picture', null, null, [ 'picture' => fopen('avatar.jpg', 'r') ]);

// Create new customer.
eaw()->create('/customers', null, [ 'name' => 'Easy at Work AS', 'number' => 1 ]);

// Fetch customer with lowest ID.
eaw()->read('/customers', [ 'order_by' => 'id', 'direction' => 'asc', 'per_page' => 1 ]);

// Fetch customer 1.
eaw()->read('/customers/1');

// Update customer 1's number.
eaw()->update('/customers/1', null, [ 'number' => 1337 ]);

// Delete customer 1.
eaw()->delete('/customers/1');

// Handle paginated responses.
$employees = eaw()->readPaginated('/customers/1/employees');
foreach ($employees as $employee) {
    echo $employee['name'], PHP_EOL;
}
```

### <a name="usage-standalone"></a>In a stand-alone script

Stand-alone scripts do not need to be launched via `./e`, but can instead be invoked directly; `php my_script.php`. All you need to do is include the bootstrapper, and you're ready to go:

```php
require('bootstrap/bootstrap.php');

$customers = eaw()->readPaginated('/customers', [ 'order_by' => 'name' ]);

foreach ($customers as $customer) {
    echo $customer['name'], PHP_EOL;
}
```

### <a name="usage-cli"></a>As a CLI

This is a work in progress. POST data is still not passable.

```shell
./e create "/customers?name=Easy at Work AS&number=1"
./e read "/customers?order_by=id&direction=asc&per_page=1"
./e read /customers/1
./e update /customers/1?number=1337
./e delete /customers/1
```
