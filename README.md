# HAL API Client
A PHP client for interacting with HAL APIs.

## Installation

### 1. Install via Composer

You can install the package via Composer. Run the following command in your terminal:

```bash
composer require amanank/hal-client
```

If you haven't already, make sure to include the Composer autoload file in your project:

```php
require 'vendor/autoload.php';
```

### 2. Publish the Configuration File
After installing the package, you can publish the configuration file using the following Artisan command:

```php
php artisan vendor:publish --tag=config --provider="Amanank\HalClient\Providers\HalClientServiceProvider"
```

This will create a configuration file named `hal-client.php` in your `config` directory.

#### Note
Laravel's auto-discovery feature will automatically register the `HalClientServiceProvider` for you. You do not need to manually register it in your `config/app.php` file.

### 3. Configure `hal-client.php`

After registering the service provider, you need to configure it. Create a configuration file named `hal-client.php` in your `config` directory with the following content:

```php
return [
    'base_uri' => env('HAL_API_BASE_URI', 'https://example.com/api/v1/'),
    'headers' => [
        'Authorization' => 'Bearer ' . env('HAL_API_TOKEN'),
        'Accept' => 'application/hal+json',
    ],
];
```

Make sure to set the `HAL_API_BASE_URI` and `HAL_API_TOKEN` environment variables in your `.env` file:

```env
HAL_API_BASE_URI=https://api.example.com
HAL_API_TOKEN=your-api-token
```

### 4. Generate Models

To generate models from your HAL API, run the following commands in your terminal:

```bash
# Clear the application cache to ensure that any configuration changes are properly loaded
php artisan config:cache

# Generate models based on your HAL API schema
php artisan hal:generate-models

# Update the Composer autoloader to include the new models
composer dump-autoload
```

This will generate the necessary models based on your HAL API schema and ensure they are properly autoloaded.

## Usage

### Basic Usage

After generating the models, you can use them directly from the `Amanank\HalClient\Models\Discovered` namespace or extend them in your `App\Models` namespace.

#### Using Models Directly

```php
use Amanank\HalClient\Models\Discovered\User;
use Amanank\HalClient\Models\Discovered\Post;
use Amanank\HalClient\Models\Discovered\Tag;
use Amanank\HalClient\Models\Discovered\Comment;

// Create a new user
$user = new User();
$user->username = 'john.doe';
$user->email = 'john.doe@example.com';
$user->save();

// Create a new post
$post = new Post();
$post->title = 'My First Post';
$post->content = 'This is the content of my first post.';
$post->user()->associate($user);
$post->save();

// Create a new tag
$tag = new Tag();
$tag->name = 'PHP';
$tag->save();

// Create a new comment
$comment = new Comment();
$comment->content = 'Great post!';
$comment->post()->associate($post);
$comment->user()->associate($user);
$comment->save();
```

#### Extending Models

You can also extend the generated models in your `App\Models` namespace:

```php
namespace App\Models;

use Amanank\HalClient\Models\Discovered\User as DiscoveredUser;

class User extends DiscoveredUser {
    // Add your custom methods or properties here
}
```

#### Creating and Updating Models

```php
use App\Models\User;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Comment;

// Create a new user
$user = new User();
$user->username = 'john.doe';
$user->email = 'john.doe@example.com';
$user->save();

// Update a user
$user = User::find(1);
$user->email = 'new.email@example.com';
$user->save();
```

#### Searching Models

You can get models using the `Model::get` method, which accepts parameters `$page = null`, `$size = null`, and `$sort = null`, and returns a `LengthAwarePaginator`.

Additionally, any search methods exposed by the HAL API can be used directly. For example, `User::findByLastName` returns a collection, and `User::getByEmail` returns a `User` or `null`.

```php
use Amanank\HalClient\Models\Discovered\User;

// Get paginated users
$users = User::get($page = 1, $size = 10, $sort = 'username');

// Find users by last name
$usersByLastName = User::findByLastName('Doe');

// Get user by email
$userByEmail = User::getByEmail('john.doe@example.com');
```

#### Working with Relationships

```php
use App\Models\User;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Comment;

// Get user's posts
$user = User::find(1);
$posts = $user->posts;

// Get post's comments
$post = Post::find(1);
$comments = $post->comments;

// Attach a tag to a post
$post = Post::find(1);
$tag = Tag::find(1);
$post->tags()->attach($tag);

// Detach a tag from a post
$post->tags()->detach($tag);
```

#### Working with Enums

Enums are located under the `Amanank\HalClient\Models\Discovered\Enums` namespace and are prefixed with the model name, such as `UserStatusEnum` and `PostStatusEnum`.

```php
use Amanank\HalClient\Models\Discovered\Enums\UserStatusEnum;
use App\Models\User;

// Set user status
$user = User::find(1);
$user->status = UserStatusEnum::ACTIVE;
$user->save();

// Get users with a specific status
$activeUsers = User::where('status', UserStatusEnum::ACTIVE)->get();
```


### License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

### Contributing
1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Make your changes.
4. Commit your changes (`git commit -am 'Add new feature'`).
5. Push to the branch (`git push origin feature-branch`).
6. Create a new Pull Request.

### Support
If you have any questions or need support, please open an issue on the GitHub repository.
