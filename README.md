<p align="center"><img src="https://raw.githubusercontent.com/Lomkit/art/master/laravel-access-control/cover.png" alt="Social Card of Laravel Access Control"></p>

# Laravel Access Control

Laravel Access Control allows you to fully secure your application in two key areas: Policies and Queries. Manage everything in one place!
## Requirements

PHP 8.2+ and Laravel 11+

## Documentation, Installation, and Usage Instructions

See the [documentation](https://laravel-access-control.lomkit.com) for detailed installation and usage instructions.

## What it does

You first need to define the perimeters concerned by your applications.

Create the model control:

```php
class PostControl extends Control
{
    protected function perimeters(): array
    {
        return [
            GlobalPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s global models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return true;
                })
                ->query(function (Builder $query, Model $user) {
                    return $query;
                }),
            ClientPerimeter::new()
                ->allowed(function (Model $user, string $method) {
                    return $user->can(sprintf('%s client models', $method));
                })
                ->should(function (Model $user, Model $model) {
                    return $model->client()->is($user->client);
                })
                ->query(function (Builder $query, Model $user) {
                    return $query->where('client_id', $user->client->getKey());
                }),
        // ...
```

Then set up your policy:

```php
class PostPolicy extends ControlledPolicy
{
    protected string $model = Post::class;
}
```

and you are ready to go !

```php
App\Models\Post::controlled()->get() // Apply the Control to the query

$user->can('view', App\Models\Post::first()) // Check if the user can view the post according to the policy
```