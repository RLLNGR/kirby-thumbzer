<?php

declare(strict_types=1);

namespace Rllngr\Thumbzer;

use Kirby\Cms\File;
use Kirby\Cms\ModelWithContent;
use Kirby\Cms\User;

/**
 * Access control for the maintenance routes and the ICC tool:
 * none of them answer anonymous visitors
 */
class Access
{
    /**
     * Permission category registered by the plugin (see `permissions` in index.php)
     */
    public const PERMISSIONS = 'rllngr.kirby-thumbzer';

    /**
     * The user logged in for this request. Like Kirby's API, impersonation
     * by other code (`kirby()->impersonate('kirby')`) does not count.
     */
    public static function user(): User|null
    {
        return kirby()->user(null, false);
    }

    /**
     * Whether the user logged in for this request may use the Panel
     */
    public static function isPanelUser(): bool
    {
        $user = static::user();

        if ($user === null) {
            return false;
        }

        return
            $user->isAdmin() === true ||
            $user->role()->permissions()->for('access', 'panel') === true;
    }

    /**
     * Whether the current user may use a plugin feature (`icc`, `regenerate`):
     * always for admins, otherwise a Panel user whose role has the permission
     */
    public static function can(string $action): bool
    {
        if (static::isPanelUser() === false) {
            return false;
        }

        $user = static::user();

        return
            $user->isAdmin() === true ||
            $user->role()->permissions()->for(static::PERMISSIONS, $action) === true;
    }

    /**
     * Finds a page file by id (`projects/my-project/photo.jpg`) or UUID,
     * provided the current user can open the file and its page in the Panel
     */
    public static function file(mixed $id): File|null
    {
        if (is_string($id) === false || $id === '') {
            return null;
        }

        $file = kirby()->file($id);
        $page = $file?->page();

        if (
            $page === null ||
            static::isAccessible($page) === false ||
            static::isAccessible($file) === false
        ) {
            return null;
        }

        return $file;
    }

    /**
     * Resolves a path and returns it only when it is a file inside /content
     */
    public static function contentPath(string $path): string|null
    {
        $content = realpath(kirby()->root('content'));
        $real    = realpath($path);

        if (
            $content === false ||
            $real === false ||
            str_starts_with($real, $content . DIRECTORY_SEPARATOR) === false ||
            is_file($real) === false
        ) {
            return null;
        }

        return $real;
    }

    /**
     * Path of a file returned by `contentPath()`, relative to /content
     * with `/` separators (`1_projects/_drafts/secret/thumbs/x.webp`)
     */
    public static function relativePath(string $path): string
    {
        $content = realpath(kirby()->root('content'));

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($content) + 1));
    }

    /**
     * Whether a path relative to /content belongs to a draft:
     * a `_drafts` folder on the way, i.e. the page or one of its parents
     */
    public static function isDraftPath(string $relative): bool
    {
        return in_array('_drafts', explode('/', $relative), true);
    }

    /**
     * Signature of a draft thumb URL (`?t=`), so that whoever may see the draft
     * page (Panel user, preview link) loads its thumbs, but nobody can guess
     * them. Same HMAC as Kirby's preview tokens: set the `content.salt` option
     * to a secret value for the strongest guarantee.
     */
    public static function thumbToken(string $relative): string
    {
        return kirby()->contentToken(kirby()->site(), 'rllngr/kirby-thumbzer:' . $relative);
    }

    /**
     * Whether a draft thumb request carries the valid signature
     */
    public static function hasValidThumbToken(string $relative, mixed $token): bool
    {
        return is_string($token) === true && hash_equals(static::thumbToken($relative), $token) === true;
    }

    /**
     * Same check as the Panel when it opens a page or a file
     * (`isReadable()` for Kirby 4 releases without `isAccessible()`)
     */
    protected static function isAccessible(ModelWithContent $model): bool
    {
        return method_exists($model, 'isAccessible') === true
            ? $model->isAccessible()
            : $model->isReadable();
    }
}
