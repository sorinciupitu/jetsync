<?php
declare( strict_types=1 );

namespace JetSync\Compatibility;

use JetSync\Registry\RelationRegistry;
use JetSync\Runtime\RelationsEngine;

/**
 * Minimal JetEngine API emulator.
 * When JetEngine is deactivated but theme/plugins call jet_engine(),
 * this stub prevents fatal errors and proxies to JetSync.
 */
class JetEngineEmulator {

    private static ?self $instance = null;
    public object $relations;
    public object $listings;
    public object $meta_boxes;
    public object $cpt;

    private function __construct(
        private RelationRegistry $relation_registry,
        private RelationsEngine $relations_engine
    ) {
        $this->relations = new class($this->relation_registry, $this->relations_engine) {
            public function __construct(private $reg, private $engine) {}
            public function get_related_posts(int $post_id, string $rel_id) : array {
                return $this->engine->get_related_items($rel_id, $post_id, 'parent');
            }
            public function get_parent_posts(int $post_id, string $rel_id) : array {
                return $this->engine->get_related_items($rel_id, $post_id, 'child');
            }
            public function __call($n,$a){ return []; }
            public function __get($n){ return $this; }
        };
        $this->listings = new class {
            public function __call($n,$a){ return null; }
            public function __get($n){ return $this; }
        };
        $this->meta_boxes = new class {
            public function __call($n,$a){ return null; }
            public function __get($n){ return $this; }
        };
        $this->cpt = new class {
            public function __call($n,$a){ return null; }
            public function __get($n){ return $this; }
        };
    }

    public static function get_instance(RelationRegistry $reg, RelationsEngine $engine) : self {
        if ( null === self::$instance ) {
            self::$instance = new self($reg, $engine);
        }
        return self::$instance;
    }

    public function __get(string $name) {
        // Return dummy sub-object for any unknown property chain
        if ( isset($this->$name) ) return $this->$name;
        $dummy = new class {
            public function __call($n,$a){ return null; }
            public function __get($n){ return $this; }
        };
        $this->$name = $dummy;
        return $dummy;
    }

    public function __call(string $name, array $args) {
        return null;
    }

    /**
     * Install global stub if JetEngine not present.
     */
    public static function maybe_emulate(RelationRegistry $reg, RelationsEngine $engine) : void {
        if ( class_exists('Jet_Engine', false) ) return;
        // Define minimal Jet_Engine class
        if ( ! class_exists('Jet_Engine') ) {
            eval('class Jet_Engine extends \\JetSync\\Compatibility\\JetEngineEmulator {
                public static function get_instance(){ return \\JetSync\\Compatibility\\JetEngineEmulator::global_instance(); }
            }');
        }
        if ( ! function_exists('jet_engine') ) {
            // use global instance
            eval('function jet_engine(){ return \\JetSync\\Compatibility\\JetEngineEmulator::global_instance(); }');
        }
    }

    private static ?self $global = null;
    public static function global_instance() : self {
        if ( null === self::$global ) {
            global $wpdb;
            // lazy - will be set on first JetSync bootstrap; fallback dummy
            $reg = new RelationRegistry();
            $engine = new RelationsEngine();
            self::$global = new self($reg, $engine);
        }
        return self::$global;
    }

    public static function set_global_instance(self $inst) : void {
        self::$global = $inst;
        self::$instance = $inst;
    }
}
