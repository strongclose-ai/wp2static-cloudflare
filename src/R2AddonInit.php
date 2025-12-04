<?php

namespace WP2Static;

/**
 * R2 API Addon Initialization
 */
class R2AddonInit {
    /**
     * Initialize the R2 API addon
     */
    public static function init() : void {
        // Register addon
        add_action(
            'plugins_loaded',
            [ self::class, 'registerAddon' ],
            11
        );

        // Register hooks
        R2Deployer::registerHooks();

        // Register admin menu
        if ( is_admin() ) {
            add_action(
                'admin_menu',
                [ R2Deployer::class, 'registerOptionsPage' ],
                11
            );

            // Register save options action
            add_action(
                'admin_post_wp2static_r2_api_save_options',
                [ R2Deployer::class, 'saveOptions' ]
            );
        }
    }

    /**
     * Register the R2 API addon
     */
    public static function registerAddon() : void {
        do_action(
            'wp2static_register_addon',
            'wp2static-addon-r2-api',
            'deploy',
            'R2 API Deployment',
            'https://github.com/strongclose-ai/wp2static-cloudflare',
            'Deploy static site to Cloudflare R2 via API with themes, plugins, and database export'
        );
    }
}
