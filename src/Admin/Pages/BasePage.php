<?php
declare( strict_types=1 );

namespace JetSync\Admin\Pages;

/**
 * Abstract class for defining admin pages.
 */
abstract class BasePage {

    /**
     * Get the unique slug of the page.
     *
     * @return string
     */
    abstract public static function get_slug() : string;

    /**
     * Get the page title.
     *
     * @return string
     */
    abstract public static function get_title() : string;

    /**
     * Get the page menu title (defaults to title if not overridden).
     *
     * @return string
     */
    public static function get_menu_title() : string {
        return static::get_title();
    }

    /**
     * Render the page content.
     *
     * @return void
     */
    abstract public function render() : void;
}
