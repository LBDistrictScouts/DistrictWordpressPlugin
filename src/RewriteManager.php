<?php
/**
 * Rewrite rule version management.
 *
 * @package LBDistrictScouts\DistrictWordpressPlugin
 */

namespace LBDistrictScouts\DistrictWordpressPlugin;

/**
 * Flushes rewrite rules once when the plugin's rewrite schema changes.
 */
class RewriteManager {

    /**
     * Option used to store the last applied rewrite schema version.
     */
    public const OPTION_NAME = 'districtwp_rewrite_version';

    /**
     * Increment this whenever post type/taxonomy rewrite rules change.
     */
    public const VERSION = '1';

    /**
     * Flush rewrite rules if this installation has not applied the current schema.
     *
     * This runs after custom post types are registered on init, so an upgrade of an
     * already-active plugin can refresh rewrite rules without requiring reactivation.
     *
     * @return void
     */
    public static function maybe_flush(): void {
        $installed_version = get_option( self::OPTION_NAME );

        if ( self::VERSION === $installed_version ) {
            return;
        }

        self::flush_and_mark_current();
    }

    /**
     * Flush rewrite rules and record the current schema version.
     *
     * @return void
     */
    public static function flush_and_mark_current(): void {
        flush_rewrite_rules();
        update_option( self::OPTION_NAME, self::VERSION );
    }
}
