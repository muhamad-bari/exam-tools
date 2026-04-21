# Local Auth Setup

This app reads login credentials from `app/shared/config/auth.local.php` first.
That file is ignored by Git, so every device needs its own copy.

## Example local credentials

- Username: `admin`
- Password: `Your Passsword`

## Setup on another device

1. Copy `app/shared/config/auth.local.example.php` to `app/shared/config/auth.local.php`.
2. Replace `AUTH_CONFIG_PASSWORD_HASH` with a real password hash.
3. Keep `auth.local.php` uncommitted; it is already ignored in `.gitignore`.

## Generate a password hash

Run:

```bash
php -r "echo password_hash('Your Password', PASSWORD_DEFAULT), PHP_EOL;"
```

Example hash for the current password:

```text
$2y$12$k1HCOH6k3t8OitG95PSVlOXBOPVtZAES5qFk5rvSBy/a4Yj0A2C5i
```

## Alternative via environment variables

If you do not want a local file, you can set:

- `EXAM_TOOLS_AUTH_USERNAME`
- `EXAM_TOOLS_AUTH_PASSWORD_HASH`
