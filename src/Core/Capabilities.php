<?php
namespace JetSync\Core;

class Capabilities {

    public const MANAGE = 'jetsync_manage';
    public const MIGRATE = 'jetsync_migrate';

    public static function install() : void {
        $fn = 'get_role';
        if ( ! \function_exists( $fn ) ) {
            return;
        }

        $role = $fn( 'administrator' );
        if ( ! $role ) {
            return;
        }

        $role->add_cap( self::MANAGE );
        $role->add_cap( self::MIGRATE );
    }

    public static function remove() : void {
        $fn = 'get_role';
        if ( ! \function_exists( $fn ) ) {
            return;
        }

        $role = $fn( 'administrator' );
        if ( ! $role ) {
            return;
        }

        $role->remove_cap( self::MANAGE );
        $role->remove_cap( self::MIGRATE );
    }
}
