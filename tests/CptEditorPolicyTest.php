<?php
declare( strict_types=1 );

require_once __DIR__ . '/../src/Runtime/CptEditorPolicy.php';

use JetSync\Runtime\CptEditorPolicy;

/**
 * A regression where News rendered its legacy text inputs, or relation boxes
 * returned to the simplified editor, would make one of these assertions fail.
 */
function expect_same( mixed $expected, mixed $actual, string $message ) : void {
    if ( $expected !== $actual ) {
        throw new RuntimeException( $message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true ) );
    }
}

expect_same( 'select', CptEditorPolicy::get_effective_field_type( 'news', 'model', 'text' ), 'News model must be rendered as a selector.' );
expect_same( 'switcher', CptEditorPolicy::get_effective_field_type( 'news', 'apare-pe-homepage', 'text' ), 'News homepage visibility must be rendered as a Da/Nu switcher.' );
expect_same( [ 'off' => 'Nu', 'on' => 'Da' ], CptEditorPolicy::get_switcher_labels( 'news', 'apare-pe-homepage' ), 'News homepage switcher must use Romanian Nu/Da labels.' );
expect_same( 'text', CptEditorPolicy::get_effective_field_type( 'women', 'model', 'text' ), 'Model profile fields must retain their configured type.' );
expect_same( true, CptEditorPolicy::should_hide_relation_boxes( 'news' ), 'News relation boxes must be hidden from the simplified editor.' );
expect_same( true, CptEditorPolicy::should_hide_relation_boxes( 'women' ), 'Women relation boxes must be hidden from the simplified editor.' );
expect_same( true, CptEditorPolicy::should_hide_relation_boxes( 'men' ), 'Men relation boxes must be hidden from the simplified editor.' );
expect_same( false, CptEditorPolicy::should_hide_relation_boxes( 'video' ), 'Unrelated CPT relation boxes must remain available.' );
expect_same(
    [
        [ 'value' => '', 'label' => '— Select Model —' ],
        [ 'value' => 'anca-ciobanu', 'label' => 'Anca Ciobanu — Women' ],
        [ 'value' => 'matei', 'label' => 'Matei — Men' ],
    ],
    CptEditorPolicy::build_model_options( [
        [ 'slug' => 'anca-ciobanu', 'title' => 'Anca Ciobanu', 'type_label' => 'Women' ],
        [ 'slug' => 'matei', 'title' => 'Matei', 'type_label' => 'Men' ],
    ] ),
    'News model selector must save a model slug while showing an unambiguous editor label.'
);
expect_same(
    'anca-ciobanu',
    CptEditorPolicy::resolve_model_value( 'Anca Ciobanu', [ [ 'value' => 'anca-ciobanu', 'label' => 'Anca Ciobanu — Women' ] ] ),
    'Existing model names must be converted to their selector slug before the post is saved.'
);
expect_same(
    'legacy-model-value',
    CptEditorPolicy::resolve_model_value( 'legacy-model-value', [ [ 'value' => 'anca-ciobanu', 'label' => 'Anca Ciobanu — Women' ] ] ),
    'An unmatched legacy model value must remain intact until the editor selects a replacement.'
);

echo "CptEditorPolicy tests passed.\n";
