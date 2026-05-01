# Third-Party Package Compatibility

This document covers known compatibility notes for popular Laravel packages when used with Modulate.

Most packages work without any changes. The ones listed here either generate files into default Laravel locations or interact with the `User` model — both of which need minor attention after running `modulate:init`.

---

## Categories

### No action needed

These packages are fully compatible with Modulate out of the box:

- `spatie/laravel-telescope` / `laravel/telescope`
- `barryvdh/laravel-debugbar`
- `laravel/horizon`
- `predis/predis`, `phpredis`
- All queue drivers, mail drivers, cache drivers
- `spatie/laravel-activitylog`
- `spatie/laravel-backup`
- `league/flysystem-*` (storage drivers)

---

### Needs minor setup

#### `laravel/sanctum`

---
package: laravel/sanctum
status: needs-setup
notes: Sanctum may reference App\Models\User when running strict module isolation.
action: "Add HasApiTokens to User model in Auth module"
---

Sanctum's migration references `App\Models\User`. If you ran `modulate:init --strict`, update the Sanctum config:

```php
// config/sanctum.php
'guard' => ['web'],

// If needed, publish and update Sanctum's HasApiTokens reference:
// use App\Modules\Auth\Models\User instead of App\Models\User
```

If you used the default `modulate:init` (with alias), no changes needed — the alias handles it.

#### `spatie/laravel-permission`

---
package: spatie/laravel-permission
status: needs-setup
notes: Role traits must be applied to the relocated Auth module user model.
action: "Add HasRoles trait to Auth module User model"
---

Add `HasRoles` trait to the `User` model in its new location:

```php
// app/Modules/Auth/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

#### `tymon/jwt-auth`

---
package: tymon/jwt-auth
status: needs-setup
notes: JWTSubject implementation must be added to the modularized Auth user model.
action: "Implement JWTSubject on Auth module User model"
---

Implement the `JWTSubject` interface on the `User` model in its new location:

```php
// app/Modules/Auth/Models/User.php
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }
}
```

#### `laravel/passport`

Same as Sanctum — add `HasApiTokens` to the `User` model in its new location and update `config/auth.php` if using `--strict`.

---

### Generates files to default locations

These packages generate files into `app/Http/Controllers/` or similar default paths. After running their generators, move the generated files into the correct module.

#### `laravel/breeze`

---
package: laravel/breeze
status: needs-setup
notes: Breeze generators target default Laravel app and routes directories.
action: "Move generated auth controllers and routes into Auth module"
---

Breeze generates controllers, views, and routes into default Laravel locations. After installing:

1. Move controllers from `app/Http/Controllers/Auth/` → `app/Modules/Auth/Controllers/Web/`
2. Move routes from `routes/auth.php` into `app/Modules/Auth/Routes/web.php`
3. Update namespaces in moved files
4. Delete the original files

#### `laravel/jetstream`

Same approach as Breeze. Jetstream generates more files — controllers, actions, views, tests. Move them module by module after generation.

#### `filament/filament`

---
package: filament/filament
status: needs-setup
notes: Filament can stay in app/Filament or be mapped into a dedicated Admin module.
action: "Choose admin structure and configure Filament paths accordingly"
---

Filament generates resources into `app/Filament/`. This is fine to leave as-is if Filament is your admin panel — it operates as its own parallel structure. Alternatively, treat Filament as its own module: `app/Modules/Admin/` and configure Filament's paths accordingly.

---

### Special cases

#### `laravel/nova`

Nova operates independently of your app structure and is fully compatible with Modulate. Keep Nova resources in `app/Nova/` as normal.

#### `livewire/livewire`

Livewire components can live inside a module:

```
app/Modules/Course/
└── Livewire/
    └── CoursePlayer.php
```

Configure Livewire's component namespace in `config/livewire.php` or use the `#[Component]` attribute to explicitly register components.

#### `inertiajs/inertia-laravel`

Fully compatible. Controllers live in your modules as normal. Inertia's `render()` calls work regardless of where the controller lives.

---

## Contributing Compatibility Notes

If you find a package that needs special setup with Modulate, contributions to this file are welcome. Open a PR with:

- Package name and version tested
- What the issue is
- The exact steps to resolve it

See [CONTRIBUTING.md](../CONTRIBUTING.md) for how to contribute.

---

> `modulate:doctor` — an automated compatibility scanner that checks your installed packages against this list — is planned for Phase 2.
